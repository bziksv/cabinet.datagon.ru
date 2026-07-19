<?php

namespace App\Support;

use App\TextUniquenessHistory;
use App\TextUniquenessUsage;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TextUniquenessLimits
{
    public static function periodKey(?Carbon $at = null): string
    {
        $at = $at ?? Carbon::now();

        return $at->format('Y-m');
    }

    public static function limitForUser(?User $user = null): ?int
    {
        return self::tariffInt('TextUniqueness', $user);
    }

    public static function historyLimitForUser(?User $user = null): ?int
    {
        return self::tariffInt('TextUniquenessHistory', $user);
    }

    public static function canSaveHistory(?User $user = null): bool
    {
        $limit = self::historyLimitForUser($user);

        return $limit !== null && $limit > 0;
    }

    public static function usedForUser(?User $user = null, ?string $period = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) TextUniquenessUsage::query()
            ->where('user_id', $user->id)
            ->where('period', $period ?? self::periodKey())
            ->value('used');
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

        return self::usedForUser($user) + $cost <= $limit;
    }

    public static function spend(int $cost, ?User $user = null): void
    {
        if ($cost <= 0) {
            return;
        }

        $user = $user ?? Auth::user();
        if (! $user) {
            return;
        }

        $period = self::periodKey();
        $row = TextUniquenessUsage::query()->firstOrCreate(
            ['user_id' => $user->id, 'period' => $period],
            ['used' => 0]
        );

        $row->used = (int) $row->used + $cost;
        $row->save();
    }

    public static function limitMessage(?User $user = null): ?string
    {
        return self::tariffMessage('TextUniqueness', $user);
    }

    public static function historyLimitMessage(?User $user = null): ?string
    {
        return self::tariffMessage('TextUniquenessHistory', $user);
    }

    public static function savedCount(?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) TextUniquenessHistory::query()->where('user_id', $user->id)->count();
    }

    public static function canSaveAnother(?User $user = null): bool
    {
        $limit = self::historyLimitForUser($user);
        if ($limit === null) {
            return true;
        }
        if ($limit <= 0) {
            return false;
        }

        return self::savedCount($user) < $limit;
    }

    private static function tariffInt(string $code, ?User $user = null): ?int
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
        if (! array_key_exists($code, $settings)) {
            return null;
        }

        return (int) $settings[$code]['value'];
    }

    private static function tariffMessage(string $code, ?User $user = null): ?string
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

        return $settings[$code]['message'] ?? null;
    }
}
