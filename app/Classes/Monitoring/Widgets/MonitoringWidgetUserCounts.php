<?php

namespace App\Classes\Monitoring\Widgets;

use App\Http\Controllers\MonitoringProjectUserStatusController;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Один запрос monitoringProjects + eager users на HTTP-запрос (виджеты вызываются многократно).
 */
final class MonitoringWidgetUserCounts
{
    private static $projectsWithUsers;

    public static function monitoringProjectCount(): int
    {
        return self::projectsWithUsers()->count();
    }

    public static function countByStatus(string $statusCode): int
    {
        $status = MonitoringProjectUserStatusController::getIdStatusByCode($statusCode);

        return self::projectsWithUsers()
            ->pluck('users')
            ->flatten()
            ->filter(function ($user) use ($status) {
                return isset($user['pivot']['status']) && $user['pivot']['status'] === $status;
            })
            ->unique('id')
            ->count();
    }

    private static function projectsWithUsers(): Collection
    {
        if (self::$projectsWithUsers !== null) {
            return self::$projectsWithUsers;
        }

        /** @var User $user */
        $user = Auth::user();

        self::$projectsWithUsers = $user->monitoringProjects()
            ->with(['users:id,name,last_name'])
            ->get(['monitoring_projects.id']);

        return self::$projectsWithUsers;
    }
}
