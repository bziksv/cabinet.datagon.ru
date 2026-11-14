<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedYandexNightWindowNoticeNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-11-14 18:30:00';

    public function up(): void
    {
        if (! Schema::hasTable('news')) {
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
<p>Доброго вечера!</p>
<p><strong>Ограничение Яндекса: запросы только с 00:00 до 06:00 (МСК)</strong> — из‑за ограничений со стороны Яндекса сейчас все запросы к нему возможны только в этом ночном окне.</p>
<p><strong>Что рекомендуем</strong></p>
<ul>
<li><strong>Анализ релевантности</strong> — используйте <strong>Google</strong>.</li>
<li><strong>Мониторинг позиций</strong> — переведите съём на <strong>ночное время</strong> (00:00–06:00 по Москве).</li>
</ul>
<p>Когда Яндекс снимет ограничение, <strong>дополнительно напишем в новостях</strong>.</p>
<p>Если по конкретному проекту съём «дырявый» или зависает — напишите в <a href="/support">службу поддержки</a>.</p>
HTML
            ,
            'files' => null,
            'number_of_likes' => 0,
            'created_at' => self::PUBLISHED_AT,
            'updated_at' => self::PUBLISHED_AT,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('news')) {
            return;
        }

        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->delete();
    }
}
