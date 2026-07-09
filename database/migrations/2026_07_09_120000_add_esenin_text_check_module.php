<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddEseninTextCheckModule extends Migration
{
    private const TEXT_ANALYZER_PROJECT_ID = 15;

    private const TARIFF_LIMITS = [
        'Free' => 5,
        'Optimal' => 200,
        'Ultimate' => 500,
        'Maximum' => 1000,
    ];

    public function up(): void
    {
        Schema::create('esenin_text_check_usages', function (Blueprint $table) {
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

        Schema::dropIfExists('esenin_text_check_usages');
    }

    private function seedTariffLimit(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $exists = DB::table('tariff_settings')->where('code', 'EseninTextCheck')->exists();
        if ($exists) {
            return;
        }

        $settingId = DB::table('tariff_settings')->insertGetId([
            'name' => 'Проверка текста Есенин (лимит в месяц)',
            'code' => 'EseninTextCheck',
            'description' => '1 проверка текста или страницы = 1 лимит. Локальный анализ SEO-текста.',
            'message' => 'Лимит проверок «Есенин» исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::TARIFF_LIMITS as $tariff => $value) {
            DB::table('tariff_setting_values')->insert([
                'tariff_setting_id' => $settingId,
                'tariff' => $tariff,
                'value' => $value,
                'sort' => 500,
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

        $settingId = DB::table('tariff_settings')->where('code', 'EseninTextCheck')->value('id');
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

        $exists = DB::table('main_projects')
            ->where('link', 'like', '%/esenin-text-check%')
            ->exists();

        if ($exists) {
            return;
        }

        $parent = DB::table('main_projects')->where('id', self::TEXT_ANALYZER_PROJECT_ID)->first();
        if ($parent === null) {
            return;
        }

        $newId = DB::table('main_projects')->insertGetId([
            'access' => $parent->access,
            'controller' => "EseninTextCheckController@index\r\n",
            'color' => '#6f42c1',
            'title' => 'Esenin text check',
            'description' => 'Оценка риска «Баден-Баден» для SEO-текстов.',
            'link' => 'https://cabinet.titlo.ru/esenin-text-check',
            'icon' => '<i class="fas fa-spell-check"></i>',
            'show' => 1,
            'position' => ((int) ($parent->position ?? 80)) + 1,
            'buttons' => $parent->buttons ?? '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertAfterProjectIdInUserMenus(self::TEXT_ANALYZER_PROJECT_ID, (int) $newId);
    }

    private function removeMenuItem(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        $ids = DB::table('main_projects')
            ->where('link', 'like', '%/esenin-text-check%')
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

        $exists = DB::table('permissions')->where('name', 'Esenin text check')->exists();
        if ($exists) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'Esenin text check',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assignPermissionLikeTextAnalyzer();
    }

    private function assignPermissionLikeTextAnalyzer(): void
    {
        if (! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $eseninId = DB::table('permissions')->where('name', 'Esenin text check')->value('id');
        $textAnalyzerId = DB::table('permissions')->where('name', 'Text analyzer')->value('id');

        if (! $eseninId || ! $textAnalyzerId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $textAnalyzerId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $eseninId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $eseninId,
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

        DB::table('permissions')->where('name', 'Esenin text check')->delete();
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
