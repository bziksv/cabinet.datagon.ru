<?php

namespace App\Services\Finance;

use App\PromoCodeFailedAttempt;
use App\PromoCodeUserLock;
use App\User;
use Illuminate\Support\Carbon;

class PromoCodeRateLimitService
{
    public const MAX_FAILED_ATTEMPTS = 10;
    public const WINDOW_MINUTES = 60;
    public const LOCK_DAYS = 7;

    /**
     * @return array{
     *     locked: bool,
     *     locked_until: ?string,
     *     locked_until_human: ?string,
     *     failed_in_window: int,
     *     attempts_left: int
     * }
     */
    public function statusForUser(User $user): array
    {
        $this->releaseExpiredLock($user);

        $failedInWindow = $this->failedAttemptsInWindow((int) $user->id);
        $lock = PromoCodeUserLock::query()->find((int) $user->id);

        if ($lock !== null && $lock->locked_until->isFuture()) {
            return [
                'locked' => true,
                'locked_until' => $lock->locked_until->toIso8601String(),
                'locked_until_human' => $lock->locked_until->format('d.m.Y H:i'),
                'failed_in_window' => $failedInWindow,
                'attempts_left' => 0,
            ];
        }

        $left = max(0, self::MAX_FAILED_ATTEMPTS - $failedInWindow);

        return [
            'locked' => false,
            'locked_until' => null,
            'locked_until_human' => null,
            'failed_in_window' => $failedInWindow,
            'attempts_left' => $left,
        ];
    }

    public function lockMessage(User $user): ?string
    {
        $status = $this->statusForUser($user);
        if (!$status['locked']) {
            return null;
        }

        return __('Promo lock active', [
            'until' => $status['locked_until_human'],
        ]);
    }

    public function isLocked(User $user): bool
    {
        return $this->statusForUser($user)['locked'];
    }

    public function recordFailedAttempt(User $user, string $rawCode): void
    {
        if ($this->isLocked($user)) {
            return;
        }

        PromoCodeFailedAttempt::query()->create([
            'user_id' => $user->id,
            'code' => $this->normalizeCodeForLog($rawCode),
            'created_at' => now(),
        ]);

        if ($this->failedAttemptsInWindow((int) $user->id) >= self::MAX_FAILED_ATTEMPTS) {
            PromoCodeUserLock::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['locked_until' => Carbon::now()->addDays(self::LOCK_DAYS)]
            );
        }
    }

    public function clearFailures(User $user): void
    {
        PromoCodeFailedAttempt::query()->where('user_id', $user->id)->delete();
    }

    private function releaseExpiredLock(User $user): void
    {
        $lock = PromoCodeUserLock::query()->find((int) $user->id);
        if ($lock === null) {
            return;
        }

        if ($lock->locked_until->isFuture()) {
            return;
        }

        $lock->delete();
        PromoCodeFailedAttempt::query()->where('user_id', $user->id)->delete();
    }

    private function failedAttemptsInWindow(int $userId): int
    {
        return PromoCodeFailedAttempt::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::WINDOW_MINUTES))
            ->count();
    }

    private function normalizeCodeForLog(string $code): ?string
    {
        $code = mb_strtoupper(trim($code));
        $code = preg_replace('/\s+/u', '', $code) ?? '';

        return $code !== '' ? mb_substr($code, 0, 64) : null;
    }
}
