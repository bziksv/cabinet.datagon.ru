<?php

namespace App\Http\Controllers;

use App\SearchSuggestionsHistory;
use App\Services\SearchSuggestionsService;
use App\Support\DemoCabinet;
use App\Support\SearchSuggestionsLimits;
use App\Support\YandexLrRegions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SearchSuggestionsController extends Controller
{
    /**
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        if (DemoCabinet::isCurrentUser() && ! $request->filled('history')) {
            $showcase = DemoCabinet::searchSuggestionsShowcasePath();
            if ($showcase) {
                return redirect($showcase);
            }
        }

        $user = Auth::user();
        $defaultRegion = YandexLrRegions::find((string) config('cabinet-search-suggestions.default_yandex_lr', '213'))
            ?: YandexLrRegions::find('213');
        $googleDomains = config('cabinet-search-suggestions.google_domains', []);
        $googleCountries = config('cabinet-search-suggestions.google_countries', []);
        $defaultGoogleDomain = (string) config('cabinet-search-suggestions.default_google_domain', 'google.ru');
        $defaultGoogleGl = (string) config('cabinet-search-suggestions.default_google_gl', 'ru');
        $limit = SearchSuggestionsLimits::limitForUser($user);
        $remaining = SearchSuggestionsLimits::remainingForUser($user);
        $historyLimit = SearchSuggestionsLimits::historyLimitForUser($user);
        $canSaveHistory = SearchSuggestionsLimits::canSaveHistory($user);
        $savedCount = SearchSuggestionsLimits::savedCount($user);
        $histories = [];

        if ($canSaveHistory) {
            $histories = SearchSuggestionsHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit((int) ($historyLimit ?: 50))
                ->get(['id', 'title', 'params', 'seeds_count', 'results_count', 'cost', 'created_at']);
        }

        return view('pages.search-suggestions', compact(
            'defaultRegion',
            'googleDomains',
            'googleCountries',
            'defaultGoogleDomain',
            'defaultGoogleGl',
            'limit',
            'remaining',
            'historyLimit',
            'canSaveHistory',
            'savedCount',
            'histories'
        ));
    }

    public function collect(Request $request, SearchSuggestionsService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        $seedsRaw = (string) $request->input('seeds', '');
        $seeds = preg_split('/\r\n|\r|\n/u', $seedsRaw) ?: [];
        $seeds = $service->normalizeSeeds($seeds);

        $engines = [];
        if ($request->boolean('yandex', true)) {
            $engines[] = 'yandex';
        }
        if ($request->boolean('google', false)) {
            $engines[] = 'google';
        }

        if ($seeds === []) {
            return response()->json(['error' => 'validation', 'message' => __('Search suggestions seeds required')], 422);
        }
        if ($engines === []) {
            return response()->json(['error' => 'validation', 'message' => __('Search suggestions engines required')], 422);
        }

        $cost = SearchSuggestionsService::estimateCost(count($seeds), count($engines));
        if (! SearchSuggestionsLimits::canSpend($cost, $user)) {
            $message = SearchSuggestionsLimits::limitMessage($user) ?: __('Search suggestions limit exhausted');

            return response()->json([
                'error' => 'limit',
                'message' => $message,
                'remaining' => SearchSuggestionsLimits::remainingForUser($user),
                'limit' => SearchSuggestionsLimits::limitForUser($user),
                'cost' => $cost,
            ], 403);
        }

        $stopRaw = (string) $request->input('stop_words', '');
        $stopWords = preg_split('/\r\n|\r|\n|,/u', $stopRaw) ?: [];

        $modes = [
            'phrase' => $request->boolean('mode_phrase', true),
            'space' => $request->boolean('mode_space', false),
            'en' => $request->boolean('mode_en', false),
            'ru' => $request->boolean('mode_ru', false),
            'digits' => $request->boolean('mode_digits', false),
        ];
        $presets = [
            'local' => $request->boolean('preset_local', false),
            'shopping' => $request->boolean('preset_shopping', false),
            'questions' => $request->boolean('preset_questions', false),
            'reviews' => $request->boolean('preset_reviews', false),
        ];

        $params = [
            'seeds' => $seeds,
            'engines' => $engines,
            'modes' => $modes,
            'presets' => $presets,
            'stop_words' => array_values(array_filter(array_map('trim', $stopWords))),
            'depth' => (int) $request->input('depth', 1),
            'yandex_lr' => (string) $request->input('yandex_lr', config('cabinet-search-suggestions.default_yandex_lr')),
            'google_domain' => (string) $request->input(
                'google_domain',
                config('cabinet-search-suggestions.default_google_domain', 'google.ru')
            ),
            'google_hl' => (string) $request->input(
                'google_hl',
                config('cabinet-search-suggestions.default_google_hl', 'ru')
            ),
            'google_gl' => (string) $request->input(
                'google_gl',
                config('cabinet-search-suggestions.default_google_gl', 'ru')
            ),
        ];

        $domains = config('cabinet-search-suggestions.google_domains', []);
        $countries = config('cabinet-search-suggestions.google_countries', []);
        $domainKey = $params['google_domain'];
        $gl = strtolower(preg_replace('/[^a-z]/i', '', $params['google_gl']) ?? '');
        if ($gl === '' || ! isset($countries[$gl])) {
            $gl = (string) ($domains[$domainKey]['gl'] ?? config('cabinet-search-suggestions.default_google_gl', 'ru'));
        }
        $hl = (string) ($countries[$gl]['hl'] ?? $domains[$domainKey]['hl'] ?? $params['google_hl'] ?: 'ru');
        $params['google_gl'] = $gl;
        $params['google_hl'] = $hl;
        if (! isset($domains[$domainKey])) {
            $params['google_domain'] = (string) config('cabinet-search-suggestions.default_google_domain', 'google.ru');
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $collected = $service->collect($params);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'fetch_failed',
                'message' => __('Search suggestions fetch failed'),
            ], 502);
        }

        SearchSuggestionsLimits::spend($collected['cost'], $user);

        $historyId = null;
        $save = $request->boolean('save', false);
        if ($save && SearchSuggestionsLimits::canSaveHistory($user)) {
            if (! SearchSuggestionsLimits::canSaveAnother($user)) {
                // Сбор уже выполнен и списан — возвращаем результат с предупреждением.
                return response()->json([
                    'ok' => true,
                    'cost' => $collected['cost'],
                    'requests' => $collected['requests'],
                    'truncated' => $collected['truncated'],
                    'results' => $collected['results'],
                    'remaining' => SearchSuggestionsLimits::remainingForUser($user),
                    'limit' => SearchSuggestionsLimits::limitForUser($user),
                    'history_id' => null,
                    'history_warning' => SearchSuggestionsLimits::historyLimitMessage($user)
                        ?: __('Search suggestions history limit exhausted'),
                    'saved_count' => SearchSuggestionsLimits::savedCount($user),
                    'history_limit' => SearchSuggestionsLimits::historyLimitForUser($user),
                ]);
            }

            $title = $seeds[0] ?? __('Search suggestions');
            if (count($seeds) > 1) {
                $title .= ' +' . (count($seeds) - 1);
            }

            $history = SearchSuggestionsHistory::query()->create([
                'user_id' => $user->id,
                'title' => mb_substr($title, 0, 255),
                'params' => $params,
                'results' => $collected['results'],
                'seeds_count' => count($seeds),
                'results_count' => count($collected['results']),
                'cost' => $collected['cost'],
            ]);
            $historyId = $history->id;
        }

        return response()->json([
            'ok' => true,
            'cost' => $collected['cost'],
            'requests' => $collected['requests'],
            'truncated' => $collected['truncated'],
            'results' => $collected['results'],
            'remaining' => SearchSuggestionsLimits::remainingForUser($user),
            'limit' => SearchSuggestionsLimits::limitForUser($user),
            'history_id' => $historyId,
            'saved_count' => SearchSuggestionsLimits::savedCount($user),
            'history_limit' => SearchSuggestionsLimits::historyLimitForUser($user),
            'can_save_history' => SearchSuggestionsLimits::canSaveHistory($user),
        ]);
    }

    public function historyShow(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! SearchSuggestionsLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Search suggestions history paid only')], 403);
        }

        $row = SearchSuggestionsHistory::query()
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
                'seeds_count' => $row->seeds_count,
                'results_count' => $row->results_count,
                'cost' => $row->cost,
                'created_at' => optional($row->created_at)->format('d.m.Y H:i'),
            ],
        ]);
    }

    public function historyDestroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! SearchSuggestionsLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Search suggestions history paid only')], 403);
        }

        $deleted = SearchSuggestionsHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return response()->json([
            'ok' => (bool) $deleted,
            'saved_count' => SearchSuggestionsLimits::savedCount($user),
            'history_limit' => SearchSuggestionsLimits::historyLimitForUser($user),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $payload = $request->input('results');
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (! is_array($payload)) {
            $payload = [];
        }

        $filename = 'search-suggestions-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [
                __('Search suggestions col seed'),
                __('Search suggestions col query'),
                __('Search suggestions col suggest'),
                __('Search suggestions col engine'),
                __('Search suggestions col level'),
                __('Search suggestions col words'),
                __('Search suggestions col type'),
            ], ';');

            foreach ($payload as $row) {
                if (! is_array($row)) {
                    continue;
                }
                fputcsv($out, [
                    $row['seed'] ?? '',
                    $row['query'] ?? '',
                    $row['suggest'] ?? '',
                    $row['engine'] ?? '',
                    $row['level'] ?? '',
                    $row['words'] ?? '',
                    $this->typeLabelRu((string) ($row['type'] ?? '')),
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function typeLabelRu(string $type): string
    {
        $map = [
            'exact' => 'точное',
            'append' => 'дополнение',
            'contains' => 'вхождение',
            'reorder' => 'перестановка',
            'prefix' => 'в начале',
            'suggest' => 'подсказка',
        ];

        return $map[$type] ?? $type;
    }
}
