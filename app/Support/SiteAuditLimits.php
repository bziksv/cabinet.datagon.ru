<?php

namespace App\Support;

use App\SiteAuditCrawl;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SiteAuditLimits
{
    /** @var array<int, array|null> */
    private static $settingsByUser = [];

    public static function periodKey(?Carbon $at = null): string
    {
        return ($at ?? Carbon::now())->format('Y-m');
    }

    public static function pagesPerCrawlLimit(?User $user = null): ?int
    {
        return self::settingValue('SiteAudit', $user);
    }

    public static function crawlsPerMonthLimit(?User $user = null): ?int
    {
        return self::settingValue('SiteAuditCrawls', $user);
    }

    public static function crawlsUsedThisMonth(?User $user = null, ?string $period = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        $period = $period ?? self::periodKey();
        $from = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $to = (clone $from)->endOfMonth();

        return (int) SiteAuditCrawl::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', [SiteAuditCrawl::STATUS_FAILED])
            ->count();
    }

    public static function canStartCrawl(?User $user = null): bool
    {
        $limit = self::crawlsPerMonthLimit($user);
        if ($limit === null) {
            return true;
        }

        return self::crawlsUsedThisMonth($user) < $limit;
    }

    public static function hasActiveCrawl(?User $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        return SiteAuditCrawl::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                SiteAuditCrawl::STATUS_QUEUED,
                SiteAuditCrawl::STATUS_DISCOVERING,
                SiteAuditCrawl::STATUS_FETCHING,
                SiteAuditCrawl::STATUS_AGGREGATING,
                SiteAuditCrawl::STATUS_QUEUED_WAIT,
            ])
            ->exists();
    }

    private static function settingValue(string $code, ?User $user = null): ?int
    {
        $settings = self::settings($user);
        if ($settings === null || ! array_key_exists($code, $settings)) {
            return null;
        }

        return (int) $settings[$code]['value'];
    }

    /**
     * @return array|null null = нет пользователя/тарифа
     */
    private static function settings(?User $user = null): ?array
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return null;
        }

        $uid = (int) $user->id;
        if (array_key_exists($uid, self::$settingsByUser)) {
            return self::$settingsByUser[$uid];
        }

        $cacheKey = 'sa_tariff_settings_' . $uid;
        if (Cache::has($cacheKey)) {
            return self::$settingsByUser[$uid] = Cache::get($cacheKey);
        }

        $tariff = $user->tariff();
        $settings = $tariff ? ($tariff->getAsArray()['settings'] ?? []) : null;
        Cache::put($cacheKey, $settings, 120);

        return self::$settingsByUser[$uid] = $settings;
    }
}
