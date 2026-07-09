<?php

namespace App\Support;

use App\EseninTextCheckUsage;
use App\Services\EseninTextCheckService;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class EseninTextCheckLimits
{
    public static function periodKey(?Carbon $at = null): string
    {
        $at = $at ?? Carbon::now();

        return $at->format('Y-m');
    }

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
        if (! array_key_exists('EseninTextCheck', $settings)) {
            return null;
        }

        return (int) $settings['EseninTextCheck']['value'];
    }

    public static function usedForUser(?User $user = null, ?string $period = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) EseninTextCheckUsage::query()
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
        $row = EseninTextCheckUsage::query()->firstOrCreate(
            ['user_id' => $user->id, 'period' => $period],
            ['used' => 0]
        );

        $row->used = (int) $row->used + $cost;
        $row->save();
    }

    public static function limitMessage(?User $user = null): ?string
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

        return $settings['EseninTextCheck']['message'] ?? null;
    }

    public static function checkCost(): int
    {
        return EseninTextCheckService::costPerCheck();
    }
}
