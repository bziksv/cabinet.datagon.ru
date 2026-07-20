<?php

namespace App\Http\Controllers;

use App\PhraseCommerceHistory;
use App\Services\PhraseCommerceService;
use App\Support\CompetitorSearchRegions;
use App\Support\DemoCabinet;
use App\Support\PhraseCommerceLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhraseCommerceController extends Controller
{
    /**
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        if (DemoCabinet::isCurrentUser() && ! $request->filled('history')) {
            $showcase = DemoCabinet::phraseCommerceShowcasePath();
            if ($showcase) {
                return redirect($showcase);
            }
        }

        $user = Auth::user();
        $defaultYandex = CompetitorSearchRegions::defaultRegion('yandex');
        $defaultGoogle = CompetitorSearchRegions::defaultRegion('google');
        $costYandex = PhraseCommerceService::costPerPhraseForEngine('yandex');
        $costGoogle = PhraseCommerceService::costPerPhraseForEngine('google');

        $limit = PhraseCommerceLimits::limitForUser($user);
        $remaining = PhraseCommerceLimits::remainingForUser($user);
        $historyLimit = PhraseCommerceLimits::historyLimitForUser($user);
        $canSaveHistory = PhraseCommerceLimits::canSaveHistory($user);
        $savedCount = PhraseCommerceLimits::savedCount($user);
        $histories = [];

        if ($canSaveHistory) {
            $histories = PhraseCommerceHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit((int) ($historyLimit ?: 50))
                ->get(['id', 'title', 'params', 'phrases_count', 'results_count', 'cost', 'created_at']);
        }

        return view('pages.phrase-commerce', compact(
            'defaultYandex',
            'defaultGoogle',
            'costYandex',
            'costGoogle',
            'limit',
            'remaining',
            'historyLimit',
            'canSaveHistory',
            'savedCount',
            'histories'
        ));
    }

    public function analyze(Request $request, PhraseCommerceService $service): JsonResponse
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
            return response()->json(['error' => 'validation', 'message' => __('Phrase commerce phrases required')], 422);
        }
        if ($engines === []) {
            return response()->json(['error' => 'validation', 'message' => __('Phrase commerce engines required')], 422);
        }

        $cost = PhraseCommerceService::estimateCost(count($phrases), $engines);
        if (! PhraseCommerceLimits::canSpend($cost, $user)) {
            return response()->json([
                'error' => 'limit',
                'message' => PhraseCommerceLimits::limitMessage($user) ?: __('Phrase commerce limit exhausted'),
                'remaining' => PhraseCommerceLimits::remainingForUser($user),
                'limit' => PhraseCommerceLimits::limitForUser($user),
            ], 403);
        }

        $params = [
            'phrases' => $phrases,
            'engines' => $engines,
            'yandex_lr' => (string) $request->input('yandex_lr', config('cabinet-phrase-commerce.default_yandex_lr', '213')),
            'google_lr' => (string) $request->input('google_lr', config('cabinet-phrase-commerce.default_google_lr', '1011969')),
            'yandex_lr2' => (string) $request->input('yandex_lr2', ''),
            'google_lr2' => (string) $request->input('google_lr2', ''),
        ];

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        try {
            $result = $service->analyze($params);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'fetch_failed',
                'message' => __('Phrase commerce fetch failed'),
            ], 502);
        }

        PhraseCommerceLimits::spend($result['cost'], $user);

        $historyId = null;
        $historyWarning = null;
        $save = $request->boolean('save', false);
        if ($save && PhraseCommerceLimits::canSaveHistory($user)) {
            if (! PhraseCommerceLimits::canSaveAnother($user)) {
                $historyWarning = PhraseCommerceLimits::historyLimitMessage($user)
                    ?: __('Phrase commerce history limit exhausted');
            } else {
                $title = $phrases[0] ?? __('Phrase commerce');
                if (count($phrases) > 1) {
                    $title .= ' +' . (count($phrases) - 1);
                }

                $history = PhraseCommerceHistory::query()->create([
                    'user_id' => $user->id,
                    'title' => mb_substr($title, 0, 255),
                    'params' => $params,
                    'results' => [
                        'summary' => $result['summary'],
                        'rows' => $result['rows'],
                        'depth' => $result['depth'],
                    ],
                    'phrases_count' => count($phrases),
                    'results_count' => count($result['rows'] ?? []),
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
            'rows' => $result['rows'],
            'contrast_regions' => $result['contrast_regions'] ?? [],
            'remaining' => PhraseCommerceLimits::remainingForUser($user),
            'limit' => PhraseCommerceLimits::limitForUser($user),
            'history_id' => $historyId,
            'history_warning' => $historyWarning,
            'saved_count' => PhraseCommerceLimits::savedCount($user),
            'history_limit' => PhraseCommerceLimits::historyLimitForUser($user),
            'can_save_history' => PhraseCommerceLimits::canSaveHistory($user),
        ]);
    }

    /**
     * Сохранить уже собранный результат (после пофразового анализа на клиенте).
     */
    public function historyStore(Request $request, PhraseCommerceService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! PhraseCommerceLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Phrase commerce history paid only')], 403);
        }
        if (! PhraseCommerceLimits::canSaveAnother($user)) {
            return response()->json([
                'error' => 'limit',
                'message' => PhraseCommerceLimits::historyLimitMessage($user)
                    ?: __('Phrase commerce history limit exhausted'),
            ], 403);
        }

        $phrases = $request->input('phrases', []);
        if (is_string($phrases)) {
            $phrases = preg_split('/\r\n|\r|\n/u', $phrases) ?: [];
        }
        if (! is_array($phrases)) {
            $phrases = [];
        }
        $phrases = $service->normalizePhrases($phrases);

        $rows = $request->input('rows', []);
        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        }
        if (! is_array($rows)) {
            $rows = [];
        }

        $engines = $request->input('engines', []);
        if (is_string($engines)) {
            $engines = array_filter(array_map('trim', explode(',', $engines)));
        }
        if (! is_array($engines)) {
            $engines = [];
        }

        $cost = (int) $request->input('cost', 0);
        $summary = $request->input('summary');
        if (is_string($summary)) {
            $summary = json_decode($summary, true);
        }
        if (! is_array($summary)) {
            $summary = $service->summaryFromRows($rows);
        }

        $title = $phrases[0] ?? __('Phrase commerce');
        if (count($phrases) > 1) {
            $title .= ' +' . (count($phrases) - 1);
        }

        $params = [
            'phrases' => $phrases,
            'engines' => array_values($engines),
            'yandex_lr' => (string) $request->input('yandex_lr', config('cabinet-phrase-commerce.default_yandex_lr', '213')),
            'google_lr' => (string) $request->input('google_lr', config('cabinet-phrase-commerce.default_google_lr', '1011969')),
            'yandex_lr2' => (string) $request->input('yandex_lr2', ''),
            'google_lr2' => (string) $request->input('google_lr2', ''),
        ];

        $history = PhraseCommerceHistory::query()->create([
            'user_id' => $user->id,
            'title' => mb_substr($title, 0, 255),
            'params' => $params,
            'results' => [
                'summary' => $summary,
                'rows' => $rows,
                'depth' => (int) $request->input('depth', config('cabinet-phrase-commerce.depth', 20)),
            ],
            'phrases_count' => count($phrases),
            'results_count' => count($rows),
            'cost' => max(0, $cost),
        ]);

        return response()->json([
            'ok' => true,
            'history_id' => $history->id,
            'saved_count' => PhraseCommerceLimits::savedCount($user),
            'history_limit' => PhraseCommerceLimits::historyLimitForUser($user),
            'can_save_history' => PhraseCommerceLimits::canSaveHistory($user),
        ]);
    }

    public function historyShow(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! PhraseCommerceLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Phrase commerce history paid only')], 403);
        }

        $row = PhraseCommerceHistory::query()
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
        if (! $user || ! PhraseCommerceLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Phrase commerce history paid only')], 403);
        }

        $deleted = PhraseCommerceHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return response()->json([
            'ok' => true,
            'deleted' => (bool) $deleted,
            'saved_count' => PhraseCommerceLimits::savedCount($user),
            'history_limit' => PhraseCommerceLimits::historyLimitForUser($user),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $payload = $request->input('rows');
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (! is_array($payload)) {
            $payload = [];
        }

        $filename = 'phrase-commerce-' . date('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [
                'Фраза', 'ПС', 'Регион', 'Контрольный регион',
                'Гео', 'Пересечение %', 'Общие хосты',
                'Локализация %', 'Локализация',
                'Коммерция %', 'Коммерция',
                'ТОП основной', 'ТОП контрольный',
            ], ';');
            foreach ($payload as $row) {
                $serpPrimary = [];
                foreach ($row['serp_primary'] ?? [] as $item) {
                    $serpPrimary[] = ($item['pos'] ?? '') . '. ' . ($item['domain'] ?? $item['url'] ?? '');
                }
                $serpContrast = [];
                foreach ($row['serp_contrast'] ?? [] as $item) {
                    $serpContrast[] = ($item['pos'] ?? '') . '. ' . ($item['domain'] ?? $item['url'] ?? '');
                }
                fputcsv($out, [
                    $row['phrase'] ?? '',
                    ($row['engine'] ?? '') === 'google' ? 'Google' : 'Яндекс',
                    $row['region_name'] ?? ($row['region'] ?? ''),
                    $row['region_contrast_name'] ?? ($row['region_contrast'] ?? ''),
                    $row['geo']['label'] ?? '',
                    $row['geo']['overlap_pct'] ?? '',
                    implode(', ', $row['geo']['shared_hosts'] ?? []),
                    $row['localization']['pct'] ?? '',
                    $row['localization']['label'] ?? '',
                    $row['commerce']['pct'] ?? '',
                    $row['commerce']['label'] ?? '',
                    implode(' | ', $serpPrimary),
                    implode(' | ', $serpContrast),
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
