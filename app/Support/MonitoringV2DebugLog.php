<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Расширенный лог /monitoring-v2 (cache по debug_session, для админ-UI).
 */
class MonitoringV2DebugLog
{
    public static function enabled(): bool
    {
        return (bool) config('cabinet-monitoring.debug_log', true);
    }

    public static function cacheKey(string $session): string
    {
        return 'monitoring_v2_debug:' . md5($session);
    }

    public static function stateKey(string $session): string
    {
        return 'monitoring_v2_debug_state:' . md5($session);
    }

    public static function clear(string $session): void
    {
        if ($session === '') {
            return;
        }

        Cache::forget(static::cacheKey($session));
        Cache::forget(static::stateKey($session));
    }

    /**
     * @return array<int, array{t: string, level: string, message: string, context: array}>
     */
    public static function get(string $session): array
    {
        if ($session === '' || ! static::enabled()) {
            return [];
        }

        return Cache::get(static::cacheKey($session), []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getState(string $session): ?array
    {
        if ($session === '' || ! static::enabled()) {
            return null;
        }

        $state = Cache::get(static::stateKey($session));

        return is_array($state) ? $state : null;
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function setState(string $session, array $state): void
    {
        if ($session === '' || ! static::enabled()) {
            return;
        }

        $ttl = (int) config('cabinet-monitoring.debug_log_ttl_minutes', 120);
        Cache::put(static::stateKey($session), $state, now()->addMinutes($ttl));
    }

    public static function info(string $session, string $message, array $context = []): void
    {
        static::append($session, 'info', $message, $context);
    }

    public static function warn(string $session, string $message, array $context = []): void
    {
        static::append($session, 'warn', $message, $context);
    }

    public static function error(string $session, string $message, array $context = []): void
    {
        static::append($session, 'error', $message, $context);
    }

    public static function append(string $session, string $level, string $message, array $context = []): void
    {
        if ($session === '' || ! static::enabled()) {
            return;
        }

        $key = static::cacheKey($session);
        $entries = Cache::get($key, []);
        $entries[] = [
            't' => now()->format('H:i:s') . '.' . substr((string) now()->format('v'), 0, 3),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $max = (int) config('cabinet-monitoring.debug_log_max_entries', 250);
        if (count($entries) > $max) {
            $entries = array_slice($entries, -$max);
        }

        $ttl = (int) config('cabinet-monitoring.debug_log_ttl_minutes', 120);
        Cache::put($key, $entries, now()->addMinutes($ttl));

        Log::info('[monitoring-v2] ' . $message, array_merge(
            ['debug_session' => $session, 'level' => $level],
            $context
        ));
    }
}
