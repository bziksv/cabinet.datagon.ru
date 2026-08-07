<?php

namespace App\Support;

use App\SiteAuditCrawl;
use App\SiteAuditProject;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteAuditLimits
{
    /** @var array<int, array|null> */
    private static $settingsByUser = [];

    /** Дефолты, если в тарифе нет настройки (должны совпадать с миграцией сетки). */
    private const TIER_DEFAULTS = [
        'Free' => ['pages' => 100, 'concurrency' => 1, 'projects' => 1, 'schedules' => 0],
        'Optimal' => ['pages' => 1000, 'concurrency' => 2, 'projects' => 20, 'schedules' => 2],
        'Ultimate' => ['pages' => 10000, 'concurrency' => 4, 'projects' => 50, 'schedules' => 5],
        'Maximum' => ['pages' => 100000, 'concurrency' => 8, 'projects' => 100, 'schedules' => 10],
    ];

    public static function periodKey(?Carbon $at = null): string
    {
        return ($at ?? Carbon::now())->format('Y-m');
    }

    public static function tierCode(?User $user = null): string
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 'Free';
        }

        try {
            $tariff = $user->tariff();
            if ($tariff && method_exists($tariff, 'code')) {
                $code = (string) $tariff->code();
                if (isset(self::TIER_DEFAULTS[$code])) {
                    return $code;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $user->hasPaidTariffRole() ? 'Optimal' : 'Free';
    }

    public static function pagesPerCrawlLimit(?User $user = null): int
    {
        $fromTariff = self::settingValue('SiteAudit', $user);
        if ($fromTariff !== null && $fromTariff > 0) {
            return $fromTariff;
        }

        $tier = self::tierCode($user);

        return (int) (self::TIER_DEFAULTS[$tier]['pages'] ?? 100);
    }

    public static function concurrencyLimit(?User $user = null): int
    {
        $fromTariff = self::settingValue('SiteAuditConcurrency', $user);
        if ($fromTariff !== null && $fromTariff > 0) {
            return max(1, min((int) config('site_audit.max_concurrency', 8), $fromTariff));
        }

        $tier = self::tierCode($user);
        $def = (int) (self::TIER_DEFAULTS[$tier]['concurrency'] ?? 1);

        return max(1, min((int) config('site_audit.max_concurrency', 8), $def));
    }

    public static function projectsLimit(?User $user = null): int
    {
        $fromTariff = self::settingValue('SiteAuditProjects', $user);
        if ($fromTariff !== null && $fromTariff > 0) {
            return $fromTariff;
        }

        $tier = self::tierCode($user);

        return (int) (self::TIER_DEFAULTS[$tier]['projects'] ?? 1);
    }

    public static function schedulesLimit(?User $user = null): int
    {
        $fromTariff = self::settingValue('SiteAuditSchedules', $user);
        if ($fromTariff !== null) {
            return max(0, $fromTariff);
        }

        $tier = self::tierCode($user);

        return (int) (self::TIER_DEFAULTS[$tier]['schedules'] ?? 0);
    }

    public static function schedulesUsed(?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) \App\SiteAuditSchedule::query()
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->count();
    }

    /**
     * Можно ли включить авторасписание для проекта (уже включённое — всегда можно сохранить).
     */
    public static function canEnableSchedule(User $user, ?int $projectId = null): bool
    {
        $limit = self::schedulesLimit($user);
        if ($limit < 1) {
            return false;
        }

        $q = \App\SiteAuditSchedule::query()
            ->where('user_id', $user->id)
            ->where('enabled', true);
        if ($projectId) {
            $q->where('project_id', '!=', $projectId);
        }

        return $q->count() < $limit;
    }

    public static function projectsUsed(?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) SiteAuditProject::query()->where('user_id', $user->id)->count();
    }

    public static function canCreateProject(User $user, string $domain): bool
    {
        $domain = preg_replace('#^https?://#i', '', trim($domain));
        $domain = rtrim((string) $domain, '/');
        $exists = SiteAuditProject::query()
            ->where('user_id', $user->id)
            ->where('domain', $domain)
            ->exists();
        if ($exists) {
            return true;
        }

        return self::projectsUsed($user) < self::projectsLimit($user);
    }

    /**
     * Запрошенный лимит URL, обрезанный сверху тарифом (и снизу 1).
     */
    public static function resolvePagesLimit(?User $user, $requested): int
    {
        $max = self::pagesPerCrawlLimit($user);
        $requested = self::parseIntLoose($requested);
        if ($requested < 1) {
            return $max;
        }

        return max(1, min($max, $requested));
    }

    public static function resolveConcurrency(?User $user, $requested): int
    {
        $max = self::concurrencyLimit($user);
        $requested = self::parseIntLoose($requested);
        if ($requested < 1) {
            return 1;
        }

        return max(1, min($max, $requested));
    }

    /**
     * Целое из строки с пробелами тысяч («100 000») или сырого int.
     */
    public static function parseIntLoose($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        $s = preg_replace('/[\s\x{00a0}\x{202f}]+/u', '', (string) $value);

        return (int) $s;
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

    public static function everHadPaidTariff(User $user): bool
    {
        if (! Schema::hasTable('tariff_pays')) {
            return false;
        }

        return DB::table('tariff_pays')
            ->where('user_id', $user->id)
            ->where('class_tariff', 'not like', '%FreeTariff%')
            ->exists();
    }

    /**
     * Обновить метку «стал бесплатным» при визите / кроне.
     */
    public static function touchDowngradeState(User $user): void
    {
        if (! Schema::hasTable('site_audit_user_state')) {
            return;
        }

        if ($user->hasPaidTariffRole()) {
            DB::table('site_audit_user_state')->where('user_id', $user->id)->delete();

            return;
        }

        if (! $user->onFreeTariff() || ! self::everHadPaidTariff($user)) {
            return;
        }

        $exists = DB::table('site_audit_user_state')->where('user_id', $user->id)->first();
        if ($exists && $exists->became_free_at) {
            return;
        }

        $now = now();
        if ($exists) {
            DB::table('site_audit_user_state')->where('user_id', $user->id)->update([
                'became_free_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('site_audit_user_state')->insert([
                'user_id' => $user->id,
                'became_free_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array{show:bool,days_left:?int,purge_at:?string}|null
     */
    public static function historyPurgeNotice(?User $user = null): ?array
    {
        $user = $user ?? Auth::user();
        if (! $user || ! Schema::hasTable('site_audit_user_state')) {
            return null;
        }

        if ($user->hasPaidTariffRole() || ! $user->onFreeTariff() || ! self::everHadPaidTariff($user)) {
            return null;
        }

        self::touchDowngradeState($user);
        $row = DB::table('site_audit_user_state')->where('user_id', $user->id)->first();
        if (! $row || ! $row->became_free_at) {
            return null;
        }

        $became = Carbon::parse($row->became_free_at);
        $purgeAt = $became->copy()->addDays((int) config('site_audit.free_history_keep_days', 14));
        $daysLeft = $purgeAt->isPast() ? 0 : (int) Carbon::now()->diffInDays($purgeAt);

        return [
            'show' => true,
            'days_left' => $daysLeft,
            'purge_at' => $purgeAt->format('d.m.Y'),
            'became_free_at' => $became->format('d.m.Y'),
        ];
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
