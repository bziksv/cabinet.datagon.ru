<?php

namespace App\Support;

use App\Common;
use App\User;
use App\VisitStatistic;
use Carbon\Carbon;

/**
 * Агрегированная статистика визитов по пользователям (/visits-statistics).
 */
class UsersVisitStatisticsReport
{
    /**
     * @param  Carbon|null  $from
     * @param  Carbon|null  $to
     */
    public static function build(?Carbon $from, ?Carbon $to, int $limit): array
    {
        $limit = min(max($limit, 50), 2000);

        $trackedIds = User::where('statistic', 1)->pluck('id')->toArray();

        $query = VisitStatistic::query()
            ->selectRaw('user_id, SUM(actions_counter) as actions_counter, SUM(refresh_page_counter) as refresh_page_counter, SUM(seconds) as seconds')
            ->whereIn('user_id', $trackedIds);

        if ($from) {
            $query->where('date', '>=', $from->toDateString());
        }
        if ($to) {
            $query->where('date', '<=', $to->toDateString());
        }

        $aggregated = $query
            ->groupBy('user_id')
            ->orderByRaw('SUM(seconds) DESC')
            ->limit($limit)
            ->get()
            ->keyBy('user_id');

        $users = User::whereIn('id', $aggregated->keys())
            ->with('roles:id,name')
            ->get(['id', 'name', 'last_name', 'email', 'metrics']);

        $rows = [];
        $totalActions = 0;
        $totalRefresh = 0;
        $totalSeconds = 0;

        foreach ($users as $user) {
            $stat = $aggregated->get($user->id);
            if (!$stat) {
                continue;
            }

            $actions = (int) $stat->actions_counter;
            $refresh = (int) $stat->refresh_page_counter;
            $seconds = (int) $stat->seconds;

            $totalActions += $actions;
            $totalRefresh += $refresh;
            $totalSeconds += $seconds;

            $rows[] = [
                'user_id' => (int) $user->id,
                'email' => $user->email,
                'name' => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                'roles' => $user->roles->pluck('name')->all(),
                'actions' => $actions,
                'refresh' => $refresh,
                'seconds' => $seconds,
                'time' => self::formatDuration($seconds),
                'total_events' => $actions + $refresh,
                'utm_source' => self::extractUtmSource($user->metrics),
            ];
        }

        usort($rows, static function ($a, $b) {
            return $b['seconds'] <=> $a['seconds'];
        });

        return [
            'rows' => $rows,
            'summary' => [
                'users_shown' => count($rows),
                'limit' => $limit,
                'total_actions' => $totalActions,
                'total_refresh' => $totalRefresh,
                'total_seconds' => $totalSeconds,
                'total_time' => self::formatDuration($totalSeconds),
                'total_events' => $totalActions + $totalRefresh,
                'tracked_users' => count($trackedIds),
            ],
            'has_period' => $from !== null || $to !== null,
        ];
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00:00';
        }

        return Carbon::now()->addSeconds($seconds)->diff(Carbon::now())->format('%H:%I:%S');
    }

    /**
     * @param  mixed  $metrics
     */
    public static function extractUtmSource($metrics): ?string
    {
        if (!is_array($metrics)) {
            return null;
        }

        if (!empty($metrics['utm_source'])) {
            return (string) $metrics['utm_source'];
        }

        foreach ($metrics as $val) {
            if (!is_string($val)) {
                continue;
            }
            $parts = explode(':', $val, 2);
            if (($parts[0] ?? '') === 'utm_source' && isset($parts[1])) {
                return trim($parts[1]);
            }
        }

        return null;
    }
}
