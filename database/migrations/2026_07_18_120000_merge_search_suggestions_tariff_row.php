<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename SearchSuggestions for a single compare-table row
 * (history stays in DB, UI merges monthly + saved into one line).
 */
class MergeSearchSuggestionsTariffRow extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'SearchSuggestions')
            ->update([
                'name' => 'Сбор поисковых подсказок (проектов / сохранений результатов)',
                'description' => 'Лимит в месяц: 1 исходная фраза в одной ПС = 1. В таблице тарифов рядом — число сохранённых проверок (на Free история недоступна).',
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'SearchSuggestions')
            ->update([
                'name' => 'Сбор поисковых подсказок (лимит в месяц)',
                'description' => '1 исходная фраза в одной поисковой системе = 1 лимит. Яндекс и Google считаются отдельно.',
                'updated_at' => now(),
            ]);
    }
}
