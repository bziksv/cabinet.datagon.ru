<?php

namespace App\Services;

use App\MainProject;
use App\User;
use Illuminate\Support\Collection;

/**
 * Один запрос main_projects на HTTP-запрос (configuration-menu + sortMenu).
 */
class MenuProjectRegistry
{
    /** @var Collection<int, MainProject>|null */
    private static $allOrdered;

    public static function isLoaded(): bool
    {
        return self::$allOrdered !== null;
    }

    /**
     * @return Collection<int, MainProject>
     */
    public static function ensureAllLoaded(): Collection
    {
        if (self::$allOrdered === null) {
            self::$allOrdered = MainProject::query()
                ->orderBy('position')
                ->get();
        }

        return self::$allOrdered;
    }

    /**
     * @return Collection<int, MainProject>
     */
    public static function forSortMenu(): Collection
    {
        $all = self::ensureAllLoaded();
        if (User::isUserAdmin()) {
            return $all;
        }

        return $all->where('show', 1)->values();
    }

    public static function clear(): void
    {
        self::$allOrdered = null;
    }

    /**
     * Метка состава меню: при новом пункте в main_projects сбрасываем session-кэш сайдбара.
     */
    public static function structureStamp(): string
    {
        $all = self::ensureAllLoaded();

        $maxUpdated = $all->max('updated_at');

        return $all->count() . ':' . ($maxUpdated ? $maxUpdated->getTimestamp() : 0) . ':' . (int) $all->max('id');
    }
}
