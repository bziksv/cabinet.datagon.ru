<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSearchSuggestionsModule extends Migration
{
    private const INDEX_CHECK_PROJECT_ID = 40;

    private const MONTHLY_LIMITS = [
        'Free' => 3,
        'Optimal' => 300,
        'Ultimate' => 1500,
        'Maximum' => 2400,
    ];

    /** Сохранённые проверки: Free = 0 (история только у платных). */
    private const HISTORY_LIMITS = [
        'Free' => 0,
        'Optimal' => 10,
        'Ultimate' => 20,
        'Maximum' => 50,
    ];

    public function up(): void
    {
        Schema::create('search_suggestions_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('period', 7);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'period']);
            $table->index('period');
        });

        Schema::create('search_suggestions_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title', 255)->nullable();
            $table->json('params');
            $table->json('results');
            $table->unsignedInteger('seeds_count')->default(0);
            $table->unsignedInteger('results_count')->default(0);
            $table->unsignedInteger('cost')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        $this->seedTariffLimits();
        $this->seedMenuItem();
        $this->seedPermission();
    }

    public function down(): void
    {
        $this->removeMenuItem();
        $this->removeTariffLimits();
        $this->removePermission();

        Schema::dropIfExists('search_suggestions_histories');
        Schema::dropIfExists('search_suggestions_usages');
    }

    private function seedTariffLimits(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $this->seedTariffSetting(
            'SearchSuggestions',
            'Сбор поисковых подсказок (проектов / сохранений результатов)',
            'Лимит в месяц: 1 исходная фраза в одной ПС = 1. В таблице тарифов рядом — число сохранённых проверок (на Free история недоступна).',
            'Лимит сбора подсказок исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
            self::MONTHLY_LIMITS,
            510
        );

        // Not shown as a separate compare row — merged into SearchSuggestions in tariff/index.
        $this->seedTariffSetting(
            'SearchSuggestionsHistory',
            'Сбор подсказок — сохранённых проверок',
            'Сколько готовых сборов можно хранить в истории. На Free история недоступна (0).',
            'Достигнут лимит сохранённых проверок ({VALUE}). Удалите старые или повысьте тариф.',
            self::HISTORY_LIMITS,
            511
        );
    }

    private function seedTariffSetting(
        string $code,
        string $name,
        string $description,
        string $message,
        array $limits,
        int $sort
    ): void {
        if (DB::table('tariff_settings')->where('code', $code)->exists()) {
            return;
        }

        $settingId = DB::table('tariff_settings')->insertGetId([
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'message' => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($limits as $tariff => $value) {
            DB::table('tariff_setting_values')->insert([
                'tariff_setting_id' => $settingId,
                'tariff' => $tariff,
                'value' => $value,
                'sort' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function removeTariffLimits(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        foreach (['SearchSuggestions', 'SearchSuggestionsHistory'] as $code) {
            $settingId = DB::table('tariff_settings')->where('code', $code)->value('id');
            if (! $settingId) {
                continue;
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
    }

    private function seedMenuItem(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        $exists = DB::table('main_projects')
            ->where('link', 'like', '%/search-suggestions%')
            ->exists();

        if ($exists) {
            return;
        }

        $parent = DB::table('main_projects')->where('id', self::INDEX_CHECK_PROJECT_ID)->first();
        if ($parent === null) {
            $parent = DB::table('main_projects')->where('id', 11)->first();
        }
        if ($parent === null) {
            return;
        }

        $newId = DB::table('main_projects')->insertGetId([
            'access' => $parent->access,
            'controller' => "SearchSuggestionsController@index\r\n",
            'color' => '#0d6efd',
            'title' => 'Search suggestions',
            'description' => 'Сбор поисковых подсказок в Яндексе и Google.',
            'link' => 'https://lk.redbox.su/search-suggestions',
            'icon' => '<i class="fas fa-lightbulb"></i>',
            'show' => 1,
            'position' => ((int) ($parent->position ?? 120)) + 1,
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
            ->where('link', 'like', '%/search-suggestions%')
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

        if (DB::table('permissions')->where('name', 'Search suggestions')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'Search suggestions',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assignPermissionLikeIndexCheck();
    }

    private function assignPermissionLikeIndexCheck(): void
    {
        if (! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $newId = DB::table('permissions')->where('name', 'Search suggestions')->value('id');
        $likeId = DB::table('permissions')->where('name', 'Index check')->value('id')
            ?: DB::table('permissions')->where('name', 'Http headers')->value('id');

        if (! $newId || ! $likeId) {
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

        DB::table('permissions')->where('name', 'Search suggestions')->delete();
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

                if (count($group) > 1 || $groupChanged) {
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
