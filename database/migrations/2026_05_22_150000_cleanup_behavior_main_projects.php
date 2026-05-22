<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Дополнение к drop_behavior_module: в main_projects часто controller=null, только link /behavior.
 */
class CleanupBehaviorMainProjects extends Migration
{
    public function up(): void
    {
        $projectIds = DB::table('main_projects')
            ->where(function ($query) {
                $query->where('controller', 'like', '%BehaviorController%')
                    ->orWhere('link', 'like', '%/behavior%')
                    ->orWhere('link', 'like', '%behavior%')
                    ->orWhere('title', 'like', '%Behavioral%')
                    ->orWhere('title', 'like', '%поведенческ%')
                    ->orWhere('title', 'like', '%репутац%');
            })
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('visit_statistics')) {
            DB::table('visit_statistics')
                ->whereIn('project_id', $projectIds)
                ->delete();
        }

        DB::table('main_projects')
            ->whereIn('id', $projectIds)
            ->delete();

        $this->purgeMenuPositions($projectIds);
    }

    public function down(): void
    {
    }

    private function purgeMenuPositions($projectIds): void
    {
        if (!Schema::hasTable('menu_items_position')) {
            return;
        }

        $ids = $projectIds->map(static function ($id) {
            return (int) $id;
        })->all();

        DB::table('menu_items_position')->orderBy('id')->chunk(50, static function ($rows) use ($ids) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }

                $positions = json_decode($row->positions, true);
                if (!is_array($positions)) {
                    continue;
                }

                $changed = false;
                $filtered = self::filterPositions($positions, $ids, $changed);

                if ($changed) {
                    DB::table('menu_items_position')
                        ->where('id', $row->id)
                        ->update(['positions' => json_encode($filtered)]);
                }
            }
        });
    }

    private static function filterPositions(array $positions, array $removeIds, bool &$changed): array
    {
        $result = [];

        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && !empty($item[0]['dir'])) {
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
