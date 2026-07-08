<?php

namespace App\Support;

/**
 * Фильтр пунктов левого меню (main_projects).
 * titlo: скрываем только legacy «Мониторинг позиций» (/monitoring), остальное меню без изменений.
 */
class CabinetSidebarMenu
{
    /** main_projects.id — старый /monitoring */
    private const DEFAULT_HIDDEN_IDS = [32];

    public static function hideLegacyMonitoring(): bool
    {
        $configured = config('cabinet-sidebar.hide_legacy_monitoring');

        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return true;
    }

    /**
     * @return int[]
     */
    public static function hiddenProjectIds(): array
    {
        $ids = config('cabinet-sidebar.hidden_project_ids');

        if (! is_array($ids) || $ids === []) {
            $ids = self::DEFAULT_HIDDEN_IDS;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param array<int|string, mixed> $modules
     * @return array<int|string, mixed>
     */
    public static function filterModules(array $modules): array
    {
        if (! self::hideLegacyMonitoring()) {
            return $modules;
        }

        $hidden = array_flip(self::hiddenProjectIds());
        $out = [];

        foreach ($modules as $key => $module) {
            if (! is_array($module)) {
                continue;
            }

            if (array_key_exists('configurationInfo', $module)) {
                $filtered = ['configurationInfo' => $module['configurationInfo']];

                foreach ($module as $k => $elem) {
                    if ($k === 'configurationInfo' || ! is_array($elem)) {
                        continue;
                    }
                    $id = (int) ($elem['id'] ?? 0);
                    if (! isset($hidden[$id])) {
                        $filtered[$k] = $elem;
                    }
                }

                if (count($filtered) > 1) {
                    $out[$key] = $filtered;
                }

                continue;
            }

            $id = (int) ($module['id'] ?? 0);
            if (! isset($hidden[$id])) {
                $out[$key] = $module;
            }
        }

        return $out;
    }
}
