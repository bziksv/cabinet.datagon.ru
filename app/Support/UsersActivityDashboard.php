<?php

namespace App\Support;

use App\User;
use App\VisitStatistic;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Сводка активности пользователей для /users.
 */
class UsersActivityDashboard
{
    public static function snapshot(): array
    {
        return [
            'active' => self::activeCounts(),
            'chart' => self::chartLastDays(30),
        ];
    }

    /**
     * Заглушка при SKIP_HEAVY_WEB_MIDDLEWARE (страница /users без тяжёлых запросов к visit_statistics).
     *
     * @return array{active: array<string, int>, chart: array{labels: array, values: array}}
     */
    public static function emptySnapshot(): array
    {
        return [
            'active' => [
                'today' => 0,
                'days_3' => 0,
                'days_7' => 0,
                'days_14' => 0,
                'days_30' => 0,
            ],
            'chart' => ['labels' => [], 'values' => []],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function activeCounts(): array
    {
        return [
            'today' => self::countActiveSince(Carbon::today()),
            'days_3' => self::countActiveSince(Carbon::today()->subDays(2)->startOfDay()),
            'days_7' => self::countActiveSince(Carbon::today()->subDays(6)->startOfDay()),
            'days_14' => self::countActiveSince(Carbon::today()->subDays(13)->startOfDay()),
            'days_30' => self::countActiveSince(Carbon::today()->subDays(29)->startOfDay()),
        ];
    }

    public static function countActiveSince(Carbon $since): int
    {
        return User::query()
            ->where('last_online_at', '>=', $since)
            ->count();
    }

    /**
     * Уникальные пользователи по дням (visit_statistics); если пусто — по last_online_at за день.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public static function chartLastDays(int $days = 30): array
    {
        $days = max($days, 7);
        $from = Carbon::today()->subDays($days - 1);
        $fromDate = $from->toDateString();

        $byDay = VisitStatistic::query()
            ->where('date', '>=', $fromDate)
            ->select('date', DB::raw('COUNT(DISTINCT user_id) as users_count'))
            ->groupBy('date')
            ->pluck('users_count', 'date')
            ->all();

        if (array_sum($byDay) === 0) {
            $byDay = User::query()
                ->where('last_online_at', '>=', $from->copy()->startOfDay())
                ->select(DB::raw('DATE(last_online_at) as day'), DB::raw('COUNT(*) as users_count'))
                ->groupBy('day')
                ->pluck('users_count', 'day')
                ->all();
        }

        $labels = [];
        $values = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->format('d.m');
            $values[] = (int) ($byDay[$day->toDateString()] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}
