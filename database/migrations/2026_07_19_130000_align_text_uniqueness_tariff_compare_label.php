<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Уникальность в UI анализа текста: в таблице тарифов одна строка
 * «проверки / сохранения», как у PhraseCommerce / SiteTypes.
 */
class AlignTextUniquenessTariffCompareLabel extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'TextUniqueness')
            ->update([
                'name' => 'Уникальность текста (проверки / сохранения)',
                'description' => 'Лимит в месяц: 1 зонд фрагмента = 1. В таблице тарифов рядом — число сохранённых проверок (на Free история недоступна).',
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'TextUniqueness')
            ->update([
                'name' => 'Уникальность текста (проверки)',
                'description' => 'Лимит в месяц: режим «интернет» — 1 лимит за поисковый зонд фрагмента; режим «URL» — 1 лимит за страницу.',
                'updated_at' => now(),
            ]);
    }
}
