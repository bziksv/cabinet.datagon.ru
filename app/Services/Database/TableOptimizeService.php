<?php

namespace App\Services\Database;

use App\DatabaseTableOptimizeRun;
use App\Jobs\OptimizeDatabaseTableJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Щадящий OPTIMIZE TABLE: sync до порога МБ, иначе очередь; история в database_table_optimize_runs.
 */
class TableOptimizeService
{
    private const LOCK_KEY = 'cabinet.db-optimize.lock';

    /**
     * @return array{queued: bool, run: DatabaseTableOptimizeRun, message: string}
     */
    public function requestOptimize(string $table, string $triggeredBy = 'ui', bool $forceQueue = false): array
    {
        $table = $this->sanitizeTableName($table);
        if (! $this->tableExists($table)) {
            throw new \InvalidArgumentException(__('Database preview table not found'));
        }

        if (! $this->historyReady()) {
            throw new \RuntimeException(__('Database optimize history missing'));
        }

        $deny = array_flip(config('cabinet-database-admin.optimize_deny_tables', []));
        if (isset($deny[$table])) {
            throw new \InvalidArgumentException(__('Database optimize not allowed'));
        }

        // Уже в очереди / выполняется — не плодим дубли
        $existing = DatabaseTableOptimizeRun::query()
            ->where('table_name', $table)
            ->whereIn('status', ['queued', 'running'])
            ->orderByDesc('id')
            ->first();
        if ($existing !== null) {
            return [
                'queued' => true,
                'run' => $existing,
                'message' => __('Database optimize already queued', ['table' => $table]),
            ];
        }

        $stats = $this->measureTable($table);
        $syncMaxMb = (float) config('cabinet-database-admin.optimize_sync_max_mb', 500);
        // UI (forceQueue) и крупные таблицы — всегда в очередь; несколько подряд OK (jobs ждут lock)
        $useQueue = $forceQueue || $stats['size_mb'] >= $syncMaxMb;

        $run = DatabaseTableOptimizeRun::query()->create([
            'table_name' => $table,
            'status' => $useQueue ? 'queued' : 'running',
            'mode' => $useQueue ? 'queue' : 'sync',
            'triggered_by' => $triggeredBy,
            'size_before_mb' => $stats['size_mb'],
            'data_free_before_mb' => $stats['data_free_mb'],
            'started_at' => $useQueue ? null : now(),
        ]);

        if ($useQueue) {
            OptimizeDatabaseTableJob::dispatch($run->id)->onQueue(
                (string) config('cabinet-database-admin.optimize_queue', 'default')
            );

            return [
                'queued' => true,
                'run' => $run,
                'message' => __('Database optimize queued', [
                    'table' => $table,
                    'size' => $this->formatMb($stats['size_mb']),
                ]),
            ];
        }

        // sync: если сейчас занято другим OPTIMIZE — тоже в очередь
        if ($this->isBusy()) {
            $run->status = 'queued';
            $run->mode = 'queue';
            $run->started_at = null;
            $run->save();
            OptimizeDatabaseTableJob::dispatch($run->id)->onQueue(
                (string) config('cabinet-database-admin.optimize_queue', 'default')
            );

            return [
                'queued' => true,
                'run' => $run,
                'message' => __('Database optimize queued busy', [
                    'table' => $table,
                    'busy' => (string) Cache::get(self::LOCK_KEY, '—'),
                ]),
            ];
        }

        $this->executeRun($run);

        $run = $run->fresh();
        if ($run === null || $run->status !== 'ok') {
            throw new \RuntimeException($run->message ?? __('Database optimize status failed'));
        }

        return [
            'queued' => false,
            'run' => $run,
            'message' => $this->successMessage($run),
        ];
    }

