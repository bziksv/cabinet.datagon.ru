<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\IntegrationBatch;
use App\IntegrationBatchItem;
use App\Jobs\Integration\ProcessIntegrationBatchJob;
use App\Services\Integration\RelevanceAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RelevanceBatchController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $items = $request->input('items');
        if (!is_array($items) || $items === []) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'items array is required',
            ], 422);
        }

        $max = (int) config('integration_api.batch_max_items', 100);
        if (count($items) > $max) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'Too many items (max ' . $max . ')',
            ], 422);
        }

        $apiKey = $request->attributes->get('integration_api_key');
        $batch = IntegrationBatch::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => Auth::id(),
            'api_key_id' => $apiKey ? $apiKey->id : null,
            'status' => IntegrationBatch::STATUS_QUEUED,
            'total_items' => 0,
            'done_items' => 0,
            'failed_items' => 0,
        ]);

        $created = 0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['url'] ?? ''));
            $phrase = trim((string) ($row['phrase'] ?? ''));
            if ($url === '' || $phrase === '') {
                continue;
            }
            $parsed = parse_url($url);
            if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
                continue;
            }
            if (!in_array(strtolower((string) $parsed['scheme']), ['http', 'https'], true)) {
                continue;
            }
            if (mb_strlen($phrase) > 50) {
                $phrase = mb_substr($phrase, 0, 50);
            }

            IntegrationBatchItem::create([
                'batch_id' => $batch->id,
                'external_id' => isset($row['external_id']) ? (string) $row['external_id'] : null,
                'url' => $url,
                'phrase' => $phrase,
                'status' => IntegrationBatchItem::STATUS_QUEUED,
            ]);
            $created++;
        }

        if ($created === 0) {
            $batch->delete();
            return response()->json([
                'error' => 'validation_error',
                'message' => 'No valid items (need url + phrase)',
            ], 422);
        }

        $batch->total_items = $created;
        $batch->save();

        ProcessIntegrationBatchJob::dispatch($batch->id)
            ->onQueue('relevance_normal_priority')
            ->onConnection('database');

        \App\Support\RelevanceLocalQueueGuard::ensureWorkers();

        return response()->json([
            'batch_id' => $batch->public_id,
            'status' => $batch->status,
            'total_items' => $batch->total_items,
        ], 202);
    }

    public function show(string $id, RelevanceAnalysisService $analyses): JsonResponse
    {
        $batch = IntegrationBatch::where('public_id', $id)
            ->where('user_id', Auth::id())
            ->with('items')
            ->first();

        if (!$batch) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Refresh running analysis links
        foreach ($batch->items as $item) {
            if ($item->status === IntegrationBatchItem::STATUS_RUNNING && $item->analysis_public_id) {
                $analysis = \App\IntegrationAnalysis::where('public_id', $item->analysis_public_id)
                    ->where('user_id', Auth::id())
                    ->first();
                if ($analysis) {
                    $analysis = $analyses->refreshStatus($analysis);
                    if ($analysis->status === \App\IntegrationAnalysis::STATUS_DONE) {
                        $item->status = IntegrationBatchItem::STATUS_DONE;
                        $item->history_id = $analysis->history_id;
                        $item->save();
                        $batch->done_items = $batch->items()->where('status', IntegrationBatchItem::STATUS_DONE)->count();
                    } elseif ($analysis->status === \App\IntegrationAnalysis::STATUS_FAILED) {
                        $item->status = IntegrationBatchItem::STATUS_FAILED;
                        $item->error = $analysis->error;
                        $item->save();
                        $batch->failed_items = $batch->items()->where('status', IntegrationBatchItem::STATUS_FAILED)->count();
                    }
                }
            }
        }

        $pending = $batch->items()
            ->whereIn('status', [IntegrationBatchItem::STATUS_QUEUED, IntegrationBatchItem::STATUS_RUNNING])
            ->count();

        if ($pending === 0 && $batch->total_items > 0) {
            $batch->status = $batch->failed_items === $batch->total_items
                ? IntegrationBatch::STATUS_FAILED
                : IntegrationBatch::STATUS_DONE;
        } elseif ($batch->status === IntegrationBatch::STATUS_QUEUED && $batch->done_items + $batch->failed_items > 0) {
            $batch->status = IntegrationBatch::STATUS_RUNNING;
        }
        $batch->save();

        return response()->json([
            'batch_id' => $batch->public_id,
            'status' => $batch->status,
            'total_items' => $batch->total_items,
            'done_items' => $batch->done_items,
            'failed_items' => $batch->failed_items,
            'items' => $batch->items->map(function (IntegrationBatchItem $item) {
                return [
                    'external_id' => $item->external_id,
                    'url' => $item->url,
                    'phrase' => $item->phrase,
                    'status' => $item->status,
                    'analysis_id' => $item->analysis_public_id,
                    'history_id' => $item->history_id,
                    'error' => $item->error,
                ];
            })->values(),
        ]);
    }
}
