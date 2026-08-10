<?php

namespace App\Http\Controllers;

use App\ClickTracking;
use App\HomeUserArchivedSite;
use App\HomeUserSitesPreference;
use App\Support\HomeDashboard;
use App\Support\HomeModuleItemCounts;
use App\Support\HomeUserSites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        // Тяжёлые куски (матрица сайтов, count'ы модулей, due, Метрика) — фоном:
        // локально БД часто удалённая, десятки последовательных запросов = секунды TTFB.
        return view('home-cards-v2', $this->dashboardViewData(false, false));
    }

    /** @deprecated Старые макеты главной — редирект на основную. */
    public function variant2()
    {
        return redirect()->route('home');
    }

    /** @deprecated Старые макеты главной — редирект на основную. */
    public function variant3()
    {
        return redirect()->route('home');
    }

    /** @deprecated Алиас бывшего /home/variant-4 — редирект на основную. */
    public function variant4()
    {
        return redirect()->route('home');
    }

    protected function dashboardViewData(bool $withItemCounts = false, bool $withUserSites = false): array
    {
        $modules = HomeDashboard::modules();
        if ($withItemCounts) {
            $modules = HomeModuleItemCounts::enrich($modules);
        }

        $data = [
            'summary' => HomeDashboard::summary(),
            'modules' => $modules,
            'featuredModules' => array_slice($modules, 0, 2),
            'listModules' => array_slice($modules, 2),
            'seoChecklistDue' => ['count' => 0, 'overdue' => 0, 'soon' => 0, 'items' => collect(), 'deferred' => true],
            'userSites' => [
                'sites' => [],
                'archived' => [],
                'hidden' => [],
                'total' => 0,
                'archived_total' => 0,
                'hidden_total' => 0,
                'shown' => 0,
                'catalog' => [],
                'modules_total' => 0,
                'deferred' => true,
                'visits_deferred' => true,
            ],
            'moduleCountsDeferred' => ! $withItemCounts,
        ];

        if ($withUserSites) {
            // Визиты Метрики — отдельным AJAX: API может висеть десятки секунд.
            $data['userSites'] = HomeUserSites::forCurrentUser(false);
            $data['userSites']['deferred'] = false;
        }

        if ($withItemCounts) {
            $data['moduleCountsDeferred'] = false;
        }

        return $data;
    }

    /**
     * HTML-фрагмент таблицы сайтов (без визитов Метрики).
     */
    public function sitesFragment(): \Illuminate\Http\Response
    {
        if (! Auth::check()) {
            abort(401);
        }

        $userSites = HomeUserSites::forCurrentUser(false);
        $userSites['deferred'] = false;
        $html = view('home-cards-v2.partials.sites', ['userSites' => $userSites])->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Счётчики проектов/сохранений на карточках модулей.
     */
    public function moduleCounts(): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $counts = HomeModuleItemCounts::countsForUser($userId);
        $labels = [
            'projects' => [
                'empty' => __('No projects yet'),
                'choice' => 'home.cards_v2.projects',
            ],
            'saved' => [
                'empty' => __('No saved items yet'),
                'choice' => 'home.cards_v2.saved_items',
            ],
        ];

        $out = [];
        foreach ($counts as $key => $row) {
            $kind = (string) ($row['kind'] ?? 'projects');
            $count = (int) ($row['count'] ?? 0);
            $pack = $labels[$kind] ?? $labels['projects'];
            $out[$key] = [
                'count' => $count,
                'kind' => $kind,
                'empty_label' => $pack['empty'],
                'count_label' => trans_choice($pack['choice'], $count),
            ];
        }

        return response()->json(['ok' => true, 'counts' => $out]);
    }

    /**
     * Блок дедлайнов чеклиста.
     */
    public function seoChecklistDueFragment(): \Illuminate\Http\Response
    {
        if (! Auth::check()) {
            abort(401);
        }

        $html = view('home.partials.seo-checklist-due', [
            'seoChecklistDue' => $this->seoChecklistDueData(),
        ])->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Фоновая догрузка посещаемости Яндекс.Метрики для таблицы сайтов на главной.
     */
    public function sitesVisits(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $domains = $request->input('domains');
        if (! is_array($domains)) {
            $domains = null;
        }

        $loaded = HomeUserSites::visitsForUser($userId, $domains);

        return response()->json([
            'ok' => true,
            'by_domain' => $loaded['by_domain'],
            'meta' => $loaded['meta'],
        ]);
    }

    /**
     * @return array{count:int,overdue:int,soon:int,items:\Illuminate\Support\Collection}
     */
    protected function seoChecklistDueData(): array
    {
        $empty = ['count' => 0, 'overdue' => 0, 'soon' => 0, 'items' => collect()];
        $userId = Auth::id();
        if (!$userId || !\App\SeoChecklist\SeoChecklistProject::tableReady()) {
            return $empty;
        }

        try {
            return app(\App\Services\SeoChecklist\SeoChecklistService::class)
                ->dueAlertsForUser((int) $userId);
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    public function archiveUserSite(Request $request): JsonResponse
    {
        return $this->moveUserSite($request, HomeUserArchivedSite::KIND_ARCHIVED, 'archive');
    }

    public function hideUserSite(Request $request): JsonResponse
    {
        return $this->moveUserSite($request, HomeUserArchivedSite::KIND_HIDDEN, 'hide');
    }

    public function restoreUserSite(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!HomeUserArchivedSite::tableReady()) {
            return response()->json(['ok' => false, 'message' => __('Sites archive unavailable')], 503);
        }

        $domain = HomeUserArchivedSite::restoreForUser($userId, (string) $request->input('domain', ''));
        if ($domain === null) {
            return response()->json(['ok' => false, 'message' => __('Invalid domain')], 422);
        }

        HomeUserSites::forgetSitesCache($userId);

        return response()->json([
            'ok' => true,
            'domain' => $domain,
            'action' => 'restore',
            'archived_total' => count(HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_ARCHIVED)),
            'hidden_total' => count(HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_HIDDEN)),
        ]);
    }

    public function saveSitesColumns(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $columns = $request->input('columns', []);
        if (!is_array($columns)) {
            return response()->json(['ok' => false, 'message' => __('Invalid request')], 422);
        }

        $saved = HomeUserSitesPreference::saveColumns($userId, $columns);

        return response()->json(['ok' => true, 'columns' => $saved]);
    }

    private function moveUserSite(Request $request, string $kind, string $action): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!HomeUserArchivedSite::tableReady()) {
            return response()->json(['ok' => false, 'message' => __('Sites archive unavailable')], 503);
        }

        $domain = HomeUserArchivedSite::setForUser($userId, (string) $request->input('domain', ''), $kind);
        if ($domain === null) {
            return response()->json(['ok' => false, 'message' => __('Invalid domain')], 422);
        }

        HomeUserSites::forgetSitesCache($userId);

        return response()->json([
            'ok' => true,
            'domain' => $domain,
            'action' => $action,
            'archived_total' => count(HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_ARCHIVED)),
            'hidden_total' => count(HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_HIDDEN)),
        ]);
    }

    public function clickTracking(Request $request): JsonResponse
    {
        try {
            ClickTracking::updateOrCreate([
                'project_id' => $request->project_id,
                'button_text' => $request->button_text,
                'url' => preg_replace('/[0-9#]+/', '', $request->url),
                'user_id' => Auth::id(),
            ], [
                'button_counter' => DB::raw('button_counter + 1'),
            ]);
        } catch (\Throwable $e) {
            Log::debug('click tracking error', [
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([], 201);
    }
}
