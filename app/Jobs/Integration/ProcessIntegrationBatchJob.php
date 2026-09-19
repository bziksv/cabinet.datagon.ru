<?php

namespace App\Jobs\Integration;

use App\IntegrationAnalysis;
use App\IntegrationApiKey;
use App\IntegrationBatch;
use App\IntegrationBatchItem;
use App\Services\Integration\RelevanceAnalysisService;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ProcessIntegrationBatchJob implements ShouldQueue
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

        $user = User::find($batch->user_id);
        if (!$user) {
            $batch->status = IntegrationBatch::STATUS_FAILED;
            $batch->save();
            return;
        }

        Auth::setUser($user);
        $apiKey = $batch->api_key_id
            ? IntegrationApiKey::find($batch->api_key_id)
            : null;

        $batch->status = IntegrationBatch::STATUS_RUNNING;
        $batch->save();

        foreach ($batch->items as $item) {
            if ($item->status !== IntegrationBatchItem::STATUS_QUEUED) {
                continue;
            }

            $item->status = IntegrationBatchItem::STATUS_RUNNING;
            $item->save();

            try {
                $analysis = $analyses->start($user, $apiKey, [
                    'url' => $item->url,
                    'phrase' => $item->phrase,
                ]);
                $item->analysis_public_id = $analysis->public_id;
                $item->save();
            } catch (InvalidArgumentException $e) {
                $item->status = IntegrationBatchItem::STATUS_FAILED;
                $item->error = $e->getMessage();
                $item->save();
                $batch->failed_items = $batch->items()->where('status', IntegrationBatchItem::STATUS_FAILED)->count();
                $batch->save();
            }

            // Start next items without waiting for completion — clients poll batch/analysis.
            // Cap concurrent starts lightly: process one start per job invocation chain.
            break;
        }

        // Re-dispatch while queued items remain
        $hasQueued = $batch->items()->where('status', IntegrationBatchItem::STATUS_QUEUED)->exists();
        $hasRunning = $batch->items()->where('status', IntegrationBatchItem::STATUS_RUNNING)->exists();

        if ($hasQueued) {
            // Delay a bit so we don't stampede SERP
            self::dispatch($batch->id)
                ->delay(now()->addSeconds(15))
                ->onQueue($this->queue ?: 'relevance_normal_priority')
                ->onConnection('database');
        } elseif (!$hasRunning) {
            $batch->done_items = $batch->items()->where('status', IntegrationBatchItem::STATUS_DONE)->count();
            $batch->failed_items = $batch->items()->where('status', IntegrationBatchItem::STATUS_FAILED)->count();
            $batch->status = $batch->failed_items === $batch->total_items
                ? IntegrationBatch::STATUS_FAILED
                : IntegrationBatch::STATUS_DONE;
            $batch->save();
        } else {
            // Still running — schedule a refresh pass
            RefreshIntegrationBatchJob::dispatch($batch->id)
                ->delay(now()->addSeconds(20))
                ->onQueue($this->queue ?: 'relevance_normal_priority')
                ->onConnection('database');
        }
    }
}
