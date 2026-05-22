<?php


namespace App\Classes\Monitoring;


use Illuminate\Support\Facades\Auth;

class ProjectsStatisticFacade
{
    /** @var bool|null */
    private static $todayResolved = false;

    /** @var \Illuminate\Support\Collection|null */
    private static $todayProjects;

    static public function getTodayProjects()
    {
        if (self::$todayResolved) {
            return self::$todayProjects;
        }

        self::$todayResolved = true;

        $user = Auth::user();

        $statistics = $user->statistics()->selectMonitoringProjectsToday()->first();

        if (!$statistics) {
            self::$todayProjects = null;

            return null;
        }

        self::$todayProjects = $statistics['monitoring_project'];

        return self::$todayProjects;
    }

    static public function getMidTopPct(string $filedPct): float
    {
        $projects = self::getTodayProjects();

        if(!$projects)
            return 0;

        return round($projects->pluck($filedPct)->sum() / $projects->count(), 2);
    }

    static public function getMidMasteredBudgetPct(): float
    {
        $projects = self::getTodayProjects();

        if(!$projects)
            return 0;

        $budget = $projects->pluck('budget')->sum();
        $mastered = $projects->pluck('mastered')->sum();

        return round($mastered / ($budget / 100), 2);
    }
}
