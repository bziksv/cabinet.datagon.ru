<?php

namespace App\Support;

use App\Common;
use App\MainProject;
use App\User;
use App\VisitStatistic;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
/**
 * Сводка визитов по одному модулю (main-projects/statistics/{id}).
 */
class ModuleVisitStatisticsReport
{
    public static function build(MainProject $project, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $usersIds = User::where('statistic', 1)->pluck('id')->toArray();

        $rows = VisitStatistic::query()
            ->where('project_id', $project->id)
            ->whereIn('user_id', $usersIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with(['user' => static function ($query) {
                $query->select(['id', 'name', 'last_name', 'email']);
            }])
            ->orderBy('date')
            ->get(['date', 'user_id', 'actions_counter', 'refresh_page_counter', 'seconds']);

        $byDate = $rows->groupBy('date');

        $periodDays = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($periodDays - 1)->startOfDay();
        $prevTotals = self::aggregatePeriod($project->id, $usersIds, $prevFrom, $prevTo);

        $chartLabels = [];
        $chartActions = [];
        $chartRefresh = [];
        $chartMinutes = [];
        $daily = [];

        $totalActions = 0;
        $totalRefresh = 0;
        $totalSeconds = 0;
        $uniqueUsers = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $dateKey = $day->toDateString();
            $dayRows = $byDate->get($dateKey, collect());

            $actions = (int) $dayRows->sum('actions_counter');
            $refresh = (int) $dayRows->sum('refresh_page_counter');
            $seconds = (int) $dayRows->sum('seconds');

            $totalActions += $actions;
            $totalRefresh += $refresh;
            $totalSeconds += $seconds;

            foreach ($dayRows as $row) {
                $uniqueUsers[$row->user_id] = true;
            }

            $chartLabels[] = $day->format('d.m');
            $chartActions[] = $actions;
            $chartRefresh[] = $refresh;
            $chartMinutes[] = round($seconds / 60, 1);

            $users = $dayRows->map(static function ($elem) {
                $user = $elem->user;
                if (!$user) {
                    return null;
                }

                return [
                    'user_id' => (int) $elem->user_id,
                    'email' => $user->email,
                    'name' => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                    'actionsCounter' => (int) $elem->actions_counter,
                    'refreshPageCounter' => (int) $elem->refresh_page_counter,
                    'time' => Common::secondsToDate($elem->seconds),
                    'seconds' => (int) $elem->seconds,
                    'total' => (int) $elem->actions_counter + (int) $elem->refresh_page_counter,
                ];
            })->filter()->values()->all();

            usort($users, static function ($a, $b) {
                return $b['total'] <=> $a['total'] ?: $b['seconds'] <=> $a['seconds'];
            });

            $daily[] = [
                'date' => $dateKey,
                'date_label' => $day->format('d.m.Y'),
                'weekday' => self::weekdayShort($day),
                'actionsCounter' => $actions,
                'refreshPageCounter' => $refresh,
                'seconds' => $seconds,
                'time' => Common::secondsToDate($seconds),
                'total' => $actions + $refresh,
                'is_empty' => ($actions + $refresh + $seconds) === 0,
                'users_count' => count($users),
                'users' => $users,
            ];
        }

        usort($daily, static function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $userTotals = [];
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            if (!isset($userTotals[$uid])) {
                $user = $row->user;
                $userTotals[$uid] = [
                    'user_id' => $uid,
                    'email' => $user ? $user->email : '—',
                    'name' => $user ? trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')) : '',
                    'actions' => 0,
                    'refresh' => 0,
                    'seconds' => 0,
                ];
            }
            $userTotals[$uid]['actions'] += (int) $row->actions_counter;
            $userTotals[$uid]['refresh'] += (int) $row->refresh_page_counter;
            $userTotals[$uid]['seconds'] += (int) $row->seconds;
        }

        $topUsers = array_values($userTotals);
        usort($topUsers, static function ($a, $b) {
            return ($b['actions'] + $b['refresh']) <=> ($a['actions'] + $a['refresh']);
        });
        $topUsers = array_slice($topUsers, 0, 12);
        foreach ($topUsers as &$u) {
            $u['total'] = $u['actions'] + $u['refresh'];
            $u['time'] = Common::secondsToDate($u['seconds']);
        }
        unset($u);

        $peakDay = null;
        foreach ($daily as $day) {
            if ($day['total'] <= 0) {
                continue;
            }
            if ($peakDay === null || $day['total'] > $peakDay['total']) {
                $peakDay = $day;
            }
        }

        $chartTotal = [];
        foreach ($daily as $day) {
            $chartTotal[] = $day['total'];
        }

        $activeDays = count(array_filter($daily, static function ($d) {
            return $d['total'] > 0;
        }));

        $weekdayTotals = array_fill(0, 7, 0);
        foreach ($daily as $day) {
            if ($day['total'] <= 0) {
                continue;
            }
            $weekdayTotals[(int) Carbon::parse($day['date'])->format('w')] += $day['total'];
        }

        $totalEvents = $totalActions + $totalRefresh;

        $currentTotals = [
            'actions' => $totalActions,
            'refresh' => $totalRefresh,
            'seconds' => $totalSeconds,
        ];

        return [
            'has_data' => $rows->count() > 0,
            'summary' => [
                'unique_users' => count($uniqueUsers),
                'days_with_visits' => $byDate->count(),
                'active_days' => $activeDays,
                'period_days' => $periodDays,
                'total_actions' => $totalActions,
                'total_refresh' => $totalRefresh,
                'total_seconds' => $totalSeconds,
                'total_time' => Common::secondsToDate($totalSeconds),
                'avg_actions_per_day' => $activeDays > 0 ? round($totalActions / $activeDays, 1) : 0,
                'avg_total_per_active_day' => $activeDays > 0
                    ? round(($totalActions + $totalRefresh) / $activeDays, 1) : 0,
                'total_events' => $totalEvents,
                'prev_actions' => $prevTotals['actions'],
                'prev_refresh' => $prevTotals['refresh'],
                'prev_seconds' => $prevTotals['seconds'],
                'prev_events' => $prevTotals['actions'] + $prevTotals['refresh'],
                'prev_time' => Common::secondsToDate($prevTotals['seconds']),
                'trend_events' => self::trendPercent(
                    $totalEvents,
                    $prevTotals['actions'] + $prevTotals['refresh']
                ),
                'trend_actions' => self::trendPercent($currentTotals['actions'], $prevTotals['actions']),
                'trend_refresh' => self::trendPercent($currentTotals['refresh'], $prevTotals['refresh']),
                'trend_seconds' => self::trendPercent($currentTotals['seconds'], $prevTotals['seconds']),
                'prev_from' => $prevFrom->format('d.m.Y'),
                'prev_to' => $prevTo->format('d.m.Y'),
            ],
            'peak_day' => $peakDay,
            'top_users' => $topUsers,
            'distribution' => [
                'actions' => $totalActions,
                'refresh' => $totalRefresh,
            ],
            'weekday' => [
                'labels' => self::weekdayLabels(),
                'values' => array_map('intval', $weekdayTotals),
            ],
            'chart' => [
                'labels' => array_reverse($chartLabels),
                'actions' => array_reverse($chartActions),
                'refresh' => array_reverse($chartRefresh),
                'minutes' => array_reverse($chartMinutes),
                'total' => array_reverse($chartTotal),
            ],
            'daily' => $daily,
        ];
    }

    protected static function aggregatePeriod(int $projectId, array $usersIds, Carbon $from, Carbon $to): array
    {
        $row = VisitStatistic::query()
            ->where('project_id', $projectId)
            ->whereIn('user_id', $usersIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(actions_counter) as actions, SUM(refresh_page_counter) as refresh, SUM(seconds) as seconds')
            ->first();

        return [
            'actions' => (int) ($row->actions ?? 0),
            'refresh' => (int) ($row->refresh ?? 0),
            'seconds' => (int) ($row->seconds ?? 0),
        ];
    }

    protected static function weekdayLabels(): array
    {
        return ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
    }

    protected static function weekdayShort(Carbon $day): string
    {
        $names = self::weekdayLabels();

        return $names[(int) $day->format('w')] ?? '';
    }

    protected static function trendPercent(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
