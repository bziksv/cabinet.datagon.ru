<?php

namespace App;

use App\Services\MenuProjectRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MenuItemsPosition extends Model
{
    protected $table = 'menu_items_position';

    protected $guarded = [];

    /** @var array|null Кэш на один HTTP-запрос (index + MenuComposer). */
    private static $sortMenuCache;

    public static function clearSortMenuCache(): void
    {
        self::$sortMenuCache = null;
    }

    public static function sortMenu(): array
    {
        if (self::$sortMenuCache !== null) {
            return self::$sortMenuCache;
        }

        $columns = ['id', 'title', 'description', 'link', 'icon', 'access', 'position', 'show'];

        if (MenuProjectRegistry::isLoaded()) {
            $items = MenuProjectRegistry::forSortMenu()
                ->map(static function (MainProject $project) use ($columns) {
                    return $project->only($columns);
                })
                ->values()
                ->all();
        } else {
            $query = MainProject::query()->orderBy('position', 'asc')->select($columns);
            if (User::isUserAdmin()) {
                $items = $query->get()->toArray();
            } else {
                $items = $query->where('show', '=', 1)->get()->toArray();
            }
        }
        $config = MenuItemsPosition::where('user_id', '=', Auth::id())->first();

        if (isset($config)) {
            $oldPositions = json_decode($config->positions, true);
            $newPositions = [];

            foreach ($oldPositions as $item) {
                if (isset($item[0]) && $item[0]['dir']) {
                    $newPositions[$item[0]['dirName']]['configurationInfo'] = $item[0];
                    foreach ($item as $groupItem) {
                        if (isset($groupItem['dir'])) {
                            continue;
                        }
                        foreach ($items as $key => $elem) {
                            if ($elem['id'] == $groupItem['id']) {
                                $newPositions[$item[0]['dirName']][] = $elem;
                                unset($items[$key]);
                            }
                        }
                    }
                    continue;
                }
                foreach ($items as $key => $elem) {
                    if ($elem['id'] == $item['id']) {
                        $newPositions[] = $elem;
                        unset($items[$key]);
                    }
                }
            }

            if (count($items) > 0) {
                foreach ($items as $elem) {
                    $newPositions[] = $elem;
                }
            }

            self::$sortMenuCache = $newPositions;

            return $newPositions;
        }

        self::$sortMenuCache = $items;

        return $items;
    }
}
