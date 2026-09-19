<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\IntegrationAnalysis;
use App\RelevanceHistory;
use App\Services\Integration\RelevanceAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class RelevanceAnalysisController extends Controller
{
    /** @var RelevanceAnalysisService */
    protected $analyses;

    public function __construct(RelevanceAnalysisService $analyses)
    {
        $this->analyses = $analyses;
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $analysis = $this->analyses->start(
                Auth::user(),
                $request->attributes->get('integration_api_key'),
                $request->all()
            );
        } catch (InvalidArgumentException $e) {
            $code = $e->getMessage() === 'relevance_limit_exhausted' ? 429 : 422;
            return response()->json([
                'error' => $e->getMessage(),
                'message' => $e->getMessage() === 'relevance_limit_exhausted'
                    ? 'Monthly relevance limit exhausted'
                    : $e->getMessage(),
            ], $code);
        }

        return response()->json([
            'analysis_id' => $analysis->public_id,
            'status' => $analysis->status,
        ], 202);
    }

    public function show(string $id): JsonResponse
    {
        $analysis = IntegrationAnalysis::where('public_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$analysis) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $analysis = $this->analyses->refreshStatus($analysis);

        return response()->json([
            'analysis_id' => $analysis->public_id,
            'status' => $analysis->status,
            'progress' => $this->analyses->progressPercent($analysis),
            'history_id' => $analysis->history_id,
            'url' => $analysis->url,
            'phrase' => $analysis->phrase,
            'error' => $analysis->error,
        ]);
    }

    public function historiesIndex(Request $request): JsonResponse
    {
        $url = trim((string) $request->query('url', ''));
        $phrase = trim((string) $request->query('phrase', ''));
        $limit = (int) $request->query('limit', 10);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 30) {
            $limit = 30;
        }

        if ($url === '' && $phrase === '') {
            return response()->json([
                'error' => 'url_or_phrase_required',
                'message' => 'Pass url and/or phrase',
            ], 422);
        }

        $items = $this->analyses->listHistoriesForLanding(
            Auth::user(),
            $url !== '' ? $url : null,
            $phrase !== '' ? $phrase : null,
            $limit,
            trim((string) $request->query('site_host', '')) ?: null
        );

        $latest = $items[0] ?? null;

        return response()->json([
            'latest' => $latest,
            'items' => $items,
            'count' => count($items),
        ]);
    }

    public function history(int $historyId): JsonResponse
    {
        $history = RelevanceHistory::where('id', $historyId)
            ->where('user_id', Auth::id())
            ->with(['results:id,project_id,average_values'])
            ->first();

        if (!$history) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $avgRaw = $history->results ? ($history->results->average_values ?? null) : null;
        $params = $this->analyses->analysisParamsFromHistory($history);

        return response()->json([
            'history_id' => $history->id,
            'phrase' => $history->phrase,
            'url' => $history->main_link,
            'region' => $params['region'] !== '' ? $params['region'] : $history->region,
            'engine' => $params['engine'],
            'top' => $params['top'],
            'points' => $history->points,
            'points_ideal' => $this->analyses->idealPointsFromAverageValues($avgRaw),
            'coverage' => $history->coverage,
            'density' => $history->density,
            'position' => $history->position,
            'last_check' => $history->last_check,
            'created_at' => optional($history->created_at)->toIso8601String(),
        ]);
    }

    public function missingPhrases(Request $request, int $historyId): JsonResponse
    {
        $history = RelevanceHistory::where('id', $historyId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$history) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $filter = strtolower((string) $request->query('filter', 'zero'));
        if (!in_array($filter, ['zero', 'diff', 'all'], true)) {
            $filter = 'zero';
        }

        $mode = strtolower((string) $request->query('mode', ''));
        if ($mode === 'tlp') {
            $tlp = $this->analyses->tlpForGeneration($history);
            return response()->json([
                'history_id' => $history->id,
                'mode' => 'tlp',
                'sort' => 'tfidf_top',
                'missing' => $tlp['missing'],
                'diff' => $tlp['diff'],
                'missing_total' => $tlp['missing_total'],
                'diff_total' => $tlp['diff_total'],
                'defaults' => [
                    'missing_limit' => (int) config('integration_api.tlp_missing_limit', 200),
                    'diff_limit' => (int) config('integration_api.tlp_diff_limit', 5),
                ],
            ]);
        }

        $data = $this->analyses->missingPhrases($history, $filter);

        return response()->json([
            'history_id' => $history->id,
            'filter' => $filter,
            'phrases' => $data['phrases'],
            'unigram' => $data['unigram'],
        ]);
    }

    public function clouds(Request $request, int $historyId): JsonResponse
    {
        $history = RelevanceHistory::where('id', $historyId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$history) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $limit = (int) $request->query('limit', 100);
        $clouds = $this->analyses->cloudsForHistory($history, $limit);

        return response()->json([
            'history_id' => $history->id,
            'competitors' => $clouds['competitors'],
            'landing' => $clouds['landing'],
        ]);
    }
}
