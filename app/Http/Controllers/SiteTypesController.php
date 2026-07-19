<?php

namespace App\Http\Controllers;

use App\Services\SiteTypesService;
use App\SiteTypesHistory;
use App\Support\CompetitorSearchRegions;
use App\Support\SiteTypesLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteTypesController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $defaultYandex = CompetitorSearchRegions::defaultRegion('yandex');
        $defaultGoogle = CompetitorSearchRegions::defaultRegion('google');
        $categories = config('cabinet-site-types.categories', []);
        $depths = config('cabinet-site-types.depths', [3, 5, 10, 20, 30]);
        $defaultDepth = (int) config('cabinet-site-types.default_depth', 10);
        $costUnit = (int) config('cabinet-site-types.cost_per_phrase_engine', 1);

        $limit = SiteTypesLimits::limitForUser($user);
        $remaining = SiteTypesLimits::remainingForUser($user);
        $historyLimit = SiteTypesLimits::historyLimitForUser($user);
        $canSaveHistory = SiteTypesLimits::canSaveHistory($user);
        $savedCount = SiteTypesLimits::savedCount($user);
        $histories = [];

        if ($canSaveHistory) {
            $histories = SiteTypesHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit((int) ($historyLimit ?: 50))
                ->get(['id', 'title', 'params', 'phrases_count', 'results_count', 'cost', 'created_at']);
        }

        return view('pages.site-types', compact(
            'defaultYandex',
            'defaultGoogle',
            'categories',
            'depths',
            'defaultDepth',
            'costUnit',
            'limit',
            'remaining',
            'historyLimit',
            'canSaveHistory',
            'savedCount',
            'histories'
        ));
    }

    public function analyze(Request $request, SiteTypesService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        $phrasesRaw = (string) $request->input('phrases', '');
        $phrases = preg_split('/\r\n|\r|\n/u', $phrasesRaw) ?: [];
        $phrases = $service->normalizePhrases($phrases);

        $engines = [];
        if ($request->boolean('yandex', true)) {
            $engines[] = 'yandex';
        }
        if ($request->boolean('google', false)) {
            $engines[] = 'google';
        }

        if ($phrases === []) {
            return response()->json(['error' => 'validation', 'message' => __('Site types phrases required')], 422);
        }
        if ($engines === []) {
            return response()->json(['error' => 'validation', 'message' => __('Site types engines required')], 422);
        }

        $depth = $service->normalizeDepth((int) $request->input('depth', config('cabinet-site-types.default_depth', 10)));
        $cost = SiteTypesService::estimateCost(count($phrases), $engines, $depth);
        if (! SiteTypesLimits::canSpend($cost, $user)) {
            $message = SiteTypesLimits::limitMessage($user) ?: __('Site types limit exhausted');

            return response()->json([
                'error' => 'limit',
                'message' => $message,
                'remaining' => SiteTypesLimits::remainingForUser($user),
                'limit' => SiteTypesLimits::limitForUser($user),
                'cost' => $cost,
            ], 403);
        }

        $customDomains = [];
        $categories = config('cabinet-site-types.categories', []);
        foreach (array_keys($categories) as $type) {
            $customDomains[$type] = $service->parseDomainList($request->input('custom_' . $type, ''));
        }

        $params = [
            'phrases' => $phrases,
            'engines' => $engines,
            'depth' => $depth,
            'yandex_lr' => (string) $request->input('yandex_lr', config('cabinet-site-types.default_yandex_lr', '213')),
            'google_lr' => (string) $request->input('google_lr', config('cabinet-site-types.default_google_lr', '1011969')),
            'custom_domains' => $customDomains,
        ];

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        try {
            $result = $service->analyze($params);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'fetch_failed',
                'message' => __('Site types fetch failed'),
            ], 502);
        }

        SiteTypesLimits::spend($result['cost'], $user);

        $historyId = null;
        $historyWarning = null;
        $save = $request->boolean('save', false);
        if ($save && SiteTypesLimits::canSaveHistory($user)) {
            if (! SiteTypesLimits::canSaveAnother($user)) {
                $historyWarning = SiteTypesLimits::historyLimitMessage($user)
                    ?: __('Site types history limit exhausted');
            } else {
                $title = $phrases[0] ?? __('Site types');
                if (count($phrases) > 1) {
                    $title .= ' +' . (count($phrases) - 1);
                }

                $history = SiteTypesHistory::query()->create([
                    'user_id' => $user->id,
                    'title' => mb_substr($title, 0, 255),
                    'params' => $params,
                    'results' => [
                        'summary' => $result['summary'],
                        'phrase_matrix' => $result['phrase_matrix'] ?? [],
                        'frequent_hosts' => $result['frequent_hosts'] ?? [],
                        'queries' => $result['queries'],
                        'categories' => $result['categories'],
                        'depth' => $result['depth'],
                    ],
                    'phrases_count' => count($phrases),
                    'results_count' => (int) ($result['summary']['total_positions'] ?? 0),
                    'cost' => $result['cost'],
                ]);
                $historyId = $history->id;
            }
        }

        return response()->json([
            'ok' => true,
            'cost' => $result['cost'],
            'requests' => $result['requests'],
            'errors' => $result['errors'],
            'depth' => $result['depth'],
            'summary' => $result['summary'],
            'phrase_matrix' => $result['phrase_matrix'] ?? [],
            'frequent_hosts' => $result['frequent_hosts'] ?? [],
            'queries' => $result['queries'],
            'categories' => $result['categories'],
            'remaining' => SiteTypesLimits::remainingForUser($user),
            'limit' => SiteTypesLimits::limitForUser($user),
            'history_id' => $historyId,
            'history_warning' => $historyWarning,
            'saved_count' => SiteTypesLimits::savedCount($user),
            'history_limit' => SiteTypesLimits::historyLimitForUser($user),
            'can_save_history' => SiteTypesLimits::canSaveHistory($user),
        ]);
    }

    public function historyShow(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! SiteTypesLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Site types history paid only')], 403);
        }

        $row = SiteTypesHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $row) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $row->id,
                'title' => $row->title,
                'params' => $row->params,
                'results' => $row->results,
                'phrases_count' => $row->phrases_count,
                'results_count' => $row->results_count,
                'cost' => $row->cost,
                'created_at' => optional($row->created_at)->format('d.m.Y H:i'),
            ],
        ]);
    }

    public function historyDestroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! SiteTypesLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Site types history paid only')], 403);
        }

        $deleted = SiteTypesHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return response()->json([
            'ok' => (bool) $deleted,
            'saved_count' => SiteTypesLimits::savedCount($user),
            'history_limit' => SiteTypesLimits::historyLimitForUser($user),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $payload = $request->input('queries');
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (! is_array($payload)) {
            $payload = [];
        }

        $categories = config('cabinet-site-types.categories', []);
        $labels = [];
        foreach ($categories as $key => $cat) {
            $labels[$key] = (string) ($cat['label'] ?? $key);
        }
        $labels['unknown'] = 'Не определён';

        $filename = 'site-types-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($payload, $labels) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [
                __('Site types col phrase'),
                __('Site types col engine'),
                __('Site types col position'),
                __('Site types col domain'),
                __('Site types col type'),
                __('Site types col url'),
            ], ';');

            foreach ($payload as $query) {
                if (! is_array($query)) {
                    continue;
                }
                $phrase = (string) ($query['phrase'] ?? '');
                $engine = (string) ($query['engine'] ?? '');
                foreach (($query['rows'] ?? []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $type = (string) ($row['type'] ?? 'unknown');
                    fputcsv($out, [
                        $phrase,
                        $engine,
                        $row['position'] ?? '',
                        $row['domain'] ?? '',
                        $labels[$type] ?? $type,
                        $row['url'] ?? '',
                    ], ';');
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
