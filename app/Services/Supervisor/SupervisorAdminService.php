<?php

namespace App\Services\Supervisor;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SupervisorAdminService
{
    /** @var array<string, mixed>|null */
    private $lastProbe;

    public function isEnabled(): bool
    {
        return (bool) config('cabinet-supervisor-admin.enabled');
    }

    /**
     * @return array{ok:bool, enabled:bool, message:string, supervisorctl:string, config_hint:string}
     */
    public function probe(): array
    {
        if ($this->lastProbe !== null) {
            return $this->lastProbe;
        }

        $enabled = $this->isEnabled();
        $supervisorctl = (string) config('cabinet-supervisor-admin.supervisorctl', '/usr/bin/supervisorctl');
        $configHint = (string) config('cabinet-supervisor-admin.config_hint', '');

        if (! $enabled) {
            return $this->lastProbe = [
                'ok' => false,
                'enabled' => false,
                'message' => __('Supervisor admin disabled hint'),
                'supervisorctl' => $supervisorctl,
                'config_hint' => $configHint,
            ];
        }

        try {
            $this->runSupervisorctl(['status']);
        } catch (\Throwable $e) {
            return $this->lastProbe = [
                'ok' => false,
                'enabled' => true,
                'message' => $e->getMessage(),
                'supervisorctl' => $supervisorctl,
                'config_hint' => $configHint,
            ];
        }

        return $this->lastProbe = [
            'ok' => true,
            'enabled' => true,
            'message' => '',
            'supervisorctl' => implode(' ', $this->supervisorctlArgv(['status'])),
            'config_hint' => $configHint,
        ];
    }

    /**
     * @return array<int, array{name:string, status:string, detail:string, uptime:string, pid:string, controllable:bool}>
     */
    public function processes(): array
    {
        $probe = $this->probe();
        if (! $probe['ok']) {
            return [];
        }

        $output = $this->runSupervisorctl(['status']);
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        $processes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseStatusLine($line);
            if ($parsed === null) {
                continue;
            }

            $parsed['controllable'] = $this->isProgramAllowed($parsed['name']);
            $processes[] = $parsed;
        }

        usort($processes, static function (array $a, array $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $processes;
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function control(string $program, string $action): array
    {
        $program = trim($program);
        $action = strtolower(trim($action));

        if ($program === '' || ! $this->isProgramAllowed($program)) {
            return ['ok' => false, 'message' => __('Supervisor program not allowed')];
        }

        if (! in_array($action, ['start', 'stop', 'restart', 'status'], true)) {
            return ['ok' => false, 'message' => __('Supervisor invalid action')];
        }

        $probe = $this->probe();
        if (! $probe['ok']) {
            return ['ok' => false, 'message' => $probe['message']];
        }

        try {
            $this->runSupervisorctl([$action, $program]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => __('Supervisor action done', [
                'action' => $action,
                'program' => $program,
            ]),
        ];
    }

    /**
     * @return array{name:string, exists:bool, tail:string, path:string}
     */
    public function tailLog(string $program, int $lines = 80): array
    {
        $base = preg_replace('/:.*$/', '', $program) ?: $program;
        $base = preg_replace('/_\d+$/', '', $base) ?: $base;

        $logFiles = config('cabinet-supervisor-admin.log_files', []);
        $relative = $logFiles[$base] ?? null;

        if ($relative === null) {
            foreach ($logFiles as $key => $path) {
                if (Str::startsWith($base, $key) || Str::startsWith($program, $key)) {
                    $relative = $path;
                    break;
                }
            }
        }

        $path = $relative ? base_path($relative) : '';

        if ($path === '' || ! is_readable($path)) {
            return [
                'name' => $program,
                'exists' => false,
                'tail' => '',
                'path' => $path,
            ];
        }

        $content = $this->readTail($path, max(10, min($lines, 400)));

        return [
            'name' => $program,
            'exists' => true,
            'tail' => $content,
            'path' => $relative,
        ];
    }

    public function isProgramAllowed(string $program): bool
    {
        $patterns = config('cabinet-supervisor-admin.allowed_programs', ['cabinet-titlo-*']);
        if (! is_array($patterns) || $patterns === []) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $program)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $args
     */
    private function runSupervisorctl(array $args): string
    {
        $command = $this->supervisorctlArgv($args);

        $process = new Process($command, base_path(), null, null, 15);
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $message = $stderr !== '' ? $stderr : $stdout;
            if ($message === '') {
                $message = __('Supervisor command failed');
            }

            throw new \RuntimeException($message);
        }

        return trim($process->getOutput());
    }

    /**
     * SUPERVISORCTL_BIN может быть "sudo /usr/bin/supervisorctl" — разбиваем на argv.
     *
     * @param array<int, string> $args
     * @return array<int, string>
     */
    private function supervisorctlArgv(array $args): array
    {
        $bin = trim((string) config('cabinet-supervisor-admin.supervisorctl', '/usr/bin/supervisorctl'));
        $bin = trim($bin, " \t\n\r\0\x0B\"'");

        $prefix = preg_split('/\s+/', $bin, -1, PREG_SPLIT_NO_EMPTY) ?: ['/usr/bin/supervisorctl'];

        if (($prefix[0] ?? '') === 'sudo' && ($prefix[1] ?? '') !== '-n') {
            array_splice($prefix, 1, 0, ['-n']);
        }

        return array_merge($prefix, $args);
    }

    /**
     * @return array{name:string, status:string, detail:string, uptime:string, pid:string}|null
     */
    private function parseStatusLine(string $line): ?array
    {
        if (! preg_match('/^(\S+)\s+(\S+)\s*(.*)$/', $line, $m)) {
            return null;
        }

        $name = $m[1];
        $status = strtoupper($m[2]);
        $detail = trim($m[3]);

        $pid = '';
        $uptime = '';
        if (preg_match('/pid\s+(\d+)/i', $detail, $pidMatch)) {
            $pid = $pidMatch[1];
        }
        if (preg_match('/uptime\s+([^,]+)/i', $detail, $upMatch)) {
            $uptime = trim($upMatch[1]);
        }

        return [
            'name' => $name,
            'status' => $status,
            'detail' => $detail,
            'uptime' => $uptime,
            'pid' => $pid,
        ];
    }

    private function readTail(string $path, int $lines): string
    {
        if (! is_readable($path)) {
            return '';
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = (int) $file->key();
        $start = max(0, $lastLine - $lines);
        $buffer = [];

        $file->seek($start);
        while (! $file->eof()) {
            $buffer[] = rtrim((string) $file->current(), "\r\n");
            $file->next();
        }

        return implode("\n", $buffer);
    }
}
