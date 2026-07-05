<?php

namespace App\Support;

use App\MonitoringProject;
use App\MonitoringSearchengine;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Расписание съёма позиций (/monitoring): Free — только ручное снятие.
 */
class MonitoringPositionsSchedule
{
    /**
     * Есть сохранённые параметры расписания (даже если auto_update выключен).
     */
    public static function engineHasScheduleConfigured(MonitoringSearchengine $engine): bool
    {
        if ($engine->auto_update) {
            return true;
        }

        if ($engine->monthday || $engine->day) {
            return true;
        }

        $weekdays = $engine->weekdays;
        if (is_array($weekdays) && $weekdays !== []) {
            return true;
        }

        return trim((string) $engine->time) !== '';
    }

    public static function hasConfiguredScheduleForUser(User $user): bool
    {
        $projectIds = MonitoringProject::query()
            ->where('creator', $user->id)
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return false;
        }

        return MonitoringSearchengine::query()
            ->whereIn('monitoring_project_id', $projectIds)
            ->get()
            ->contains(static function (MonitoringSearchengine $engine) {
                return self::engineHasScheduleConfigured($engine);
            });
    }

    /**
     * Отключает auto_update у всех регионов пользователя Free.
     *
     * @return int количество обновлённых строк
     */
    public static function enforceForFreeUser(User $user): int
    {
        if (!$user->onFreeTariff()) {
            return 0;
        }

        $projectIds = MonitoringProject::query()
            ->where('creator', $user->id)
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
     * Массовая приостановка всех активных расписаний (auto_update → false).
     *
     * @return int количество обновлённых строк
     */
    public static function suspendAllAutoSchedules(): int
    {
        return MonitoringSearchengine::query()
            ->where('auto_update', true)
            ->update(['auto_update' => false]);
    }

    /**
     * Включить auto_update там, где расписание сохранено (обратно к suspend, кроме Free).
     *
     * @return int количество обновлённых строк
     */
    public static function resumePaidAutoSchedules(?int $projectId = null): int
    {
        $query = MonitoringSearchengine::query()->where('auto_update', false);

        if ($projectId !== null) {
            $query->where('monitoring_project_id', $projectId);
        }

        $count = 0;
        foreach ($query->get() as $engine) {
            if (!self::engineHasScheduleConfigured($engine)) {
                continue;
            }
            if (self::creatorOnFreeTariff($engine)) {
                continue;
            }
            $engine->update(['auto_update' => true]);
            $count++;
        }

        return $count;
    }

    /**
     * Включить auto_update у всех регионов проектов пользователя (не Free).
     *
     * @param bool $onlyWithSchedule если true — только регионы с сохранённым расписанием
     * @return int количество обновлённых строк
     */
    public static function countResumableAutoSchedulesForUser(int $userId, bool $onlyWithSchedule = false): int
    {
        $projectIds = MonitoringProject::query()
            ->where('creator', $userId)
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach (
            MonitoringSearchengine::query()
                ->whereIn('monitoring_project_id', $projectIds)
                ->where('auto_update', false)
                ->get() as $engine
        ) {
            if ($onlyWithSchedule && !self::engineHasScheduleConfigured($engine)) {
                continue;
            }
            if (self::creatorOnFreeTariff($engine)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    public static function resumeAutoSchedulesForUser(int $userId, bool $onlyWithSchedule = false): int
    {
        $projectIds = MonitoringProject::query()
            ->where('creator', $userId)
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach (
            MonitoringSearchengine::query()
                ->whereIn('monitoring_project_id', $projectIds)
                ->where('auto_update', false)
                ->get() as $engine
        ) {
            if ($onlyWithSchedule && !self::engineHasScheduleConfigured($engine)) {
                continue;
            }
            if (self::creatorOnFreeTariff($engine)) {
                continue;
            }
            $engine->update(['auto_update' => true]);
            $count++;
        }

        return $count;
    }

    public static function countResumableAutoSchedules(?int $projectId = null): int
    {
        $query = MonitoringSearchengine::query()->where('auto_update', false);

        if ($projectId !== null) {
            $query->where('monitoring_project_id', $projectId);
        }

        $count = 0;
        foreach ($query->get() as $engine) {
            if (!self::engineHasScheduleConfigured($engine)) {
                continue;
            }
            if (self::creatorOnFreeTariff($engine)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return int количество обновлённых строк
     */
    public static function suspendAutoSchedulesForFreeUsers(): int
    {
        $userIds = self::freeTariffUserIds();

        if ($userIds->isEmpty()) {
            return 0;
        }

        $projectIds = MonitoringProject::query()
            ->whereIn('creator', $userIds)
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return 0;
        }

        return MonitoringSearchengine::query()
            ->whereIn('monitoring_project_id', $projectIds)
            ->where('auto_update', true)
            ->update(['auto_update' => false]);
    }

    public static function creatorOnFreeTariff(MonitoringSearchengine $engine): bool
    {
        $project = $engine->project;

        if (!$project) {
            return false;
        }

        $creator = User::query()->find((int) $project->creator);

        return $creator instanceof User && $creator->onFreeTariff();
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function freeTariffUserIds()
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $roleId = Role::query()
            ->where('name', 'Free')
            ->value('id');

        if (!$roleId) {
            return collect();
        }

        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $paidCodes = array_filter((array) config('cabinet-users.paid_tariff_role_codes', []));

        $query = DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->when(
                config('permission.teams'),
                static function ($q) use ($teamKey) {
                    $q->where($teamKey, 1);
                }
            );

        if ($paidCodes !== []) {
            $query->whereNotIn($morphKey, static function ($sub) use ($paidCodes, $morphKey, $teamKey) {
                $sub->select($morphKey)
                    ->from(config('permission.table_names.model_has_roles') . ' as mhr_paid')
                    ->join('roles as r_paid', 'r_paid.id', '=', 'mhr_paid.role_id')
                    ->where('mhr_paid.model_type', User::class)
                    ->whereIn('r_paid.name', $paidCodes)
                    ->when(
                        config('permission.teams'),
                        static function ($q) use ($teamKey) {
                            $q->where('mhr_paid.' . $teamKey, 1);
                        }
                    );
            });
        }

        return $query->pluck($morphKey);
    }
}
