<?php

namespace App\Http\Controllers\Api\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\CompetitorAnalysisDemoService;
use App\Support\DemoGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class CompetitorAnalysisDemoController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $cfg = CompetitorAnalysisDemoService::config();
        $module = CompetitorAnalysisDemoService::MODULE;
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 3);

        $guest = DemoGuestSession::read($request);
        $remainingBefore = DemoGuestSession::remaining($guest['state'], $module, $maxRuns);

        if ($remainingBefore <= 0) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит демо на сегодня исчерпан. Зарегистрируйтесь для полного анализа.',
                    'remaining' => 0,
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $body = $request->json()->all();
        if (!is_array($body)) {
            $body = $request->all();
        }

        $demoInput = [
            'phrase' => $body['phrase'] ?? '',
            'region_id' => $body['region_id'] ?? '',
            'compare_region_id' => $body['compare_region_id'] ?? '',
            'search_engine' => $body['search_engine'] ?? 'yandex',
        ];

        $validated = CompetitorAnalysisDemoService::validate($demoInput);
        if (!$validated['ok']) {
            return $this->jsonError(
                $validated['status'],
                [
                    'error' => $validated['error'],
                    'message' => $validated['message'],
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $analysis = CompetitorAnalysisDemoService::analyze($validated['payload']);
        if (array_key_exists('ok', $analysis) && $analysis['ok'] === false) {
            return $this->jsonError(
                $analysis['status'],
                [
                    'error' => $analysis['error'],
                    'message' => $analysis['message'],
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $bump = DemoGuestSession::bump($guest['state'], $module, $maxRuns);
        if (!$bump['allowed']) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит демо на сегодня исчерпан.',
                    'remaining' => 0,
                ],
                $guest['guestId'],
                $bump['nextState'],
                $guest['isNewGuest']
            );
        }

        $payload = CompetitorAnalysisDemoService::buildResponse($analysis, $bump['remaining'], $guest['guestId']);

        return $this->attachCookies(
            response()->json($payload),
            DemoGuestSession::cookies($guest['guestId'], $bump['nextState'], $guest['isNewGuest'])
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, array{count: int, day: string}> $runState
     */
    private function jsonError(int $status, array $body, string $guestId, array $runState, bool $setGuest): JsonResponse
    {
        return $this->attachCookies(
            response()->json($body, $status),
            DemoGuestSession::cookies($guestId, $runState, $setGuest)
        );
    }

    /**
     * @param Cookie[] $cookies
     */
    private function attachCookies(JsonResponse $response, array $cookies): JsonResponse
    {
        foreach ($cookies as $cookie) {
            $response = $response->withCookie($cookie);
        }

        return $response;
    }
}
