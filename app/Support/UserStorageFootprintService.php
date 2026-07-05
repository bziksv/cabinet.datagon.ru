<?php

namespace App\Support;

use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Оценка объёма данных пользователя в БД (строки + ~MB).
 */
class UserStorageFootprintService
{
    private const CACHE_PREFIX = 'cabinet.users.footprint.';

    /** @var array<string, bool> */
    private static $tableExists = [];

    public static function cacheTtlMinutes(): int
    {
        return max(60, (int) config('cabinet-users.storage_cache_ttl_minutes', 1440));
    }

    /**
     * @return array{rows: int, est_mb: float, modules: array<int, array<string, mixed>>, computed_at: string}|null
     */
    public static function getCached(int $userId): ?array
    {
        $payload = Cache::get(self::CACHE_PREFIX . $userId);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param int[] $userIds
     *
     * @return array<int, array{rows: int, est_mb: float, label: string}|null>
     */
    public static function getManyCached(array $userIds): array
    {
        $out = [];
        foreach ($userIds as $id) {
            $id = (int) $id;
            $cached = self::getCached($id);
            if ($cached === null) {
                $out[$id] = null;
                continue;
            }
            $out[$id] = [
                'rows' => (int) ($cached['rows'] ?? 0),
                'est_mb' => (float) ($cached['est_mb'] ?? 0),
                'label' => self::formatBrief($cached),
            ];
        }

        return $out;
    }

    /**
     * @return array{rows: int, est_mb: float, modules: array<int, array<string, mixed>>, computed_at: string}
     */
    public static function computeForUser(int $userId): array
    {
        $modules = [];
        $totalRows = 0;
        $totalBytes = 0;

        foreach (self::moduleDefinitions() as $def) {
            if (!self::tableExists($def['table'])) {
                continue;
            }

            $count = self::countModule($def, $userId);
            if ($count <= 0) {
                continue;
            }

            $avgBytes = (int) ($def['avg_row_bytes'] ?? 256);
            $bytes = $count * $avgBytes;
            $modules[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'rows' => $count,
                'est_kb' => round($bytes / 1024, 1),
            ];
            $totalRows += $count;
            $totalBytes += $bytes;
        }

        $payload = [
            'rows' => $totalRows,
            'est_mb' => round($totalBytes / 1024 / 1024, 2),
            'modules' => $modules,
            'computed_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_PREFIX . $userId, $payload, now()->addMinutes(self::cacheTtlMinutes()));

        return $payload;
    }

    public static function forget(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    /**
     * @param int[] $userIds
     *
     * @return array{users: int, errors: int}
     */
    public static function refreshForUserIds(array $userIds): array
    {
        $done = 0;
        $errors = 0;

        foreach (array_slice(array_unique(array_map('intval', $userIds)), 0, 25) as $userId) {
            if ($userId <= 0) {
                continue;
            }
            try {
                self::computeForUser($userId);
                $done++;
            } catch (\Throwable $e) {
                report($e);
                $errors++;
            }
        }

        if ($done > 0) {
            self::touchRefreshedAt();
        }

        return ['users' => $done, 'errors' => $errors];
    }

    /**
     * Пачка полного прогона (для AJAX, без таймаута на всех пользователей).
     *
     * direction=desc — с больших id вниз (совпадает с сортировкой таблицы по умолчанию).
     *
     * @return array{users: int, errors: int, last_id: int, done: bool, remaining: int, direction: string}
     */
    public static function refreshBatch(int $cursorId, int $limit, string $direction = 'desc'): array
    {
        $limit = max(1, min(25, $limit));
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $done = 0;
        $errors = 0;
        $lastId = $cursorId;

        if ($direction === 'desc') {
            $beforeId = $cursorId > 0 ? $cursorId : ((int) User::query()->max('id')) + 1;
            $userIds = User::query()
                ->where('id', '<', $beforeId)
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('id');
        } else {
            $afterId = max(0, $cursorId);
            $userIds = User::query()
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id');
        }

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            try {
                self::computeForUser($userId);
                $done++;
            } catch (\Throwable $e) {
                report($e);
                $errors++;
            }
            $lastId = $userId;
        }

        if ($direction === 'desc') {
            $remaining = $lastId > 0 && $userIds->isNotEmpty()
                ? (int) User::query()->where('id', '<', $lastId)->count()
                : ($userIds->isEmpty() ? 0 : (int) User::count());
        } else {
            $remaining = $lastId > 0
                ? (int) User::query()->where('id', '>', $lastId)->count()
                : (int) User::count();
        }

        $complete = $userIds->isEmpty() || $remaining === 0;
        if ($complete) {
            self::touchRefreshedAt();
        }

        return [
            'users' => $done,
            'errors' => $errors,
            'last_id' => $lastId,
            'done' => $complete,
            'remaining' => $remaining,
            'direction' => $direction,
            'total' => (int) User::count(),
        ];
    }

    /**
     * @return array{users: int, errors: int}
     */
    public static function refreshAll(int $chunkSize = 100): array
    {
        $chunkSize = max(10, min(500, $chunkSize));
        $done = 0;
        $errors = 0;

        User::query()->orderBy('id')->select('id')->chunkById($chunkSize, static function ($users) use (&$done, &$errors) {
            foreach ($users as $user) {
                try {
                    self::computeForUser((int) $user->id);
                    $done++;
                } catch (\Throwable $e) {
                    report($e);
                    $errors++;
                }
            }
        });

        self::touchRefreshedAt();

        return ['users' => $done, 'errors' => $errors];
    }

    private static function touchRefreshedAt(): void
    {
        Cache::put('cabinet.users.footprint_refreshed_at', now()->toIso8601String(), now()->addDays(7));
    }

    private static function tableExists(string $table): bool
    {
        if (!array_key_exists($table, self::$tableExists)) {
            self::$tableExists[$table] = Schema::hasTable($table);
        }

        return self::$tableExists[$table];
    }

    public static function formatBrief(array $payload): string
    {
        $rows = (int) ($payload['rows'] ?? 0);
        $mb = (float) ($payload['est_mb'] ?? 0);

        if ($rows === 0) {
            return '0';
        }

        if ($mb >= 1) {
            return number_format($rows, 0, ',', ' ') . ' · ~' . number_format($mb, 1, ',', ' ') . ' MB';
        }

        return number_format($rows, 0, ',', ' ') . ' · ~' . number_format($mb * 1024, 0, ',', ' ') . ' KB';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function moduleDefinitions(): array
    {
        return (array) config('cabinet-users.storage_modules', []);
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function countModule(array $def, int $userId): int
    {
        $table = $def['table'];
        $type = $def['type'] ?? 'column';

        if ($type === 'monitoring_keywords_by_creator') {
            return (int) DB::table('monitoring_keywords as mk')
                ->join('monitoring_projects as mp', 'mp.id', '=', 'mk.monitoring_project_id')
                ->where('mp.creator', $userId)
                ->count();
        }

        if ($type === 'monitoring_positions_by_creator') {
            return (int) DB::table('monitoring_positions as mpos')
                ->join('monitoring_keywords as mk', 'mk.id', '=', 'mpos.monitoring_keyword_id')
                ->join('monitoring_projects as mp', 'mp.id', '=', 'mk.monitoring_project_id')
                ->where('mp.creator', $userId)
                ->count();
        }

        $column = $def['column'] ?? 'user_id';

        return (int) DB::table($table)->where($column, $userId)->count();
    }
}
