<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * В сравнении тарифов «Анализ текста» показывает проверки / сохранения
 * (сохранения = лимит истории уникальности из UI анализатора).
 * Уникальность — отдельная строка только про проверки.
 */
class AlignTextAnalyzerTariffCompareWithSaves extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'TextAnalyzer')
            ->update([
                'name' => 'Анализ текста страницы (проверки / сохранения)',
                'description' => 'Лимит проверок анализа текста в месяц. В таблице тарифов рядом — сохранения проверок уникальности из этого модуля (на Free история недоступна).',
                'updated_at' => now(),
            ]);

        DB::table('tariff_settings')
            ->where('code', 'TextUniqueness')
            ->update([
                'name' => 'Уникальность текста (проверки)',
                'description' => 'Лимит в месяц: режим «интернет» — 1 лимит за поисковый зонд фрагмента; режим «URL» — 1 лимит за страницу.',
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'TextAnalyzer')
            ->update([
                'name' => 'Анализ текста страницы (проверки)',
                'updated_at' => now(),
            ]);

        DB::table('tariff_settings')
            ->where('code', 'TextUniqueness')
            ->update([
                'name' => 'Уникальность текста (проверки / сохранения)',
                'updated_at' => now(),
            ]);
    }
}