    /**
     * @return bool true если lock взят и OPTIMIZE выполнен (или failed по ошибке MySQL)
     *              false если сейчас занято — вызывающий должен retry
     */
    public function tryExecuteRun(DatabaseTableOptimizeRun $run, bool $refreshInventory = true): bool
    {
        $table = $this->sanitizeTableName((string) $run->table_name);

        if (! Cache::add(self::LOCK_KEY, $table, (int) config('cabinet-database-admin.optimize_lock_seconds', 7200))) {
            return false;
        }

        try {
            $this->performOptimize($run, $refreshInventory);

            return true;
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }

    public function executeRun(DatabaseTableOptimizeRun $run, bool $refreshInventory = true): DatabaseTableOptimizeRun
    {
        $table = $this->sanitizeTableName((string) $run->table_name);

        if (! Cache::add(self::LOCK_KEY, $table, (int) config('cabinet-database-admin.optimize_lock_seconds', 7200))) {
            // Для sync/cron: не помечаем failed — пусть caller решит
            throw new \RuntimeException('OPTIMIZE_BUSY:' . (string) Cache::get(self::LOCK_KEY, '—'));
        }

        try {
            return $this->performOptimize($run, $refreshInventory);
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }

    private function performOptimize(DatabaseTableOptimizeRun $run, bool $refreshInventory = true): DatabaseTableOptimizeRun
    {
        $table = $this->sanitizeTableName((string) $run->table_name);

        try {
            $before = $this->measureTable($table);
            $run->status = 'running';
            $run->started_at = $run->started_at ?: now();
            $run->size_before_mb = $before['size_mb'];
            $run->data_free_before_mb = $before['data_free_mb'];
            $run->message = null;
            $run->save();

            DB::statement('OPTIMIZE TABLE `' . str_replace('`', '', $table) . '`');

            $after = $this->measureTable($table);
            $freed = round($before['size_mb'] - $after['size_mb'], 2);

            $run->status = 'ok';
            $run->size_after_mb = $after['size_mb'];
            $run->freed_mb = $freed;
            $run->finished_at = now();
            $run->message = null;
            $run->save();

            if ($refreshInventory) {
                try {
                    app(DatabaseInventoryService::class)->refreshMetadata();
                } catch (\Throwable $e) {
                    // снимок обновится при следующем refresh
                }
            }

            return $run;
        } catch (\Throwable $e) {
            $run->status = 'failed';
            $run->message = mb_substr($e->getMessage(), 0, 1000);
            $run->finished_at = now();
            $run->save();

            throw $e;
        }
    }

    /**
     * @return array{size_mb: float, data_free_mb: float, size_bytes: int, data_free_bytes: int}
     */
    public function measureTable(string $table): array
    {
        $table = $this->sanitizeTableName($table);
        $schema = (string) config('database.connections.mysql.database');
        $row = DB::selectOne(
            'SELECT DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length, DATA_FREE AS data_free
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$schema, $table]
        );

        if ($row === null) {
            throw new \InvalidArgumentException(__('Database preview table not found'));
        }

        $sizeBytes = (int) $row->data_length + (int) $row->index_length;
        $freeBytes = (int) $row->data_free;

        return [
            'size_bytes' => $sizeBytes,
            'data_free_bytes' => $freeBytes,
            'size_mb' => round($sizeBytes / 1024 / 1024, 2),
            'data_free_mb' => round($freeBytes / 1024 / 1024, 2),
        ];
    }

    public function isBusy(): bool
    {
        return Cache::has(self::LOCK_KEY);
    }

    public function historyReady(): bool
    {
        try {
            return Schema::hasTable('database_table_optimize_runs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, array{status: string, optimized_at: ?string, freed_mb: ?float, size_before_mb: ?float, size_after_mb: ?float, data_free_before_mb: ?float, mode: ?string, message: ?string}>
     */
    public function latestRunsByTable(): array
    {
        if (! $this->historyReady()) {
            return [];
        }

        $rows = DB::select(
            'SELECT r.*
             FROM database_table_optimize_runs r
             INNER JOIN (
                 SELECT table_name, MAX(id) AS max_id
                 FROM database_table_optimize_runs
                 GROUP BY table_name
             ) t ON t.max_id = r.id'
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->table_name] = [
                'status' => (string) $row->status,
                'optimized_at' => $row->finished_at ?: $row->started_at ?: $row->created_at,
                'freed_mb' => $row->freed_mb !== null ? (float) $row->freed_mb : null,
                'size_before_mb' => $row->size_before_mb !== null ? (float) $row->size_before_mb : null,
                'size_after_mb' => $row->size_after_mb !== null ? (float) $row->size_after_mb : null,
                'data_free_before_mb' => $row->data_free_before_mb !== null ? (float) $row->data_free_before_mb : null,
                'mode' => (string) ($row->mode ?? ''),
                'message' => $row->message !== null ? (string) $row->message : null,
            ];
        }

        return $out;
    }

    public function sanitizeTableName(string $table): string
    {
        $table = strtolower(trim($table));
        if (! preg_match('/^[a-z][a-z0-9_]{0,62}$/', $table)) {
            throw new \InvalidArgumentException(__('Database preview invalid table'));
        }

        return $table;
    }

    private function tableExists(string $table): bool
    {
        $schema = (string) config('database.connections.mysql.database');

        return DB::table('information_schema.tables')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->exists();
    }

    private function successMessage(DatabaseTableOptimizeRun $run): string
    {
        $freed = (float) ($run->freed_mb ?? 0);

        return __('Database optimize done', [
            'table' => $run->table_name,
            'freed' => $this->formatMbSigned($freed),
            'after' => $this->formatMb((float) ($run->size_after_mb ?? 0)),
        ]);
    }

    public function formatMb(float $mb): string
    {
        if ($mb >= 1024) {
            return round($mb / 1024, 2) . ' GB';
        }

        return round($mb, 1) . ' MB';
    }

    public function formatMbSigned(float $mb): string
    {
        $sign = $mb > 0 ? '−' : ($mb < 0 ? '+' : '');

        return $sign . $this->formatMb(abs($mb));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRun(?DatabaseTableOptimizeRun $run): ?array
    {
        if ($run === null) {
            return null;
        }

        $finished = $run->finished_at ?: $run->started_at ?: $run->created_at;
        if ($finished && ! is_string($finished)) {
            $finished = $finished->toDateTimeString();
        }

        return [
            'id' => (int) $run->id,
            'table' => (string) $run->table_name,
            'status' => (string) $run->status,
            'mode' => (string) $run->mode,
            'optimized_at' => $finished ? (string) $finished : null,
            'freed_mb' => $run->freed_mb !== null ? (float) $run->freed_mb : null,
            'size_before_mb' => $run->size_before_mb !== null ? (float) $run->size_before_mb : null,
            'size_after_mb' => $run->size_after_mb !== null ? (float) $run->size_after_mb : null,
            'data_free_before_mb' => $run->data_free_before_mb !== null ? (float) $run->data_free_before_mb : null,
            'message' => $run->message !== null ? (string) $run->message : null,
        ];
    }

    /**
     * Текущий статус OPTIMIZE + актуальный DATA_FREE / size для UI.
     *
     * @return array{table: string, size_mb: float, data_free_mb: float, optimize: ?array}
     */
    public function statusPayload(string $table): array
    {
        $table = $this->sanitizeTableName($table);
        $stats = $this->measureTable($table);
        $run = null;
        if ($this->historyReady()) {
            $run = DatabaseTableOptimizeRun::query()
                ->where('table_name', $table)
                ->orderByDesc('id')
                ->first();
        }

        return [
            'table' => $table,
            'size_mb' => $stats['size_mb'],
            'data_free_mb' => $stats['data_free_mb'],
            'busy' => $this->isBusy(),
            'busy_table' => $this->isBusy() ? (string) Cache::get(self::LOCK_KEY, '') : null,
            'optimize' => $this->serializeRun($run),
        ];
    }
}
