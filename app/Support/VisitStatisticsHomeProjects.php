<?php

namespace App\Support;

use App\MainProject;

/**
 * Проекты main_projects, которые считаются «главной» в visit_statistics (HomeController).
 */
class VisitStatisticsHomeProjects
{
    /** @var list<int>|null */
    private static $ids;

    /**
     * @return list<int>
     */
    public static function ids(): array
    {
        if (self::$ids === null) {
            self::$ids = MainProject::query()
                ->where('controller', 'like', '%HomeController@%')
                ->pluck('id')
                ->map(static function ($id) {
                    return (int) $id;
                })
                ->unique()
                ->values()
                ->all();
        }

        return self::$ids;
    }

    public static function clear(): void
    {
        self::$ids = null;
    }
}
