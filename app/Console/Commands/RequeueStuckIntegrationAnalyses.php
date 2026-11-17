<?php

namespace App\Console\Commands;

use App\Http\Controllers\RelevanceController;
use App\IntegrationAnalysis;
use App\Jobs\Relevance\RelevanceAnalyseQueue;
use App\RelevanceProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RequeueStuckIntegrationAnalyses extends Command
{
    protected $signature = 'integration:requeue-stuck-analyses
                            {--minutes=15 : Сколько минут queued без старта}
                            {--limit=20 : Сколько заданий поднять за один проход}';

    protected $description = 'Снова ставит в очередь анализы API, которые так и остались queued';

    public function handle(): int
    {
        $minutes = max(5, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subMinutes($minutes);

        $rows = IntegrationAnalysis::query()
            ->where('status', IntegrationAnalysis::STATUS_QUEUED)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $requeued = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $analysis) {
            $progress = RelevanceProgress::where('hash', $analysis->progress_hash)->first();
            if ($progress && (int) $progress->progress > 0 && ! $progress->error) {
                $analysis->status = IntegrationAnalysis::STATUS_RUNNING;
                $analysis->save();
                $skipped++;
                continue;
            }

            $hash = (string) $analysis->progress_hash;
            $jobExists = $hash !== '' && DB::table('jobs')
                ->where('queue', RelevanceController::HIGH_QUEUE)
                ->where('payload', 'like', '%' . $hash . '%')
                ->exists();
            if ($jobExists) {
                $skipped++;
                continue;
            }

            $payload = is_array($analysis->request_payload) ? $analysis->request_payload : [];
            $tries = (int) ($payload['queue_retries'] ?? 0);
            if ($tries >= 2) {
                $analysis->status = IntegrationAnalysis::STATUS_FAILED;
                $analysis->error = 'queue_not_started';
                $analysis->save();
                $failed++;
                continue;
            }

            if ($payload === [] || empty($payload['hash'])) {
                $analysis->status = IntegrationAnalysis::STATUS_FAILED;
                $analysis->error = 'queue_not_started';
                $analysis->save();
                $failed++;
                continue;
            }

            $payload['queue_retries'] = $tries + 1;
            $analysis->request_payload = $payload;
            $analysis->save();

            RelevanceAnalyseQueue::dispatch($payload, false, (int) $analysis->user_id, 'full')
                ->onQueue(RelevanceController::HIGH_QUEUE)
                ->onConnection('database');
            $requeued++;
        }

        $this->info('requeued=' . $requeued . ' failed=' . $failed . ' skipped=' . $skipped);

        return 0;
    }
}
