<?php

namespace App\Jobs;

use App\DatabaseTableOptimizeRun;
use App\Services\Database\TableOptimizeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OptimizeDatabaseTableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Крупные таблицы (search_indices и т.п.) могут идти часами. */
    public $timeout = 14400;

    public $tries = 1;

    /** @var int */
    private $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
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
            $optimizer->executeRun($run);
        } catch (\Throwable $e) {
            Log::error('OptimizeDatabaseTableJob failed', [
                'run_id' => $this->runId,
                'table' => $run->table_name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
