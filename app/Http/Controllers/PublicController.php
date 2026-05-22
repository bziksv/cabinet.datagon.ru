<?php

namespace App\Http\Controllers;

use App\HttpHeader;
use App\MainProject;
use App\VisitStatistic;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicController extends Controller
{
    public function httpHeaders($id, HttpHeader $header)
    {
        $lang = \request('lang', $header->lang);
        $response = $header->getData($id);
        return view('pages.headers', compact('response', 'id', 'lang'));
    }

    public function updateStatistics(Request $request): JsonResponse
    {
        $targetController = explode('@', $request->controllerAction)[0];
        $project = MainProject::Where('controller', 'like', "%" . $targetController . '@%')->first();

        if (isset($project)) {
            VisitStatistic::where('project_id', $project->id)
                ->where('user_id', Auth::id())
                ->where('date', Carbon::now()->toDateString())
                ->increment('seconds', $request->seconds);
        }

        return response()->json([], 200);
    }
}
