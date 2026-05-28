<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Удаление модуля SEO-чеклистов (/checklist) и всех связанных таблиц.
 */
class DropChecklistModule extends Migration
{
    private const TABLES = [
        'checklist_relation_with_monitoring',
        'checklist_notification',
        'checklist_project_checklist_label',
        'checklist_tasks',
        'checklists_stubs',
        'checklist_projects',
        'check_lists_labels',
    ];

    public function up(): void
    {
        DB::table('main_projects')
            ->where('link', 'like', '%/checklist%')
            ->orWhere('controller', 'like', '%CheckList%')
            ->delete();

        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Восстановление — только через исторические миграции 2023–2024.
    }
}
