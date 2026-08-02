<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSeoReportsMenuAndPermission extends Migration
{
    public function up(): void
    {
        $this->seedMenuItem();
        $this->seedPermission();
    }

    public function down(): void
    {
        $this->removeMenuItem();
        $this->removePermission();
    }

    private function seedMenuItem(): void
    {
        if (!Schema::hasTable('main_projects')) {
            return;
        }

        $exists = DB::table('main_projects')
            ->where('link', 'like', '%/seo-reports%')
            ->exists();
        if ($exists) {
            return;
        }

        $parent = DB::table('main_projects')->where('link', 'like', '%/seo-checklist%')->first()
            ?: DB::table('main_projects')->where('link', 'like', '%/site-audit%')->first()
            ?: DB::table('main_projects')->where('id', 13)->first();
        if ($parent === null) {
            return;
        }

        $maxPos = (int) DB::table('main_projects')->max('position');
        $position = max($maxPos + 1, ((int) ($parent->position ?? 180)) + 1);

        $newId = DB::table('main_projects')->insertGetId([
            'access' => $parent->access,
            'controller' => "SeoReportsController@index\r\n",
            'color' => '#1d4ed8',
            'title' => 'SEO Reports',
            'description' => 'Клиентские SEO-отчёты: трафик, позиции, работы, PDF и публичная ссылка.',
            'link' => 'https://lk.redbox.su/seo-reports',
            'icon' => '<i class="fas fa-file-alt"></i>',
            'show' => 1,
            'position' => $position,
            'buttons' => $parent->buttons ?? '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertAfterProjectIdInUserMenus((int) $parent->id, (int) $newId);
    }

    private function removeMenuItem(): void
    {
        if (!Schema::hasTable('main_projects')) {
            return;
        }

        $ids = DB::table('main_projects')
            ->where('link', 'like', '%/seo-reports%')
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $this->purgeMenuPositions($ids->all());
        DB::table('main_projects')->whereIn('id', $ids)->delete();
    }

    private function seedPermission(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        if (DB::table('permissions')->where('name', 'SEO Reports')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'SEO Reports',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assignPermissionLikeSiteAudit();
    }

    private function removePermission(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', 'SEO Reports')->value('id');
        if (!$id) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }

    private function assignPermissionLikeSiteAudit(): void
    {
        if (!Schema::hasTable('role_has_permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $newId = DB::table('permissions')->where('name', 'SEO Reports')->value('id');
        if (!$newId) {
            return;
        }

        $likeId = DB::table('permissions')->where('name', 'SEO Checklist')->value('id')
            ?: DB::table('permissions')->where('name', 'Site audit')->value('id');

        $roleIds = collect();
        if ($likeId) {
            $roleIds = $roleIds->merge(
                DB::table('role_has_permissions')
                    ->where('permission_id', $likeId)
                    ->pluck('role_id')
            );
        }

        $roleIds = $roleIds->merge(
            DB::table('roles')
                ->whereIn('name', [
                    'admin', 'user', 'Super Admin',
                    'Free', 'Optimal', 'Maximum', 'Ultimate',
                ])
                ->pluck('id')
        )->unique()->filter();

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $newId)
                ->where('role_id', $roleId)
                ->exists();
            if (!$exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $newId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    private function insertAfterProjectIdInUserMenus(int $afterId, int $newId): void
    {
        if (!Schema::hasTable('menu_items_position')) {
            return;
        }

        DB::table('menu_items_position')->orderBy('id')->chunk(50, function ($rows) use ($afterId, $newId) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }
                $positions = json_decode($row->positions, true);
                if (!is_array($positions)) {
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
            if (isset($item[0]) && is_array($item[0]) && !empty($item[0]['dir'])) {
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
            if (isset($item[0]) && is_array($item[0]) && !empty($item[0]['dir'])) {
                $group = [];
                $groupChanged = false;
                foreach ($item as $entry) {
                    $group[] = $entry;
                    if (isset($entry['id']) && (int) $entry['id'] === $afterId) {
                        $group[] = ['id' => $newId];
                        $groupChanged = true;
                        $changed = true;
                    }
                }
                $result[] = $groupChanged ? $group : $item;
                continue;
            }

            $result[] = $item;
            if (isset($item['id']) && (int) $item['id'] === $afterId) {
                $result[] = ['id' => $newId];
                $changed = true;
            }
        }

        if (!$changed) {
            $result[] = ['id' => $newId];
            $changed = true;
        }

        return $result;
    }

    private function purgeMenuPositions(array $ids): void
    {
        if (!Schema::hasTable('menu_items_position') || $ids === []) {
            return;
        }

        DB::table('menu_items_position')->orderBy('id')->chunk(50, function ($rows) use ($ids) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }
                $positions = json_decode($row->positions, true);
                if (!is_array($positions)) {
                    continue;
                }
                $filtered = $this->filterIdsFromPositions($positions, $ids);
                if ($filtered !== $positions) {
                    DB::table('menu_items_position')
                        ->where('id', $row->id)
                        ->update(['positions' => json_encode($filtered)]);
                }
            }
        });
    }

    private function filterIdsFromPositions(array $positions, array $ids): array
    {
        $idMap = array_fill_keys(array_map('intval', $ids), true);
        $result = [];
        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && !empty($item[0]['dir'])) {
                $group = [];
                foreach ($item as $entry) {
                    if (isset($entry['id']) && isset($idMap[(int) $entry['id']])) {
                        continue;
                    }
                    $group[] = $entry;
                }
                if (count($group) > 0) {
                    $result[] = $group;
                }
                continue;
            }
            if (isset($item['id']) && isset($idMap[(int) $item['id']])) {
                continue;
            }
            $result[] = $item;
        }

        return $result;
    }
}
