<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedSeoReportsModuleNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-08-02 00:30:00';

    public function up(): void
    {
        if (!Schema::hasTable('news')) {
            return;
        }

        $exists = DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('news')->insert([
            'user_id' => self::AUTHOR_ID,
            'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>Запустили модуль «SEO отчёты»</strong> — клиентский отчёт по сайту в один клик:</p>
<ul>
<li>Метрика, позиции, конверсии, KPI-цели и инсайты;</li>
<li>публичная ссылка с PIN, режим презентации и lite-дашборд;</li>
<li>экспорт PDF и пакет ZIP (PDF + CSV);</li>
<li>блоки Titlo: аудит, чеклист, релевантность, доступность.</li>
</ul>
<p>Раздел в меню: <strong><a href="https://cabinet.titlo.ru/seo-reports">cabinet.titlo.ru/seo-reports</a></strong>.</p>
<p>При ошибках пишите в <a href="/support">поддержку</a>.</p>
HTML,
            'files' => null,
            'number_of_likes' => 0,
            'created_at' => self::PUBLISHED_AT,
            'updated_at' => self::PUBLISHED_AT,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('news')) {
            return;
        }
        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->delete();
    }
}
