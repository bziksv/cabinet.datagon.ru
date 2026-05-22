<?php

namespace App\Http\Controllers;

use App\ClickTracking;
use App\Support\HomeDashboard;
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

    protected function dashboardViewData(): array
    {
        $modules = HomeDashboard::modules();

        return [
            'summary' => HomeDashboard::summary(),
            'modules' => $modules,
            'featuredModules' => array_slice($modules, 0, 2),
            'listModules' => array_slice($modules, 2),
        ];
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
