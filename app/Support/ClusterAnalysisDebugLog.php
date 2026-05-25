<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Расширенный лог кластеризатора (cache по progressId, для админ-UI).
 */
class ClusterAnalysisDebugLog
{
    public static function enabled(): bool
    {
        return (bool) config('cabinet-cluster.debug_log', true);
    }

    public static function cacheKey(string $progressId): string
    {
        return 'cluster_analysis_debug:' . md5($progressId);
    }

    public static function clear(string $progressId): void
    {
        if ($progressId === '') {
            return;
        }

        Cache::forget(static::cacheKey($progressId));
    }

    /**
     * @return array<int, array{t: string, level: string, message: string, context: array}>
     */
    public static function get(string $progressId): array
    {
        if ($progressId === '' || ! static::enabled()) {
            return [];
        }

        return Cache::get(static::cacheKey($progressId), []);
    }

    public static function info(string $progressId, string $message, array $context = []): void
    {
        static::append($progressId, 'info', $message, $context);
    }

    public static function warn(string $progressId, string $message, array $context = []): void
    {
        static::append($progressId, 'warn', $message, $context);
    }

    public static function error(string $progressId, string $message, array $context = []): void
    {
        static::append($progressId, 'error', $message, $context);
    }

    public static function append(string $progressId, string $level, string $message, array $context = []): void
    {
        if ($progressId === '' || ! static::enabled()) {
            return;
        }

        $key = static::cacheKey($progressId);
        $entries = Cache::get($key, []);
        $entries[] = [
            't' => now()->format('H:i:s') . '.' . substr((string) now()->format('v'), 0, 3),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $max = (int) config('cabinet-cluster.debug_log_max_entries', 250);
        if (count($entries) > $max) {
            $entries = array_slice($entries, -$max);
        }

        $ttl = (int) config('cabinet-cluster.debug_log_ttl_minutes', 120);
        Cache::put($key, $entries, now()->addMinutes($ttl));

        Log::info('[cluster] ' . $message, array_merge(
            ['progress_id' => $progressId, 'level' => $level],
            $context
        ));
    }
}
