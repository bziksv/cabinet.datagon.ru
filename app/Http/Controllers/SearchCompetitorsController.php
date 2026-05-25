<?php

namespace App\Http\Controllers;

use App\CompetitorConfig;
use App\CompetitorsProgressBar;
use App\Jobs\CompetitorAnalyse\CompetitorAnalyseQueue;
use App\SearchCompetitors;
use App\Support\CompetitorAnalysisDebugLog;
use App\Support\CompetitorSearchRegions;
use App\Support\GoogleGeoRegions;
use App\Support\PhpCliBinary;
use App\Support\YandexLrRegions;
use Symfony\Component\Process\Process;
use App\TariffSetting;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class SearchCompetitorsController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:Competitor analysis']);
    }

    public function index()
    {
        $admin = User::isUserAdmin();
        $config = CompetitorConfig::first();

        $defaultSearchEngine = config('cabinet-competitor-analysis.default_search_engine', 'yandex');

        return view('competitors.index', [
            'admin' => $admin,
            'config' => $config,
            'defaultSearchEngine' => $defaultSearchEngine,
            'defaultRegion' => YandexLrRegions::find('213'),
            'defaultGoogleRegion' => GoogleGeoRegions::find('1011969'),
        ]);
    }

    public function searchRegions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(50, max(5, (int) $request->query('limit', 25)));
        $engine = CompetitorSearchRegions::normalizeEngine($request->query('engine'));

        return response()->json([
            'engine' => $engine,
            'results' => CompetitorSearchRegions::search($engine, $q, $limit),
        ]);
    }

    public function analyseSites(Request $request): JsonResponse
    {
        $maxRegions = (int) config('cabinet-competitor-analysis.max_regions', 5);

        $request->validate([
            'search_engines' => ['sometimes', 'array', 'min:1', 'max:2'],
            'search_engines.*' => ['string', 'in:yandex,google'],
            'search_engine' => ['sometimes', 'string', 'in:yandex,google'],
            'regions_yandex' => ['sometimes', 'array', 'max:' . $maxRegions],
            'regions_yandex.*' => ['string', 'max:12', 'regex:/^\d+$/'],
            'regions_google' => ['sometimes', 'array', 'max:' . $maxRegions],
            'regions_google.*' => ['string', 'max:12', 'regex:/^\d+$/'],
            'regions' => ['sometimes', 'array', 'max:' . $maxRegions],
            'regions.*' => ['string', 'max:12', 'regex:/^\d+$/'],
            'region' => ['sometimes', 'string', 'max:12', 'regex:/^\d+$/'],
            'count' => ['required', 'in:10,20'],
            'phrases' => ['required', 'string'],
        ]);

        $engines = CompetitorSearchRegions::normalizeEnginesList($request->input('search_engines', []));
        if (count($engines) === 1 && $request->filled('search_engine')) {
            $engines = CompetitorSearchRegions::normalizeEnginesList($request->input('search_engine'));
        }

        $regionsByEngine = $this->collectRegionsByEngineFromRequest($request, $engines, $maxRegions);
        $plan = CompetitorSearchRegions::buildAnalysisPlanFromRequest($engines, $regionsByEngine, $maxRegions);

        if (count($plan) === 0) {
            return response()->json([
                'message' => count($engines) > 1
                    ? __('Select regions for each search engine')
                    : __('Select at least one search region'),
            ], 422);
        }

        if (count($plan) !== count($engines)) {
            return response()->json([
                'message' => __('Invalid region'),
            ], 422);
        }

        $regionSteps = 0;
        foreach ($plan as $planItem) {
            $regionSteps += count($planItem['regions']);
        }

        $countPhrases = count(array_unique(array_diff(explode("\n", $request->input('phrases')), [''])));
        $tariffUnits = $countPhrases * $regionSteps;

        try {
            if (TariffSetting::checkSearchCompetitorsLimits($tariffUnits)) {
                return response()->json([
                    'message' => __('Exceeding the limit')
                ], 500);
            } else if ($countPhrases > 40) {
                return response()->json([
                    'message' => __('The maximum number of keywords is 40, and you have - ') . $countPhrases
                ], 500);
            }

            $payload = $request->all();
            $payload['pageHash'] = (string) $request->input('pageHash');
            $payload['search_engines'] = array_map(static function ($item) {
                return $item['engine'];
            }, $plan);
            $payload['search_engine'] = $payload['search_engines'][0];
            $payload['analysis_plan'] = array_map(static function ($item) {
                return [
                    'engine' => $item['engine'],
                    'regions' => array_map(static function ($r) {
                        return $r['id'];
                    }, $item['regions']),
                ];
            }, $plan);

            $pageHash = (string) $request->input('pageHash');
            CompetitorAnalysisDebugLog::clear($pageHash);
            CompetitorAnalysisDebugLog::info($pageHash, 'http.analyseSites.accepted', [
                'user_id' => Auth::id(),
                'phrases' => $countPhrases,
                'count' => (int) $request->input('count'),
                'region_steps' => $regionSteps,
                'engines' => $payload['search_engines'],
            ]);

            $job = (new CompetitorAnalyseQueue($payload, Auth::id()))->onQueue('competitor_analyse');
            $this->dispatchCompetitorAnalyseJob($job, $pageHash);

            return $this->withCompetitorDebug($request, [
                'success' => true,
            ]);
        } catch (Throwable $e) {
            $pageHash = (string) $request->input('pageHash');
            CompetitorAnalysisDebugLog::error($pageHash, 'http.analyseSites.exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            Log::debug('competitor error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->withCompetitorDebug($request, [
                'object' => CompetitorsProgressBar::where('page_hash', '=', $pageHash)->delete(),
                'message' => __('An unexpected error has occurred, please contact the administrator')
            ], 500);
        }
    }

    public function startProgressBar(Request $request): JsonResponse
    {
        $pageHash = (string) $request->input('pageHash');
        $progress = CompetitorsProgressBar::firstOrNew([
            'page_hash' => $pageHash,
        ]);
        $progress->percent = 1;
        $progress->result = null;
        $progress->save();

        CompetitorAnalysisDebugLog::clear($pageHash);

        CompetitorAnalysisDebugLog::info($pageHash, 'http.startProgressBar', [
            'progress_id' => $progress->id,
            'was_new' => $progress->wasRecentlyCreated,
        ]);

        return $this->withCompetitorDebug($request, [
            'code' => 200,
            'object_id' => $progress->id,
        ]);
    }

    public function getProgressBar(Request $request): JsonResponse
    {
        $pageHash = (string) $request->input('pageHash');
        $progress = CompetitorsProgressBar::where('page_hash', $pageHash)->first();

        if ($progress === null) {
            $terminal = CompetitorAnalysisDebugLog::getTerminal($pageHash);
            if ($terminal !== null) {
                CompetitorAnalysisDebugLog::info($pageHash, 'http.getProgressBar.terminal_cache', $terminal);

                if (! empty($terminal['failed'])) {
                    return $this->withCompetitorDebug($request, [
                        'percent' => 100,
                        'failed' => true,
                        'message' => (string) ($terminal['message'] ?? __('An unexpected error has occurred, please contact the administrator')),
                        'code' => 200,
                        'terminal' => true,
                    ]);
                }

                if (! empty($terminal['result'])) {
                    return $this->withCompetitorDebug($request, [
                        'percent' => 100,
                        'result' => $terminal['result'],
                        'code' => 200,
                        'terminal' => true,
                    ]);
                }
            }

            CompetitorAnalysisDebugLog::warn($pageHash, 'http.getProgressBar.missing_row');
        }

        if (isset($progress) && (int) $progress->percent >= 100) {
            $result = json_decode($progress->result, true);
            $failed = is_array($result) && !empty($result['error']);
            $progress->delete();

            CompetitorAnalysisDebugLog::info($pageHash, 'http.getProgressBar.complete', [
                'failed' => $failed,
                'has_result' => is_array($result),
            ]);

            if ($failed) {
                CompetitorAnalysisDebugLog::rememberTerminal($pageHash, [
                    'failed' => true,
                    'message' => (string) ($result['message'] ?? ''),
                ]);

                return $this->withCompetitorDebug($request, [
                    'percent' => 100,
                    'failed' => true,
                    'message' => (string) ($result['message'] ?? __('An unexpected error has occurred, please contact the administrator')),
                    'code' => 200,
                ]);
            }

            CompetitorAnalysisDebugLog::rememberTerminal($pageHash, [
                'failed' => false,
                'result' => $result,
            ]);

            return $this->withCompetitorDebug($request, [
                'percent' => 100,
                'result' => $result,
                'code' => 200,
            ]);
        }

        $percent = (int) ($progress->percent ?? 0);

        return $this->withCompetitorDebug($request, [
            'percent' => $percent,
            'code' => 200,
            'debug_state' => [
                'row_exists' => $progress !== null,
                'percent_raw' => $progress->percent ?? null,
                'updated_at' => $progress->updated_at ?? null,
            ],
        ]);
    }

    public function removeProgressBar(Request $request): JsonResponse
    {
        return response()->json([
            'object' => CompetitorsProgressBar::where('page_hash', '=', $request->input('pageHash'))->delete()
        ]);
    }

    public function config()
    {
        if (!User::isUserAdmin()) {
            return abort(403);
        }

        $now = Carbon::now();
        $counter = (int)SearchCompetitors::where('month', '=', $now->year . '-' . $now->month)
            ->sum('counter');
        $config = CompetitorConfig::first();
        $uniqueUsers = [
            30 => SearchCompetitors::countUniqueUsersSinceDays(30),
            60 => SearchCompetitors::countUniqueUsersSinceDays(60),
            90 => SearchCompetitors::countUniqueUsersSinceDays(90),
        ];

        return view('competitors.config', [
            'admin' => true,
            'config' => $config,
            'counter' => $counter,
            'uniqueUsers' => $uniqueUsers,
        ]);

    }

    public function editConfig(Request $request): RedirectResponse
    {
        $config = CompetitorConfig::first();
        $config->update($request->all());

        return Redirect::back();
    }

    /**
     * @param array<int, string> $engines
     * @return array<string, array<int, string>>
     */
    protected function dispatchCompetitorAnalyseJob(CompetitorAnalyseQueue $job, string $pageHash): void
    {
        $runAfterResponse = app()->environment('local')
            && (bool) config('cabinet-competitor-analysis.run_job_after_response', true);

        if ($runAfterResponse) {
            ignore_user_abort(true);
            CompetitorAnalysisDebugLog::info($pageHash, 'dispatch.shutdown.registered', [
                'env' => app()->environment(),
            ]);

            register_shutdown_function(static function () use ($job, $pageHash) {
                $jobMaxSec = (int) config('cabinet-competitor-analysis.job_max_execution_sec', 1200);
                @set_time_limit($jobMaxSec);
                @ini_set('max_execution_time', (string) $jobMaxSec);
                CompetitorAnalysisDebugLog::info($pageHash, 'dispatch.shutdown.run.start');
                try {
                    dispatch_now($job);
                    CompetitorAnalysisDebugLog::info($pageHash, 'dispatch.shutdown.run.done');
                } catch (Throwable $e) {
                    CompetitorAnalysisDebugLog::error($pageHash, 'dispatch.shutdown.failed', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                    Log::debug('competitor analyse shutdown failed', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            });

            return;
        }

        CompetitorAnalysisDebugLog::info($pageHash, 'dispatch.queue', [
            'connection' => config('queue.default'),
        ]);
        dispatch($job);

        if (app()->environment('local') && (bool) config('cabinet-competitor-analysis.spawn_queue_worker', false)) {
            $this->spawnCompetitorQueueWorkerOnce();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function withCompetitorDebug(Request $request, array $payload, int $status = 200): JsonResponse
    {
        if (User::isUserAdmin()) {
            $pageHash = (string) $request->input('pageHash');
            $payload['debug_log'] = CompetitorAnalysisDebugLog::get($pageHash);
            $payload['debug_admin'] = true;
        }

        return response()->json($payload, $status);
    }

    protected function spawnCompetitorQueueWorkerOnce(): void
    {
        $php = PhpCliBinary::resolve();
        if (!PhpCliBinary::looksLikeCli($php)) {
            Log::debug('competitor queue worker skipped: php CLI not found', [
                'php_binary' => $php,
            ]);

            return;
        }

        $logFile = storage_path('logs/competitor-analyse-once.log');
        $process = new Process([
            $php,
            base_path('artisan'),
            'queue:work',
            '--queue=competitor_analyse',
            '--once',
            '--timeout=1200',
        ], base_path());
        $process->setTimeout(null);
        try {
            $process->start(static function ($type, $buffer) use ($logFile) {
                @file_put_contents($logFile, $buffer, FILE_APPEND);
            });
        } catch (Throwable $e) {
            Log::debug('competitor queue worker spawn failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<int, string> $engines
     * @return array<string, array<int, string>>
     */
    protected function collectRegionsByEngineFromRequest(Request $request, array $engines, int $maxRegions): array
    {
        $out = [];

        foreach ($engines as $engine) {
            $key = 'regions_' . $engine;
            $ids = array_values(array_unique(array_filter((array) $request->input($key, []))));

            if (count($engines) === 1 && empty($ids)) {
                $ids = array_values(array_unique(array_filter((array) $request->input('regions', []))));
                if (empty($ids) && $request->filled('region')) {
                    $ids = [(string) $request->input('region')];
                }
            }

            if (count($ids) > $maxRegions) {
                return [];
            }

            $out[$engine] = $ids;
        }

        return $out;
    }

    public function getRecommendations(Request $request): JsonResponse
    {
        $analysedSites = json_decode((string) $request->input('analysedSites'), true);
        $totalMetaTags = json_decode((string) $request->input('totalMetaTags'), true);

        if (! is_array($analysedSites) || count($analysedSites) === 0) {
            $metaOnly = json_decode((string) $request->input('metaTags'), true);
            if (is_array($metaOnly)) {
                $totalMetaTags = $metaOnly;
                $analysedSites = array_fill_keys(array_keys($metaOnly), []);
            }
        }

        if (! is_array($totalMetaTags) || count($totalMetaTags) === 0) {
            return response()->json([
                'message' => __('No analysis data for recommendations'),
            ], 422);
        }

        $tags = json_decode((string) $request->input('selectedTags'), true);
        if (! is_array($tags) || count($tags) === 0) {
            $tags = config('cabinet-competitor-analysis.recommendation_default_tags', ['title', 'h1', 'description']);
        }

        $service = new \App\Services\Competitor\CompetitorMetaRecommendations();
        $result = $service->build(
            is_array($analysedSites) ? $analysedSites : [],
            $totalMetaTags,
            $request->input('count', '10'),
            $tags
        );

        return response()->json([
            'result' => $result,
            'tags' => $tags,
            'code' => 200,
        ]);
    }
}
