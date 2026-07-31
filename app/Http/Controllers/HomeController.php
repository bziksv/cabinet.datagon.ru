<?php

namespace App\Http\Controllers;

use App\ClickTracking;
use App\HomeUserArchivedSite;
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

        return view('home', $this->dashboardViewData());
    }

    /**
     * Альтернативный макет главной (bento + список модулей).
     */
    public function variant2()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        return view('home-v2', $this->dashboardViewData());
    }

    /**
     * Вариант 3: KPI-полоса + сетка иконок (app hub).
     */
    public function variant3()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        return view('home-v3', $this->dashboardViewData());
    }

    /**
     * Карточки v2: счётчики проектов/сохранений (не дефолт).
     */
    public function variant4()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        return view('home-cards-v2', $this->dashboardViewData(true, true));
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
            ],
        ];

        if ($withUserSites) {
            $data['userSites'] = HomeUserSites::forCurrentUser();
        }

        return $data;
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

        return response()->json([
            'ok' => true,
            'domain' => $domain,
            'action' => 'restore',
            'archived_total' => count(HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_ARCHIVED)),
            'hidden_total' => count(HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_HIDDEN)),
        ]);
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
