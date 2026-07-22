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

    /** Ждём освобождения lock: 720 × 20с ≈ 4 часа */
    public $tries = 720;

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
            $done = $optimizer->tryExecuteRun($run);
            if (! $done) {
                // Другой OPTIMIZE ещё идёт — остаёмся в очереди и пробуем позже
                $run->status = 'queued';
                $run->message = __('Database optimize waiting', [
                    'table' => (string) \Illuminate\Support\Facades\Cache::get('cabinet.db-optimize.lock', '—'),
                ]);
                $run->finished_at = null;
                $run->save();
                $this->release(20);

                return;
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
