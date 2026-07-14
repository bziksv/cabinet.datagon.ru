<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedRelevanceTlpAndMonitoringTableNews extends Migration
{
    private const AUTHOR_ID = 4;

    /** @var list<string> */
    private const DATES = [
        '2026-07-12 11:00:00',
        '2026-07-13 14:00:00',
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
                'created_at' => '2026-07-12 11:00:00',
                'updated_at' => '2026-07-12 11:00:00',
                'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>TLP точнее группирует словоформы; повторный анализ посадочной стал в 2–3 раза быстрее</strong> — обновления в анализаторе релевантности:</p>
<ul>
<li><strong>Группировка словоформ в TLP</strong> — омонимы вроде «участка» / «участков» объединяются в одну группу «участок» по контексту корпуса конкурентов, а не только по первой лемме phpMorphy.</li>
<li><strong>Быстрый «Повторный анализ посадочной»</strong> — пересчитывается только ваша страница, данные конкурентов берутся из сохранённого снимка (~20 с вместо ~55 с полного прогона).</li>
<li><strong>Очередь на странице отчёта</strong> — баннер «в очереди» под кнопками анализа, тосты о готовности и ошибке; при открытии истории больше нет ложного «готово».</li>
<li><strong>Список проектов</strong> — исправлено отображение «Станислав undefined» в колонке владельца.</li>
<li><strong>История анализов</strong> — быстрее раскрывается список прогонов (оптимизация загрузки данных).</li>
</ul>
<p>Для актуальной группировки словоформ в TLP запустите анализ заново или «Повторный анализ посадочной» — старые отчёты в истории сохраняют снимок на момент прогона. После повторного анализа открывайте ссылку на новый прогон из уведомления.</p>
<p>Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>При обнаружении ошибок просим писать в <a href="/support">службу поддержки</a>. Идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
HTML,
            ],
            [
                'created_at' => '2026-07-13 14:00:00',
                'updated_at' => '2026-07-13 14:00:00',
                'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>Таблица мониторинга позиций: загрузка за секунду и удобнее работа со строками</strong> — доработали экран ключевых слов проекта:</p>
<ul>
<li><strong>Скорость</strong> — загрузка таблицы ключевых слов с ~15 с до ~1 с: оптимизировали SQL, добавили кэш, убрали лишние запросы.</li>
<li><strong>Подсветка строки</strong> — при наведении единая серая подсветка на всей строке, включая фиксированную колонку «Запрос» и зелёные ячейки позиций (без «пятен»).</li>
<li><strong>Вёрстка таблицы</strong> — починена пустая область при смене числа строк на странице; стабильнее работают фиксированные колонки и горизонтальная прокрутка.</li>
<li><strong>Uptime мониторинга сайтов</strong> — корректный расчёт доступности после сброса статистики по проекту.</li>
</ul>
<p>Откройте любой проект в <strong>«Мониторинг позиций»</strong> → таб «Ключевые слова», чтобы оценить изменения. Если таблица открывается медленно — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>При обнаружении ошибок просим писать в <a href="/support">службу поддержки</a>. Идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
HTML,
            ],
        ];
    }
}
