<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Убрать «(новый)» из пункта меню /monitoring-v2.
 */
class RenameMonitoringV2MenuTitle extends Migration
{
    public function up(): void
    {
        DB::table('main_projects')
            ->where('link', 'like', '%/monitoring-v2%')
            ->update([
                'title' => 'Position monitoring',
                'description' => 'Мониторинг позиций сайта в поисковых системах.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('main_projects')
            ->where('link', 'like', '%/monitoring-v2%')
            ->update([
                'title' => 'Мониторинг позиций (новый)',
                'description' => 'Экспериментальный список проектов мониторинга позиций (/monitoring-v2).',
                'updated_at' => now(),
            ]);
    }
}
