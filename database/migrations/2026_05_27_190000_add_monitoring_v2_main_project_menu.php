<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Пункт бокового меню: /monitoring-v2 сразу под «Мониторинг позиций» (main_projects.id=32).
 */
class AddMonitoringV2MainProjectMenu extends Migration
{
    private const MONITORING_PROJECT_ID = 32;

    public function up(): void
    {
        $exists = DB::table('main_projects')
            ->where('link', 'like', '%/monitoring-v2%')
            ->exists();

        if ($exists) {
            $newId = (int) DB::table('main_projects')
                ->where('link', 'like', '%/monitoring-v2%')
                ->value('id');

            if ($newId > 0) {
                $this->insertAfterProjectIdInUserMenus(self::MONITORING_PROJECT_ID, $newId);
            }

            return;
        }

        $parent = DB::table('main_projects')->where('id', self::MONITORING_PROJECT_ID)->first();
        if ($parent === null) {
            return;
        }

        $newId = DB::table('main_projects')->insertGetId([
            'access' => $parent->access,
            'controller' => "MonitoringV2Controller@index\r\n",
            'color' => '#c95a73',
            'title' => 'Position monitoring',
            'description' => 'Мониторинг позиций сайта в поисковых системах.',
            'link' => 'https://lk.redbox.su/monitoring-v2',
            'icon' => '<i class="fas fa-chart-line"></i>',
            'show' => 1,
            'position' => 121,
            'buttons' => $parent->buttons ?? '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertAfterProjectIdInUserMenus(self::MONITORING_PROJECT_ID, (int) $newId);
    }

    public function down(): void
    {
        $ids = DB::table('main_projects')
            ->where('link', 'like', '%/monitoring-v2%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $this->purgeMenuPositions($ids->all());

        if (Schema::hasTable('visit_statistics')) {
            DB::table('visit_statistics')
                ->whereIn('project_id', $ids)
                ->delete();
        }

        DB::table('main_projects')->whereIn('id', $ids)->delete();
    }

    private function insertAfterProjectIdInUserMenus(int $afterId, int $newId): void
    {
        if (! Schema::hasTable('menu_items_position')) {
            return;
        }

        DB::table('menu_items_position')->orderBy('id')->chunk(50, function ($rows) use ($afterId, $newId) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }

                $positions = json_decode($row->positions, true);
                if (! is_array($positions)) {
                    continue;
                }

                if ($this->positionsContainId($positions, $newId)) {
                    continue;
                }

                $changed = false;
                $updated = $this->insertAfterIdInPositions($positions, $afterId, $newId, $changed);

                if ($changed) {
                    DB::table('menu_items_position')
                        ->where('id', $row->id)
                        ->update(['positions' => json_encode($updated)]);
                }
            }
        });
    }

    private function positionsContainId(array $positions, int $searchId): bool
    {
        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                foreach ($item as $entry) {
                    if (isset($entry['id']) && (int) $entry['id'] === $searchId) {
                        return true;
                    }
                }
                continue;
            }

            if (isset($item['id']) && (int) $item['id'] === $searchId) {
                return true;
            }
        }

        return false;
    }

    private function insertAfterIdInPositions(array $positions, int $afterId, int $newId, bool &$changed): array
    {
        $result = [];

        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                $group = [];
                $groupChanged = false;

                foreach ($item as $entry) {
                    if (isset($entry['dir'])) {
                        $group[] = $entry;
                        continue;
                    }

                    $group[] = $entry;

                    if (isset($entry['id']) && (int) $entry['id'] === $afterId) {
                        $group[] = ['id' => $newId];
                        $groupChanged = true;
                        $changed = true;
                    }
                }

                if (count($group) > 1) {
                    $result[] = $group;
                } elseif ($groupChanged) {
                    $result[] = $group;
                }

                continue;
            }

            $result[] = $item;

            if (isset($item['id']) && (int) $item['id'] === $afterId) {
                $result[] = ['id' => $newId];
                $changed = true;
            }
        }

        return $result;
    }

    private function purgeMenuPositions(array $removeIds): void
    {
        if (! Schema::hasTable('menu_items_position')) {
            return;
        }

        DB::table('menu_items_position')->orderBy('id')->chunk(50, function ($rows) use ($removeIds) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }

                $positions = json_decode($row->positions, true);
                if (! is_array($positions)) {
                    continue;
                }

                $changed = false;
                $filtered = $this->filterPositions($positions, $removeIds, $changed);

                if ($changed) {
                    DB::table('menu_items_position')
                        ->where('id', $row->id)
                        ->update(['positions' => json_encode($filtered)]);
                }
            }
        });
    }

    private function filterPositions(array $positions, array $removeIds, bool &$changed): array
    {
        $result = [];

        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                $group = [];
                foreach ($item as $entry) {
                    if (isset($entry['dir'])) {
                        $group[] = $entry;
                        continue;
                    }
                    if (isset($entry['id']) && in_array((int) $entry['id'], $removeIds, true)) {
                        $changed = true;
                        continue;
                    }
                    $group[] = $entry;
                }
                if (count($group) > 1) {
                    $result[] = $group;
                } else {
                    $changed = true;
                }
                continue;
            }

            if (isset($item['id']) && in_array((int) $item['id'], $removeIds, true)) {
                $changed = true;
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }
}
