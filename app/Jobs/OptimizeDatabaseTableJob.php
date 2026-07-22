<?php

namespace App\Jobs;

use App\DatabaseTableOptimizeRun;
use App\Services\Database\TableOptimizeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * OPTIMIZE TABLE — только очередь db-optimize (1 supervisor-воркер), без stampede на default.
 */
class OptimizeDatabaseTableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Крупные таблицы могут идти часами. */
    public $timeout = 14400;

    public $tries = 3;

    /** @var int */
    private $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
        $this->onQueue((string) config('cabinet-database-admin.optimize_queue', 'db-optimize'));
    }

    public function handle(TableOptimizeService $optimizer): void
    {
        $run = DatabaseTableOptimizeRun::query()->find($this->runId);
        if ($run === null) {
            return;
        }

        if (in_array($run->status, ['ok', 'failed'], true)) {
            return;
        }

        try {
            set_time_limit(0);
            try {
                $optimizer->executeRun($run);
            } catch (\Throwable $e) {
                // Зависший lock после kill воркера — сбросить и один раз повторить
                if (strpos($e->getMessage(), 'OPTIMIZE_BUSY:') === 0) {
                    Cache::forget('cabinet.db-optimize.lock');
                    $optimizer->executeRun($run);

                    return;
                }
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error('OptimizeDatabaseTableJob failed', [
                'run_id' => $this->runId,
                'table' => $run->table_name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
