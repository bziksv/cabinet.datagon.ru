<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDomainRecordsModule extends Migration
{
    private const PARENT_PROJECT_ID = 14; // Срок регистрации доменов

    private const MONTHLY_LIMITS = [
        'Free' => 20,
        'Optimal' => 600,
        'Ultimate' => 2000,
        'Maximum' => 5000,
    ];

    public function up(): void
    {
        Schema::create('domain_records_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('period', 7);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'period']);
            $table->index('period');
        });

        $this->seedTariffLimit();
        $this->seedMenuItem();
        $this->seedPermission();
    }

    public function down(): void
    {
        $this->removeMenuItem();
        $this->removeTariffLimit();
        $this->removePermission();
        Schema::dropIfExists('domain_records_usages');
    }

    private function seedTariffLimit(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        if (DB::table('tariff_settings')->where('code', 'DomainRecords')->exists()) {
            return;
        }

        $settingId = DB::table('tariff_settings')->insertGetId([
            'name' => 'Записи домена (лимит в месяц)',
            'code' => 'DomainRecords',
            'description' => '1 проверка домена (WHOIS + DNS) = 1 лимит.',
            'message' => 'Лимит проверок записей домена исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::MONTHLY_LIMITS as $tariff => $value) {
            DB::table('tariff_setting_values')->insert([
                'tariff_setting_id' => $settingId,
                'tariff' => $tariff,
                'value' => $value,
                'sort' => 520,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function removeTariffLimit(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'DomainRecords')->value('id');
        if (! $settingId) {
            return;
        }

        if (Schema::hasTable('tariff_setting_user_values')) {
            $valueIds = DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $settingId)
                ->pluck('id');
            if ($valueIds->isNotEmpty()) {
                DB::table('tariff_setting_user_values')
                    ->whereIn('tariff_setting_value_id', $valueIds)
                    ->delete();
            }
        }

        DB::table('tariff_setting_values')->where('tariff_setting_id', $settingId)->delete();
        DB::table('tariff_settings')->where('id', $settingId)->delete();
    }

    private function seedMenuItem(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        if (DB::table('main_projects')->where('link', 'like', '%/domain-records%')->exists()) {
            return;
        }

        $parent = DB::table('main_projects')->where('id', self::PARENT_PROJECT_ID)->first()
            ?: DB::table('main_projects')->where('id', 11)->first();
        if ($parent === null) {
            return;
        }

        $newId = DB::table('main_projects')->insertGetId([
            'access' => $parent->access,
            'controller' => "DomainRecordsController@index\r\n",
            'color' => '#0f766e',
            'title' => 'Domain records',
            'description' => 'WHOIS и DNS записи домена. Добавление в мониторинг сайтов и срок регистрации.',
            'link' => 'https://lk.redbox.su/domain-records',
            'icon' => '<i class="fas fa-globe"></i>',
            'show' => 1,
            'position' => ((int) ($parent->position ?? 100)) + 1,
            'buttons' => $parent->buttons ?? '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertAfterProjectIdInUserMenus((int) $parent->id, (int) $newId);
    }

    private function removeMenuItem(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        $ids = DB::table('main_projects')
            ->where('link', 'like', '%/domain-records%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $this->purgeMenuPositions($ids->all());
        DB::table('main_projects')->whereIn('id', $ids)->delete();
    }

    private function seedPermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        if (DB::table('permissions')->where('name', 'Domain records')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'Domain records',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newId = DB::table('permissions')->where('name', 'Domain records')->value('id');
        $likeId = DB::table('permissions')->where('name', 'Domain information')->value('id')
            ?: DB::table('permissions')->where('name', 'Http headers')->value('id');

        if (! $newId || ! $likeId || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $likeId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $newId)
                ->where('role_id', $roleId)
                ->exists();
            if (! $exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $newId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    private function removePermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->where('name', 'Domain records')->delete();
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
                if (! is_array($positions) || $this->positionsContainId($positions, $newId)) {
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
                foreach ($item as $entry) {
                    $group[] = $entry;
                    if (isset($entry['id']) && (int) $entry['id'] === $afterId) {
                        $group[] = ['id' => $newId];
                        $changed = true;
                    }
                }
                $result[] = $group;
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
