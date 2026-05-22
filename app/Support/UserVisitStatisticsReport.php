<?php

namespace App\Support;

use App\MainProject;
use App\User;
use App\VisitStatistic;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Статистика визитов одного пользователя (/visit-statistics/{user}).
 */
class UserVisitStatisticsReport
{
    public static function build(User $user, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $summed = self::aggregateByProject((int) $user->id, $from, $to);
        $lastVisits = self::lastVisitByProject((int) $user->id, $from, $to);
        $chart = self::dailyChart((int) $user->id, $from, $to);

        $modules = [];
        $totalActions = 0;
        $totalRefresh = 0;
        $totalSeconds = 0;

        foreach ($summed as $item) {
            $project = $item->project;
            if (!$project) {
                continue;
            }

            $actions = (int) $item->actionsCounter;
            $refresh = (int) $item->refreshPageCounter;
            $seconds = (int) ($item->raw_seconds ?? 0);

            $totalActions += $actions;
            $totalRefresh += $refresh;
            $totalSeconds += $seconds;

            $color = $project->color && preg_match('/^#[0-9A-Fa-f]{6}$/', $project->color)
                ? $project->color : '#0d6efd';

            $modules[] = [
                'project_id' => (int) $project->id,
                'title' => __($project->title),
                'link' => localize_cabinet_url($project->link),
                'color' => $color,
                'actions' => $actions,
                'refresh' => $refresh,
                'seconds' => $seconds,
                'time' => $item->time,
                'total' => $actions + $refresh,
                'last_visit' => isset($lastVisits[$project->id])
                    ? Carbon::parse($lastVisits[$project->id])->format('d.m.Y')
                    : null,
                'stats_url' => !empty($project->controller)
                    ? route('main-projects.statistics', $project->id)
                    : null,
            ];
        }

        usort($modules, static function ($a, $b) {
            return $b['seconds'] <=> $a['seconds'];
        });

        $doughnutLabels = [];
        $doughnutValues = [];
        $doughnutColors = [];
        foreach ($modules as $m) {
            if ($m['total'] <= 0) {
                continue;
            }
            $doughnutLabels[] = $m['title'];
            $doughnutValues[] = $m['total'];
            $doughnutColors[] = $m['color'];
        }

        return [
            'has_data' => count($modules) > 0,
            'summary' => [
                'actions' => $totalActions,
                'refresh' => $totalRefresh,
                'seconds' => $totalSeconds,
                'time' => UsersVisitStatisticsReport::formatDuration($totalSeconds),
                'total_events' => $totalActions + $totalRefresh,
                'modules_count' => count($modules),
            ],
            'modules' => $modules,
            'chart' => $chart,
            'doughnut' => [
                'labels' => $doughnutLabels,
                'values' => $doughnutValues,
                'colors' => $doughnutColors,
            ],
            'active_dates' => self::activeDates((int) $user->id),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    protected static function aggregateByProject(int $userId, Carbon $from, Carbon $to): Collection
    {
        return VisitStatistic::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('user_id', $userId)
            ->with('project')
            ->get()
            ->groupBy('project_id')
            ->map(static function ($group) {
                $sumActions = (int) $group->sum('actions_counter');
                $sumRefresh = (int) $group->sum('refresh_page_counter');
                $sumSeconds = (int) $group->sum('seconds');
                $first = $group->first();
                $first->actionsCounter = $sumActions;
                $first->refreshPageCounter = $sumRefresh;
                $first->raw_seconds = $sumSeconds;
                $first->time = UsersVisitStatisticsReport::formatDuration($sumSeconds);

                return $first;
            });
    }

    /**
     * @return array<int, string>
     */
    protected static function lastVisitByProject(int $userId, Carbon $from, Carbon $to): array
    {
        return VisitStatistic::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('user_id', $userId)
            ->selectRaw('project_id, MAX(date) as last_visit')
            ->groupBy('project_id')
            ->pluck('last_visit', 'project_id')
            ->all();
    }

    /**
     * @return array{labels: array, actions: array, refresh: array, minutes: array}
     */
    protected static function dailyChart(int $userId, Carbon $from, Carbon $to): array
    {
        $byDate = VisitStatistic::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('user_id', $userId)
            ->get(['date', 'actions_counter', 'refresh_page_counter', 'seconds'])
            ->groupBy('date');

        $labels = [];
        $actions = [];
        $refresh = [];
        $minutes = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $rows = $byDate->get($key, collect());

            $labels[] = $day->format('d.m');
            $actions[] = (int) $rows->sum('actions_counter');
            $refresh[] = (int) $rows->sum('refresh_page_counter');
            $minutes[] = round(((int) $rows->sum('seconds')) / 60, 1);
        }

        return compact('labels', 'actions', 'refresh', 'minutes');
    }

    /**
     * @return array<int, array{date: string}>
     */
    protected static function activeDates(int $userId): array
    {
        return VisitStatistic::query()
            ->where('user_id', $userId)
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->map(static function ($date) {
                return ['date' => $date];
            })
            ->values()
            ->all();
    }

    public static function dateRangeString(Carbon $from, Carbon $to): string
    {
        return $from->format('d-m-Y') . ' - ' . $to->format('d-m-Y');
    }
}
