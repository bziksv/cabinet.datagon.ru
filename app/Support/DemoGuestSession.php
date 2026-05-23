<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Guest-лимиты демо (те же cookie, что datagon.ru/lib/demo/guest-session.ts).
 */
final class DemoGuestSession
{
    public const GUEST_COOKIE = 'datagon_demo_guest';

    public const RUNS_COOKIE = 'datagon_demo_runs';

    /**
     * @return array{guestId: string, state: array<string, array{count: int, day: string}>, isNewGuest: bool}
     */
    public static function read(Request $request): array
    {
        $guestId = (string) $request->cookie(self::GUEST_COOKIE, '');
        $isNewGuest = $guestId === '';
        if ($isNewGuest) {
            $guestId = self::newGuestId();
        }

        return [
            'guestId' => $guestId,
            'state' => self::parseRunState((string) $request->cookie(self::RUNS_COOKIE, '')),
            'isNewGuest' => $isNewGuest,
        ];
    }

    /**
     * @param array<string, array{count: int, day: string}> $state
     */
    public static function remaining(array $state, string $module, int $maxPerDay): int
    {
        $used = self::countRunsForModule($state, $module);

        return max(0, $maxPerDay - $used);
    }

    /**
     * @param array<string, array{count: int, day: string}> $state
     * @return array{nextState: array<string, array{count: int, day: string}>, remaining: int, allowed: bool}
     */
    public static function bump(array $state, string $module, int $maxPerDay): array
    {
        $day = self::todayKey();
        $prev = $state[$module] ?? null;
        $count = (!$prev || ($prev['day'] ?? '') !== $day) ? 0 : (int) ($prev['count'] ?? 0);

        if ($count >= $maxPerDay) {
            return ['nextState' => $state, 'remaining' => 0, 'allowed' => false];
        }

        $nextCount = $count + 1;
        $state[$module] = ['day' => $day, 'count' => $nextCount];

        return [
            'nextState' => $state,
            'remaining' => $maxPerDay - $nextCount,
            'allowed' => true,
        ];
    }

    /**
     * @param array<string, array{count: int, day: string}> $runState
     * @return Cookie[]
     */
    public static function cookies(string $guestId, array $runState, bool $setGuest): array
    {
        $secure = (bool) config('session.secure', false);
        $minutes = 60 * 24 * 30;
        $cookies = [];

        if ($setGuest) {
            $cookies[] = cookie(self::GUEST_COOKIE, $guestId, $minutes, '/', null, $secure, true, false, 'lax');
        }

        $cookies[] = cookie(
            self::RUNS_COOKIE,
            json_encode($runState, JSON_UNESCAPED_UNICODE),
            $minutes,
            '/',
            null,
            $secure,
            true,
            false,
            'lax'
        );

        return $cookies;
    }

    /**
     * @return array<string, array{count: int, day: string}>
     */
    private static function parseRunState(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode(urldecode($raw), true);
        if (!is_array($decoded)) {
            $decoded = json_decode($raw, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function countRunsForModule(array $state, string $module): int
    {
        $day = self::todayKey();
        $entry = $state[$module] ?? null;
        if (!$entry || ($entry['day'] ?? '') !== $day) {
            return 0;
        }

        return (int) ($entry['count'] ?? 0);
    }

    private static function todayKey(): string
    {
        return date('Y-m-d');
    }

    private static function newGuestId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
