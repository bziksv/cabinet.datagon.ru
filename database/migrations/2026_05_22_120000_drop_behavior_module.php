<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropBehaviorModule extends Migration
{
    public function up(): void
    {
        $this->removeTariffSettings();
        $this->removePermissions();
        $this->removeMainProjects();
        $this->dropBehaviorTables();
    }

    public function down(): void
    {
        // Модуль удалён намеренно; восстановление — только из бэкапа БД.
    }

    private function removeTariffSettings(): void
    {
        $settingIds = DB::table('tariff_settings')
            ->where('code', 'behavior')
            ->pluck('id');

        if ($settingIds->isEmpty()) {
            return;
        }

        $valueIds = DB::table('tariff_setting_values')
            ->whereIn('tariff_setting_id', $settingIds)
            ->pluck('id');

        if ($valueIds->isNotEmpty()) {
            DB::table('tariff_setting_user_values')
                ->whereIn('tariff_setting_value_id', $valueIds)
                ->delete();

            DB::table('tariff_setting_values')
                ->whereIn('id', $valueIds)
                ->delete();
        }

        DB::table('tariff_settings')
            ->whereIn('id', $settingIds)
            ->delete();
    }

    private function removePermissions(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('name', 'Behavior')
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('model_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }

    private function removeMainProjects(): void
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
    }

    private function dropBehaviorTables(): void
    {
        Schema::dropIfExists('behaviors_phrases');
        Schema::dropIfExists('behaviors');
    }
}
