<?php

namespace App\ViewComposers;

use App\MenuItemsPosition;
use App\Support\CabinetAdminMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MenuComposer
{
    public function compose(View $view)
    {
        $user = Auth::user();
        if (! isset($user)) {
            return;
        }

        if (cabinet_skip_heavy_web()) {
            $cached = session('cabinet_menu_modules_v4');
            if (is_array($cached) && $this->cachedMenuHasItems($cached)) {
                $view->with('modules', CabinetAdminMenu::filterModules($cached));

                return;
            }
        }

        apply_global_team_permissions();
        $user->loadMissing('roles');

        $result = MenuItemsPosition::sortMenu();
        $modules = [];

        foreach ($result as $key => $item) {
            if (array_key_exists('configurationInfo', $item)) {
                foreach ($item as $k => $elem) {
                    if ($k === 'configurationInfo') {
                        $modules[$key]['configurationInfo'] = $elem;
                        continue;
                    }

                    $access = (is_null($elem['access'])) ? [] : $elem['access'];

                    if ($user->hasRole($access)) {
                        $modules[$key][] = [
                            'id' => $elem['id'],
                            'title' => __($elem['title']),
                            'description' => $elem['description'],
                            'link' => localize_cabinet_url($elem['link']),
                            'icon' => $elem['icon'],
                        ];
                    }
                }
            } else {
                $access = (is_null($item['access'])) ? [] : $item['access'];
                if ($user->hasRole($access)) {
                    $modules[] = [
                        'id' => $item['id'],
                        'title' => __($item['title']),
                        'description' => $item['description'],
                        'link' => localize_cabinet_url($item['link']),
                        'icon' => $item['icon'],
                    ];
                }
            }
        }

        $modules = CabinetAdminMenu::filterModules(collect($modules)->toArray());

        if (cabinet_skip_heavy_web()) {
            session(['cabinet_menu_modules_v4' => $modules]);
        }

        $view->with(compact('modules'));
    }

    private function cachedMenuHasItems(array $modules): bool
    {
        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }
            if (array_key_exists('configurationInfo', $module) && count($module) > 1) {
                return true;
            }
            if (! array_key_exists('configurationInfo', $module) && isset($module['id'])) {
                return true;
            }
        }

        return false;
    }
}
