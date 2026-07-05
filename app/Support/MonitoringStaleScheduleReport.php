<?php

namespace App\Support;

use App\Classes\Monitoring\MonitoringSearchengineScheduleFormatter;
use App\MonitoringProject;
use App\MonitoringSearchengine;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Проекты мониторинга с auto_update у неактивных владельцев (/users).
 */
class MonitoringStaleScheduleReport
{
    public static function inactiveDays(): int
    {
        return max(1, (int) config('cabinet-monitoring.stale_schedules_inactive_days', config('cabinet-users.stale_monitoring_inactive_days', 90)));
    }

    /**
     * @return array{projects: int, users: int, auto_regions: int, keywords: int, inactive_days: int}
     */
    public static function summary(?int $inactiveDays = null): array
    {
        $days = $inactiveDays ?? self::inactiveDays();
        $row = self::aggregatedQuery($days)
            ->selectRaw('COUNT(DISTINCT mp.id) as projects, COUNT(DISTINCT u.id) as users, COUNT(DISTINCT ms.id) as auto_regions')
            ->first();

        $projectIds = self::aggregatedQuery($days)->distinct()->pluck('mp.id');
        $keywords = $projectIds->isEmpty()
            ? 0
            : (int) DB::table('monitoring_keywords')->whereIn('monitoring_project_id', $projectIds)->count();

        return [
            'inactive_days' => $days,
            'projects' => (int) ($row->projects ?? 0),
            'users' => (int) ($row->users ?? 0),
            'auto_regions' => (int) ($row->auto_regions ?? 0),
            'keywords' => $keywords,
        ];
    }

    /**
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public static function listPage(
        int $start,
        int $length,
        ?int $inactiveDays = null,
        bool $freeTariffOnly = false,
        string $sortBy = 'keywords_count',
        string $sortDir = 'desc'
    ): array {
        $days = $inactiveDays ?? self::inactiveDays();
        $start = max(0, $start);
        $length = max(1, min($length, 100));

        $allowedSort = ['url', 'email', 'last_online_at', 'tariff', 'keywords_count', 'auto_regions'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'keywords_count';
        }
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $base = self::projectAggregatesQuery($days, $freeTariffOnly);

        $total = (int) (clone $base)->count(DB::raw('DISTINCT mp.id'));

        $query = (clone $base)
            ->select([
                'mp.id as project_id',
                'mp.url',
                'mp.name',
                'u.id as user_id',
                'u.email',
                'u.name as user_name',
                'u.last_name as user_last_name',
                'u.last_online_at',
                DB::raw('COUNT(DISTINCT ms.id) as auto_regions'),
                DB::raw('(SELECT COUNT(*) FROM monitoring_keywords mk WHERE mk.monitoring_project_id = mp.id) as keywords_count'),
            ])
            ->groupBy('mp.id', 'mp.url', 'mp.name', 'u.id', 'u.email', 'u.name', 'u.last_name', 'u.last_online_at');

        self::applyListSort($query, $sortBy, $sortDir);

        $rows = $query
            ->offset($start)
            ->limit($length)
            ->get();

        $projectIds = $rows->pluck('project_id')->all();
        $schedules = self::scheduleLabelsByProject($projectIds);
        $tariffs = self::tariffLabelsByUser($rows->pluck('user_id')->unique()->all());

        $items = [];
        foreach ($rows as $row) {
            $loa = $row->last_online_at ? Carbon::parse($row->last_online_at) : null;
            $items[] = [
                'project_id' => (int) $row->project_id,
                'url' => (string) $row->url,
                'name' => (string) $row->name,
                'user_id' => (int) $row->user_id,
                'user_email' => (string) $row->email,
                'user_name' => trim($row->user_name . ' ' . $row->user_last_name),
                'last_online_at' => $loa ? $loa->format('d.m.Y H:i') : null,
                'last_online_human' => $loa ? $loa->diffForHumans() : __('Never'),
                'tariff' => $tariffs[(int) $row->user_id] ?? '—',
                'auto_regions' => (int) $row->auto_regions,
                'keywords_count' => (int) $row->keywords_count,
                'schedules' => $schedules[(int) $row->project_id] ?? [],
                'monitoring_url' => url('/monitoring/' . (int) $row->project_id),
            ];
        }

        return ['total' => $total, 'rows' => $items];
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     */
    private static function applyListSort($query, string $sortBy, string $sortDir): void
    {
        switch ($sortBy) {
            case 'url':
                $query->orderBy('mp.url', $sortDir);
                break;
            case 'email':
                $query->orderBy('u.email', $sortDir);
                break;
            case 'last_online_at':
                if ($sortDir === 'asc') {
                    $query->orderByRaw('u.last_online_at IS NULL DESC')
                        ->orderBy('u.last_online_at', 'asc');
                } else {
                    $query->orderByRaw('u.last_online_at IS NULL ASC')
                        ->orderBy('u.last_online_at', 'desc');
                }
                break;
            case 'tariff':
                $userClass = str_replace('\\', '\\\\', User::class);
                $query->orderByRaw(
                    "(SELECT MIN(r.name) FROM model_has_roles mhr INNER JOIN roles r ON r.id = mhr.role_id WHERE mhr.model_id = u.id AND mhr.model_type = '{$userClass}') {$sortDir}"
                );
                break;
            case 'auto_regions':
                $query->orderBy('auto_regions', $sortDir);
                break;
            case 'keywords_count':
            default:
                $query->orderBy('keywords_count', $sortDir);
                break;
        }

        if ($sortBy !== 'url') {
            $query->orderBy('mp.url', 'asc');
        }
    }

