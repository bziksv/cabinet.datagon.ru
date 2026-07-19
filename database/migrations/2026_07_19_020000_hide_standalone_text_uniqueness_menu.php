<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Уникальность текста встроена в «Анализ текста» — скрываем отдельный пункт меню.
 */
class HideStandaloneTextUniquenessMenu extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        DB::table('main_projects')
            ->where('link', 'like', '%/text-uniqueness%')
            ->update([
                'show' => 0,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        DB::table('main_projects')
            ->where('link', 'like', '%/text-uniqueness%')
            ->update([
                'show' => 1,
                'updated_at' => now(),
            ]);
    }
}
