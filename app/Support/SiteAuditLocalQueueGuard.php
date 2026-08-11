<?php

namespace App\Support;

/**
 * Local + shared remote MySQL: jobs идут в site_audit_local.
 * Если воркер умер / застрял в sandbox без сети — краулы висят на «Запуск».
 * Heartbeat пишет queue:work; при старте/поллинге поднимаем ./scripts/dev-site-audit-queue.sh.
 */
class SiteAuditLocalQueueGuard
{
    private const HEARTBEAT_MAX_AGE = 90;

    private const RESTART_COOLDOWN = 45;

    public static function isLocalQueue(): bool
    {
        $queue = (string) config('site_audit.queue', 'site_audit');

        return $queue === 'site_audit_local'
            || (app()->environment('local') && substr($queue, -6) === '_local');
    }

    public static function heartbeatPath(): string
    {
        return storage_path('logs/dev-site-audit.heartbeat');
    }

    public static function touchHeartbeat(): void
    {
        if (! self::isLocalQueue()) {
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
        if (! self::isLocalQueue()) {
            return ['ok' => true, 'restarted' => false, 'message' => null, 'age' => null];
        }

        $age = self::heartbeatAgeSec();
        if ($age !== null && $age <= self::HEARTBEAT_MAX_AGE) {
            return ['ok' => true, 'restarted' => false, 'message' => null, 'age' => $age];
        }

        $lock = storage_path('logs/dev-site-audit.restart.lock');
        if (is_file($lock) && (time() - (int) @filemtime($lock)) < self::RESTART_COOLDOWN) {
            return [
                'ok' => false,
                'restarted' => false,
                'message' => 'Очередь аудита не отвечает — уже перезапускаю воркеры',
                'age' => $age,
            ];
        }

        @file_put_contents($lock, (string) time());

        $script = base_path('scripts/dev-site-audit-queue.sh');
        if (! is_file($script)) {
            return [
                'ok' => false,
                'restarted' => false,
                'message' => 'Нет scripts/dev-site-audit-queue.sh — запустите локальный воркер вручную',
                'age' => $age,
            ];
        }

        $log = storage_path('logs/dev-site-audit-guard.log');
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
            'message' => 'Очередь аудита зависла — перезапустил локальные воркеры',
            'age' => $age,
        ];
    }
}
