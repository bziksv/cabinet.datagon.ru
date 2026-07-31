<?php

namespace App\ViewComposers;

use App\MenuItemsPosition;
use App\SeoChecklist\SeoChecklistUserPreference;
use App\Services\MenuProjectRegistry;
use App\Support\CabinetAdminMenu;
use App\Support\CabinetSidebarMenu;
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
            $stamp = MenuProjectRegistry::structureStamp() . ':' . (CabinetSidebarMenu::hideLegacyMonitoring() ? 'n32' : 'full');
            $cached = session('cabinet_menu_modules_v9');
            $cachedStamp = session('cabinet_menu_modules_v9_stamp');
            if (is_array($cached) && $cachedStamp === $stamp && $this->cachedMenuHasItems($cached)) {
                $view->with('modules', CabinetSidebarMenu::filterModules(CabinetAdminMenu::filterModules($cached)));

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
                            'title' => $this->moduleDisplayTitle($elem),
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
                        'title' => $this->moduleDisplayTitle($item),
                        'description' => $item['description'],
                        'link' => localize_cabinet_url($item['link']),
                        'icon' => $item['icon'],
                    ];
                }
            }
        }

        $modules = CabinetSidebarMenu::filterModules(CabinetAdminMenu::filterModules(collect($modules)->toArray()));

        if (cabinet_skip_heavy_web()) {
            session([
                'cabinet_menu_modules_v9' => $modules,
                'cabinet_menu_modules_v9_stamp' => MenuProjectRegistry::structureStamp() . ':' . (CabinetSidebarMenu::hideLegacyMonitoring() ? 'n32' : 'full'),
            ]);
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

    /**
     * @param  array<string, mixed>  $item
     */
    private function moduleDisplayTitle(array $item): string
    {
        $titleKey = (string) ($item['title'] ?? '');
        $link = (string) ($item['link'] ?? '');
        if ($titleKey === 'SEO Checklist' || strpos($link, 'seo-checklist') !== false) {
            return SeoChecklistUserPreference::moduleTitleFor((int) Auth::id());
        }

        return __($titleKey);
    }
}
