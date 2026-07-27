<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Дайджест пользовательских обновлений после новости от 19.07.2026
 * (админские доработки не освещаем).
 */
class SeedJulyCabinetUpdatesNews extends Migration
{
    private const AUTHOR_ID = 4;

    /** @var list<string> */
    private const DATES = [
        '2026-07-27 18:00:00',
    ];

    public function up(): void
    {
        foreach ($this->items() as $item) {
            $exists = DB::table('news')
                ->where('user_id', self::AUTHOR_ID)
                ->where('created_at', $item['created_at'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('news')->insert([
                'user_id' => self::AUTHOR_ID,
                'content' => $item['content'],
                'files' => null,
                'number_of_likes' => 0,
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->whereIn('created_at', self::DATES)
            ->delete();
    }

    /**
     * @return array<int, array{created_at: string, updated_at: string, content: string}>
     */
    private function items(): array
    {
        return [
            [
                'created_at' => '2026-07-27 18:00:00',
                'updated_at' => '2026-07-27 18:00:00',
                'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>Обновления кабинета с 19 июля</strong> — кратко о том, что появилось и стало удобнее в повседневной работе.</p>

<p><strong>1. Новый модуль «Аудит сайта» (бета, версия 0.3.22)</strong></p>
<ul>
<li><strong>Технический краул</strong> — sitemap + robots.txt, обход страниц и сводка находок по приоритетам.</li>
<li><strong>Отчёты и сравнение</strong> — детальные отчёты по блокам; повторные краулы с учётом неизменённого контента; сравнение снимков.</li>
<li><strong>Публичная ссылка</strong> — можно отдать клиенту отчёт без входа в кабинет; опционально свой логотип (white-label).</li>
<li><strong>Дополнительно в отчёте</strong> — внешний антиплагиат, каннибализация по сниппетам выдачи, быстрые переходы к связанным проверкам (мета, индексация, Есенин, HTTP-заголовки) и связка с анализатором релевантности.</li>
<li><strong>Очередь</strong> — если краул занят, новый запуск встаёт в ожидание и стартует сам, когда освободится слот.</li>
</ul>
<p>Модуль в меню: <strong><a href="https://cabinet.titlo.ru/site-audit">cabinet.titlo.ru/site-audit</a></strong>. Модуль в бете — замечания и идеи ждём в <a href="/support">поддержке</a> и <a href="/ideas">идеях</a>.</p>

<p><strong>2. Анализатор релевантности</strong></p>
<ul>
<li><strong>Google в выдаче</strong> — удобнее работа с SERP Google наряду с Яндексом.</li>
<li><strong>Доступ к проектам</strong> — переработан экран шаринга: кому и какой доступ выдан — понятнее с первого взгляда.</li>
<li><strong>Публичная ссылка</strong> — можно задать срок жизни (30 / 90 / 180 / 365 дней или бессрочно).</li>
<li><strong>Теги в истории</strong> — теги проекта сохраняются и отображаются в истории анализов.</li>
<li><strong>Форма полного анализа</strong> — если не заполнены посадочная или ключевая фраза, поля подсвечиваются, появляется понятное сообщение об ошибке.</li>
<li><strong>Статистика проектов</strong> — таблица нормально прокручивается по горизонтали, стрелки сортировки больше не «ломаются».</li>
</ul>
<p>Раздел: <strong><a href="https://cabinet.titlo.ru/analyze-relevance">cabinet.titlo.ru/analyze-relevance</a></strong>.</p>

<p><strong>3. Отслеживание срока регистрации домена</strong></p>
<ul>
<li><strong>Без дублей</strong> — один и тот же домен нельзя добавить дважды (ни по одному, ни списком).</li>
<li><strong>Выбрать все</strong> — чекбокс в шапке таблицы отмечает отфильтрованные строки на всех страницах — удобно для массовой проверки и удаления.</li>
<li><strong>Напоминания о сроке</strong> — снова корректно отрабатывают проверки по расписанию и оповещения об истечении.</li>
</ul>
<p>Раздел: <strong><a href="https://cabinet.titlo.ru/domain-information">cabinet.titlo.ru/domain-information</a></strong>.</p>

<p><strong>4. Другие модули</strong></p>
<ul>
<li><strong>Проверка ссылок</strong> — пакетная проверка, опция без анкора, стабильнее счётчик битых ссылок.</li>
<li><strong>Кластеризация</strong> — поисковая система Google; подчистили формулировки в интерфейсе.</li>
<li><strong>Типы сайтов в выдаче</strong> — пресеты каталогов доменов для быстрой настройки.</li>
<li><strong>Анализ конкурентов</strong> — надёжнее скачивание и отрисовка страниц, удобнее работа с SERP.</li>
<li><strong>Лента новостей</strong> — у постов и комментариев теперь точная дата и время публикации (вместо «N дней назад»).</li>
</ul>

<p>Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>При обнаружении ошибок просим писать в <a href="/support">службу поддержки</a>. Идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
HTML,
            ],
        ];
    }
}
