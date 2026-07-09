<?php

namespace App\Http\Controllers\Api\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\EseninTextCheckDemoService;
use App\Support\DemoGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class EseninTextCheckDemoController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $cfg = EseninTextCheckDemoService::config();
        $module = EseninTextCheckDemoService::MODULE;
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 3);

        $guest = DemoGuestSession::read($request);
        $remainingBefore = DemoGuestSession::remaining($guest['state'], $module, $maxRuns);

        if ($remainingBefore <= 0) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит демо-проверок на сегодня исчерпан. Зарегистрируйтесь — HTML-редактор, автосохранение версий и лимиты по тарифу.',
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

        try {
            $validated = EseninTextCheckDemoService::validate([
                'source' => $body['source'] ?? 'text',
                'text' => $body['text'] ?? '',
                'url' => $body['url'] ?? '',
                'tbclass' => $body['tbclass'] ?? '',
                'mode' => $body['mode'] ?? 'risk',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError(
                422,
                [
                    'error' => 'validation',
                    'message' => $e->getMessage(),
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

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
            $result = EseninTextCheckDemoService::check($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError(
                422,
                [
                    'error' => 'validation',
                    'message' => $e->getMessage(),
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        } catch (\Throwable $e) {
            return $this->jsonError(
                502,
                [
                    'error' => 'fetch_failed',
                    'message' => 'Не удалось выполнить проверку. Попробуйте позже.',
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

        $payload = EseninTextCheckDemoService::buildResponse($result, $bump['remaining'], $guest['guestId']);

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
