<?php

namespace App\Jobs\Integration;

use App\IntegrationAnalysis;
use App\IntegrationBatch;
use App\IntegrationBatchItem;
use App\Services\Integration\RelevanceAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshIntegrationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $batchId;

    public function __construct(int $batchId)
    {
        $this->batchId = $batchId;
    }

    public function handle(RelevanceAnalysisService $analyses)
    {
        $batch = IntegrationBatch::with('items')->find($this->batchId);
        if (!$batch) {
            return;
        }

        foreach ($batch->items as $item) {
            if ($item->status !== IntegrationBatchItem::STATUS_RUNNING || !$item->analysis_public_id) {
                continue;
            }

            $analysis = IntegrationAnalysis::where('public_id', $item->analysis_public_id)->first();
            if (!$analysis) {
                continue;
            }

            $analysis = $analyses->refreshStatus($analysis);
            if ($analysis->status === IntegrationAnalysis::STATUS_DONE) {
                $item->status = IntegrationBatchItem::STATUS_DONE;
                $item->history_id = $analysis->history_id;
                $item->save();
            } elseif ($analysis->status === IntegrationAnalysis::STATUS_FAILED) {
                $item->status = IntegrationBatchItem::STATUS_FAILED;
                $item->error = $analysis->error ?: 'analysis_failed';
                $item->save();
            }
        }

        $batch->done_items = $batch->items()->where('status', IntegrationBatchItem::STATUS_DONE)->count();
        $batch->failed_items = $batch->items()->where('status', IntegrationBatchItem::STATUS_FAILED)->count();

        $hasQueued = $batch->items()->where('status', IntegrationBatchItem::STATUS_QUEUED)->exists();
        $hasRunning = $batch->items()->where('status', IntegrationBatchItem::STATUS_RUNNING)->exists();

        if ($hasQueued) {
            ProcessIntegrationBatchJob::dispatch($batch->id)
                ->onQueue($this->queue ?: 'relevance_normal_priority')
                ->onConnection('database');
            return;
        }

        if ($hasRunning) {
            self::dispatch($batch->id)
                ->delay(now()->addSeconds(20))
                ->onQueue($this->queue ?: 'relevance_normal_priority')
                ->onConnection('database');
            return;
        }

        $batch->status = $batch->failed_items === $batch->total_items
            ? IntegrationBatch::STATUS_FAILED
            : IntegrationBatch::STATUS_DONE;
        $batch->save();
    }
}