    public static function disableProjectSchedule(int $projectId): int
    {
        return MonitoringSearchengine::query()
            ->where('monitoring_project_id', $projectId)
            ->where('auto_update', true)
            ->update(['auto_update' => false]);
    }

    public static function disableUserSchedules(int $userId): int
    {
        $projectIds = MonitoringProject::query()
            ->where('creator', $userId)
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return 0;
        }

        return MonitoringSearchengine::query()
            ->whereIn('monitoring_project_id', $projectIds)
            ->where('auto_update', true)
            ->update(['auto_update' => false]);
    }

    /**
     * user_id[] с активным auto_update и неактивностью > N дней.
     *
     * @return int[]
     */
    public static function staleCreatorUserIds(?int $inactiveDays = null): array
    {
        $days = $inactiveDays ?? self::inactiveDays();
        $cutoff = Carbon::now()->subDays($days);

        return self::aggregatedQuery($days)
            ->distinct()
            ->pluck('u.id')
            ->map(static function ($id) {
                return (int) $id;
            })
            ->all();
    }

    private static function aggregatedQuery(int $inactiveDays)
    {
        $cutoff = Carbon::now()->subDays($inactiveDays);

        return DB::table('monitoring_searchengines as ms')
            ->join('monitoring_projects as mp', 'mp.id', '=', 'ms.monitoring_project_id')
            ->join('users as u', 'u.id', '=', 'mp.creator')
            ->where('ms.auto_update', 1)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('u.last_online_at')
                    ->orWhere('u.last_online_at', '<', $cutoff);
            });
    }

    private static function projectAggregatesQuery(int $inactiveDays, bool $freeTariffOnly)
    {
        $query = self::aggregatedQuery($inactiveDays);

        if ($freeTariffOnly) {
            $paidRoles = array_filter((array) config('cabinet-users.paid_tariff_role_codes', []));
            if ($paidRoles !== []) {
                $query->whereNotExists(function ($sub) use ($paidRoles) {
                    $sub->select(DB::raw(1))
                        ->from('model_has_roles as mhr')
                        ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                        ->whereColumn('mhr.model_id', 'u.id')
                        ->where('mhr.model_type', User::class)
                        ->whereIn('r.name', $paidRoles);
                });
            }
        }

        return $query;
    }

    /**
     * @param int[] $projectIds
     *
     * @return array<int, list<string>>
     */
    private static function scheduleLabelsByProject(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $formatter = app(MonitoringSearchengineScheduleFormatter::class);
        $engines = MonitoringSearchengine::query()
            ->whereIn('monitoring_project_id', $projectIds)
            ->where('auto_update', true)
            ->get();

        $out = [];
        foreach ($engines as $engine) {
            $pid = (int) $engine->monitoring_project_id;
            if (!isset($out[$pid])) {
                $out[$pid] = [];
            }
            $desc = $formatter->describe($engine);
            $label = strtoupper((string) $engine->engine) . ': ' . ($desc['short'] ?? $desc['label']);
            $out[$pid][] = $label;
        }

        return $out;
    }

    /**
     * @param int[]|Collection $userIds
     *
     * @return array<int, string>
     */
    private static function tariffLabelsByUser($userIds): array
    {
        $ids = collect($userIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $paidRoles = array_filter((array) config('cabinet-users.paid_tariff_role_codes', []));
        $roleRows = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', \App\User::class)
            ->whereIn('mhr.model_id', $ids)
            ->select('mhr.model_id as user_id', 'r.name')
            ->get()
            ->groupBy('user_id');

        $out = [];
        foreach ($ids as $uid) {
            $names = collect($roleRows->get($uid, []))->pluck('name');
            $paid = $names->first(static function ($name) use ($paidRoles) {
                return in_array($name, $paidRoles, true);
            });
            if ($paid) {
                $out[(int) $uid] = (string) $paid;
            } elseif ($names->contains('Free')) {
                $out[(int) $uid] = 'Free';
            } else {
                $out[(int) $uid] = $names->first() ?: '—';
            }
        }

        return $out;
    }
}
