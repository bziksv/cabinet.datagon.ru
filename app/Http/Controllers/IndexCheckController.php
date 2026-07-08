<?php

namespace App\Http\Controllers;

use App\Services\IndexCheckService;
use App\Support\IndexCheckLimits;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IndexCheckController extends Controller
{
    /**
     * @return array|Factory|JsonResponse|View
     */
    public function index(Request $request)
    {
        if ($request->boolean('ajax')) {
            return $this->ajaxCheck($request);
        }

        $googleDomains = config('cabinet-index-check.google_domains', []);
        $limit = IndexCheckLimits::limitForUser();
        $remaining = IndexCheckLimits::remainingForUser();
        $costPerEngine = IndexCheckService::costPerEngine();

        return view('pages.index-check', compact('googleDomains', 'limit', 'remaining', 'costPerEngine'));
    }

    private function ajaxCheck(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        $url = trim((string) $request->input('url', ''));
        $yandex = $request->boolean('yandex', true);
        $google = $request->boolean('google', true);
        $unifyWww = $request->boolean('unify_www', false);

        if ($url === '') {
            return response()->json(['error' => 'validation', 'message' => 'Укажите URL'], 422);
        }

        if (! $yandex && ! $google) {
            return response()->json(['error' => 'validation', 'message' => 'Выберите хотя бы одну поисковую систему'], 422);
        }

        $cost = IndexCheckService::checkCost($yandex, $google);
        if (! IndexCheckLimits::canSpend($cost)) {
            $message = IndexCheckLimits::limitMessage() ?: __('Index check limit exhausted');

            return response()->json([
                'error' => 'limit',
                'message' => $message,
                'remaining' => IndexCheckLimits::remainingForUser(),
                'limit' => IndexCheckLimits::limitForUser(),
            ], 403);
        }

        $googleDomain = (string) $request->input('google_domain', 'google.ru');
        $googleDomains = config('cabinet-index-check.google_domains', []);
        $googleLr = $googleDomains[$googleDomain] ?? config('cabinet-index-check.default_google_lr', '213');

        try {
            $result = IndexCheckService::check($url, [
                'yandex' => $yandex,
                'google' => $google,
                'unify_www' => $unifyWww,
                'google_lr' => $googleLr,
                'yandex_lr' => config('cabinet-index-check.default_yandex_lr', '213'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'fetch_failed',
                'message' => 'Не удалось выполнить проверку. Попробуйте позже.',
            ], 502);
        }

        IndexCheckLimits::spend($cost);

        return response()->json([
            'ok' => true,
            'cost' => $cost,
            'remaining' => IndexCheckLimits::remainingForUser(),
            'limit' => IndexCheckLimits::limitForUser(),
            'result' => $result,
        ]);
    }
}
