<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Расширенный лог анализа конкурентов (cache по pageHash, для админ-UI).
 */
class CompetitorAnalysisDebugLog
{
    public static function enabled(): bool
    {
        return (bool) config('cabinet-competitor-analysis.debug_log', true);
    }

    public static function cacheKey(string $pageHash): string
    {
        return 'competitor_analysis_debug:' . md5($pageHash);
    }

    public static function clear(string $pageHash): void
    {
        if ($pageHash === '') {
            return;
        }

        Cache::forget(static::cacheKey($pageHash));
        Cache::forget(static::terminalCacheKey($pageHash));
    }

    public static function terminalCacheKey(string $pageHash): string
    {
        return 'competitor_analysis_terminal:' . md5($pageHash);
    }

    /**
     * Финальное состояние после удаления строки progress (чтобы опрос не получал 0%).
     *
     * @param array{failed: bool, message?: string, result?: array|null} $state
     */
    public static function rememberTerminal(string $pageHash, array $state): void
    {
        if ($pageHash === '' || ! static::enabled()) {
            return;
        }

        $ttl = (int) config('cabinet-competitor-analysis.debug_log_ttl_minutes', 120);
        Cache::put(static::terminalCacheKey($pageHash), $state, now()->addMinutes($ttl));
    }

    /**
     * @return array{failed: bool, message?: string, result?: array|null}|null
     */
    public static function getTerminal(string $pageHash): ?array
    {
        if ($pageHash === '' || ! static::enabled()) {
            return null;
        }

        $state = Cache::get(static::terminalCacheKey($pageHash));

        return is_array($state) ? $state : null;
    }

    /**
     * @return array<int, array{t: string, level: string, message: string, context: array}>
     */
    public static function get(string $pageHash): array
    {
        if ($pageHash === '' || ! static::enabled()) {
            return [];
        }

        return Cache::get(static::cacheKey($pageHash), []);
    }

    public static function info(string $pageHash, string $message, array $context = []): void
    {
        static::append($pageHash, 'info', $message, $context);
    }

    public static function warn(string $pageHash, string $message, array $context = []): void
    {
        static::append($pageHash, 'warn', $message, $context);
    }

    public static function error(string $pageHash, string $message, array $context = []): void
    {
        static::append($pageHash, 'error', $message, $context);
    }

    public static function append(string $pageHash, string $level, string $message, array $context = []): void
    {
        if ($pageHash === '' || ! static::enabled()) {
            return;
        }

        $key = static::cacheKey($pageHash);
        $entries = Cache::get($key, []);
        $entries[] = [
            't' => now()->format('H:i:s') . '.' . substr((string) now()->format('v'), 0, 3),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $max = (int) config('cabinet-competitor-analysis.debug_log_max_entries', 250);
        if (count($entries) > $max) {
            $entries = array_slice($entries, -$max);
        }

        $ttl = (int) config('cabinet-competitor-analysis.debug_log_ttl_minutes', 120);
        Cache::put($key, $entries, now()->addMinutes($ttl));

        Log::info('[competitor-analysis] ' . $message, array_merge(
            ['page_hash' => $pageHash, 'level' => $level],
            $context
        ));
    }
}
