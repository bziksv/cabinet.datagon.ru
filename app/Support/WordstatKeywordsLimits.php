<?php

namespace App\Support;

use App\User;
use App\Wordstat\WordstatRequestLog;
use Illuminate\Support\Facades\Auth;

class WordstatKeywordsLimits
{
    public const CODE = 'WordstatKeywords';

    public static function limitForUser(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return null;
        }

        $tariff = $user->tariff();
        if (! $tariff) {
            return null;
        }

        $settings = $tariff->getAsArray()['settings'] ?? [];
        if (! array_key_exists(self::CODE, $settings)) {
            return null;
        }

        $value = (int) $settings[self::CODE]['value'];
        if ($value >= ModuleTariffLimit::UNLIMITED_CAP) {
            return null;
        }

        return max(0, $value);
    }

    public static function usedForUser(?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return self::usedForUserId((int) $user->id);
    }

    public static function usedForUserId(int $userId): int
    {
        return (int) WordstatRequestLog::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public static function remainingForUser(?User $user = null): ?int
    {
        $limit = self::limitForUser($user);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - self::usedForUser($user));
    }

    public static function canSpend(int $cost, ?User $user = null): bool
    {
        $limit = self::limitForUser($user);
        if ($limit === null) {
            return true;
        }

        return self::usedForUser($user) + max(0, $cost) <= $limit;
    }

    public static function limitMessage(?User $user = null): string
    {
        $user = $user ?? Auth::user();
        $limit = self::limitForUser($user);
        $formatted = $limit !== null ? number_format($limit, 0, '', ' ') : '';

        if ($user && ($tariff = $user->tariff())) {
            $settings = $tariff->getAsArray()['settings'] ?? [];
            $msg = trim((string) ($settings[self::CODE]['message'] ?? ''));
            if ($msg !== '') {
                return str_replace('{VALUE}', $formatted, $msg);
            }
        }

        return __('Лимит запросов Wordstat на этот месяц исчерпан (:limit). Повысьте тариф или дождитесь нового месяца.', ['limit' => $formatted]);
    }
}
