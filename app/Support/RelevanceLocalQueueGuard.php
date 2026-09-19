<?php

namespace App\Support;

/**
 * Local: Bitrix Shop API ставит jobs в relevance_* очереди.
 * Без queue:work анализ зависает на queued (0%).
 */
class RelevanceLocalQueueGuard
{
    private const HEARTBEAT_MAX_AGE = 90;

    private const RESTART_COOLDOWN = 30;

    public static function isLocal(): bool
    {
        return app()->environment('local');
    }

    public static function heartbeatPath(): string
    {
        return storage_path('logs/dev-relevance.heartbeat');
    }

    public static function touchHeartbeat(): void
    {
        if (! self::isLocal()) {
            return;
        }

        $argv = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
            ? implode(' ', $_SERVER['argv'])
            : '';
        if (strpos($argv, 'relevance_') === false) {
            return;
        }

        @file_put_contents(self::heartbeatPath(), (string) time());
    }

    public static function heartbeatAgeSec(): ?int
    {
        $path = self::heartbeatPath();
        if (! is_file($path)) {
            return null;
        }

        $raw = trim((string) @file_get_contents($path));
        $ts = ctype_digit($raw) ? (int) $raw : (int) @filemtime($path);
        if ($ts <= 0) {
            return null;
        }

        return max(0, time() - $ts);
    }

    public static function isHealthy(?int $maxAge = null): bool
    {
        $maxAge = $maxAge ?? self::HEARTBEAT_MAX_AGE;
        $age = self::heartbeatAgeSec();

        return $age !== null && $age <= $maxAge;
    }

    /**
     * @return array{ok:bool,restarted:bool,message:?string,age:?int}
     */
    public static function ensureWorkers(): array
    {
        if (! self::isLocal()) {
            return ['ok' => true, 'restarted' => false, 'message' => null, 'age' => null];
        }

        $age = self::heartbeatAgeSec();
        if ($age !== null && $age <= self::HEARTBEAT_MAX_AGE) {
            return ['ok' => true, 'restarted' => false, 'message' => null, 'age' => $age];
        }

        $lock = storage_path('logs/dev-relevance.restart.lock');
        if (is_file($lock) && (time() - (int) @filemtime($lock)) < self::RESTART_COOLDOWN) {
            return [
                'ok' => false,
                'restarted' => false,
                'message' => 'Очередь relevance не отвечает — уже перезапускаю воркер',
                'age' => $age,
            ];
        }

        @file_put_contents($lock, (string) time());

        $script = base_path('scripts/dev-relevance-queue.sh');
        if (! is_file($script)) {
            return [
                'ok' => false,
                'restarted' => false,
                'message' => 'Нет scripts/dev-relevance-queue.sh — запустите queue:work --queue=relevance_high_priority',
                'age' => $age,
            ];
        }

        @chmod($script, 0755);
        $log = storage_path('logs/dev-relevance-guard.log');
        $cmd = sprintf(
            'cd %s && /bin/bash %s >>%s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg($script),
            escapeshellarg($log)
        );
        @exec($cmd);

        return [
            'ok' => false,
            'restarted' => true,
            'message' => 'Очередь relevance зависла — перезапустил локальный воркер',
            'age' => $age,
        ];
    }
}
