<?php

namespace App\Http\Controllers\Api\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\IndexCheckDemoService;
use App\Support\DemoGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class IndexCheckDemoController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $cfg = IndexCheckDemoService::config();
        $module = IndexCheckDemoService::MODULE;
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 5);

        $guest = DemoGuestSession::read($request);
        $remainingBefore = DemoGuestSession::remaining($guest['state'], $module, $maxRuns);

        if ($remainingBefore <= 0) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит демо-проверок на сегодня исчерпан. Зарегистрируйтесь — пакетная проверка и лимиты по тарифу.',
                    'remaining' => 0,
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $body = $request->json()->all();
        if (! is_array($body)) {
            $body = $request->all();
        }

        $validated = IndexCheckDemoService::validate([
            'url' => $body['url'] ?? '',
            'yandex' => $body['yandex'] ?? true,
            'google' => $body['google'] ?? true,
            'unify_www' => $body['unify_www'] ?? false,
            'google_domain' => $body['google_domain'] ?? 'google.ru',
        ]);

        if (! ($validated['ok'] ?? false)) {
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

        try {
            $result = IndexCheckDemoService::check($validated);
        } catch (\Throwable $e) {
            return $this->jsonError(
                502,
                [
                    'error' => 'fetch_failed',
                    'message' => 'Не удалось выполнить проверку индексации. Попробуйте позже.',
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $bump = DemoGuestSession::bump($guest['state'], $module, $maxRuns);
        if (! $bump['allowed']) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит демо-проверок на сегодня исчерпан.',
                    'remaining' => 0,
                ],
                $guest['guestId'],
                $bump['nextState'],
                $guest['isNewGuest']
            );
        }

        $payload = IndexCheckDemoService::buildResponse($result, $bump['remaining'], $guest['guestId']);

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
