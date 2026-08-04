<?php

namespace App\SeoReports;

/**
 * Показатели внутри секций отчёта (вкл/выкл в шаблоне).
 * Ключи совпадают со snapshot / blade, где возможно.
 *
 * preview: тип миниатюры «как будет в отчёте» рядом с галочкой.
 */
class SeoReportMetricRegistry
{
    /**
     * @return array<string, list<array{key:string,label:string,preview?:string,sample?:string}>>
     */
    public static function catalog(): array
    {
        return [
            'traffic' => [
                ['key' => 'visits', 'label' => 'Визиты', 'preview' => 'kpi', 'sample' => '18 420  +11%'],
                ['key' => 'users', 'label' => 'Пользователи', 'preview' => 'kpi', 'sample' => '13 840  +9%'],
                ['key' => 'pageviews', 'label' => 'Просмотры', 'preview' => 'kpi', 'sample' => '64 280  +17%'],
                ['key' => 'bounce_rate', 'label' => 'Отказы', 'preview' => 'kpi', 'sample' => '24%  −3'],
                ['key' => 'page_depth', 'label' => 'Глубина', 'preview' => 'kpi', 'sample' => '3,49  +6%'],
                ['key' => 'avg_visit_duration', 'label' => 'Время на сайте', 'preview' => 'kpi', 'sample' => '02:56  +9%'],
                ['key' => 'series_users', 'label' => 'График пользователей по дням', 'preview' => 'chart'],
                ['key' => 'channels', 'label' => 'Каналы', 'preview' => 'table', 'sample' => "Поиск\t908\nПрямые\t210"],
                ['key' => 'channel_months', 'label' => 'Каналы по месяцам', 'preview' => 'table'],
                ['key' => 'sources', 'label' => 'Источники', 'preview' => 'table'],
                ['key' => 'search', 'label' => 'Поисковый трафик', 'preview' => 'kpi', 'sample' => '908  −4%'],
                ['key' => 'devices', 'label' => 'Устройства', 'preview' => 'table'],
                ['key' => 'geo', 'label' => 'География', 'preview' => 'table'],
                ['key' => 'landings', 'label' => 'Топ посадочных', 'preview' => 'table', 'sample' => "/catalog/…\t420\n/\t310"],
                ['key' => 'landings_search', 'label' => 'Посадочные из поиска', 'preview' => 'table'],
                ['key' => 'landings_social', 'label' => 'Посадочные из соцсетей', 'preview' => 'table'],
                ['key' => 'comment', 'label' => 'Комментарий к трафику', 'preview' => 'text'],
            ],
            'positions' => [
                ['key' => 'summary', 'label' => 'Сводка: сколько в TOP-3 / 10 / 30 / 100', 'preview' => 'baskets', 'sample' => 'TOP-10: 42'],
                ['key' => 'dynamics', 'label' => 'Динамика: рост / без изменений / падение', 'preview' => 'dynamics', 'sample' => '↑12  →48  ↓7'],
                ['key' => 'top_baskets', 'label' => 'Диаграмма: запросы в TOP-3 / 10 / 30 / 100', 'preview' => 'baskets'],
                ['key' => 'visibility_by_engine', 'label' => 'Видимость по Яндексу и Google', 'preview' => 'table'],
                ['key' => 'visibility_series', 'label' => 'График доли запросов в TOP-10', 'preview' => 'chart'],
                [
                    'key' => 'phrases_improved',
                    'label' => 'Выросшие запросы',
                    'preview' => 'phrases',
                    'sample' => "купить стетоскоп\t18 → 9",
                ],
                [
                    'key' => 'phrases_worsened',
                    'label' => 'Упавшие запросы',
                    'preview' => 'phrases',
                    'sample' => "фонендоскоп цена\t7 → 19",
                ],
                ['key' => 'by_engine', 'label' => 'Сводка по Яндексу и Google', 'preview' => 'table'],
                [
                    'key' => 'quick_wins',
                    'label' => 'Почти в TOP-10 (позиции 8–20)',
                    'preview' => 'phrases',
                    'sample' => "запрос\t11 → 8",
                ],
                [
                    'key' => 'risk',
                    'label' => 'Сильно просевшие (−5 позиций и больше)',
                    'preview' => 'phrases',
                    'sample' => "запрос\t3 → 12",
                ],
                [
                    'key' => 'groups',
                    'label' => 'Группы запросов: TOP-3 / 10 / 30 / 100',
                    'preview' => 'baskets',
                    'sample' => 'TOP-10: 28/80',
                ],
                ['key' => 'competitors', 'label' => 'Конкуренты', 'preview' => 'table'],
            ],
            'conversions' => [
                ['key' => 'goals', 'label' => 'Таблица целей', 'preview' => 'table'],
                ['key' => 'channels_by_goal', 'label' => 'Каналы по целям', 'preview' => 'table'],
                ['key' => 'search_goals', 'label' => 'Конверсии из поиска', 'preview' => 'kpi'],
                ['key' => 'ad_goals', 'label' => 'Конверсии из рекламы', 'preview' => 'kpi'],
                ['key' => 'social_goals', 'label' => 'Конверсии из соцсетей', 'preview' => 'kpi'],
            ],
            'gsc' => [
                ['key' => 'kpis', 'label' => 'KPI: клики / показы / CTR / позиция', 'preview' => 'kpi'],
                ['key' => 'queries', 'label' => 'Топ запросов', 'preview' => 'table'],
                ['key' => 'pages', 'label' => 'Топ страниц', 'preview' => 'table'],
            ],
            'webmaster' => [
                ['key' => 'kpis', 'label' => 'KPI: клики / показы / CTR / позиция', 'preview' => 'kpi'],
                ['key' => 'queries', 'label' => 'Топ запросов', 'preview' => 'table'],
                ['key' => 'pages', 'label' => 'Топ страниц', 'preview' => 'table'],
                ['key' => 'diagnostics', 'label' => 'Общие ошибки (диагностика)', 'preview' => 'list'],
                ['key' => 'meta_duplicates', 'label' => 'Дубли title / Description', 'preview' => 'table'],
                ['key' => 'filtered_pages', 'label' => 'Отфильтрованные / малополезные страницы', 'preview' => 'table'],
            ],
            'direct' => [
                ['key' => 'kpis', 'label' => 'KPI сессий', 'preview' => 'kpi'],
                ['key' => 'spend', 'label' => 'Расход / клики / CPC', 'preview' => 'kpi'],
                ['key' => 'series_visits', 'label' => 'График визитов', 'preview' => 'chart'],
                ['key' => 'engines', 'label' => 'Площадки', 'preview' => 'table'],
                ['key' => 'campaigns', 'label' => 'Кампании', 'preview' => 'table'],
                ['key' => 'platforms', 'label' => 'Платформы', 'preview' => 'table'],
                ['key' => 'phrases', 'label' => 'Поисковые фразы', 'preview' => 'table'],
                ['key' => 'landings', 'label' => 'Посадочные', 'preview' => 'table'],
                ['key' => 'conversions', 'label' => 'Конверсии', 'preview' => 'table'],
                ['key' => 'fix', 'label' => 'Что поправить', 'preview' => 'list'],
            ],
            'google_ads' => [
                ['key' => 'kpis', 'label' => 'KPI сессий', 'preview' => 'kpi'],
                ['key' => 'campaigns', 'label' => 'Кампании', 'preview' => 'table'],
                ['key' => 'landings', 'label' => 'Посадочные', 'preview' => 'table'],
                ['key' => 'phrases', 'label' => 'Фразы', 'preview' => 'table'],
                ['key' => 'conversions', 'label' => 'Конверсии', 'preview' => 'table'],
            ],
            'vk_ads' => [
                ['key' => 'kpis', 'label' => 'KPI', 'preview' => 'kpi'],
                ['key' => 'campaigns', 'label' => 'Кампании', 'preview' => 'table'],
            ],
            'vk_smm' => [
                ['key' => 'kpis', 'label' => 'KPI / ER', 'preview' => 'kpi'],
                ['key' => 'posts', 'label' => 'Топ-посты', 'preview' => 'table'],
            ],
            'ecommerce' => [
                ['key' => 'kpis', 'label' => 'Выручка и заказы', 'preview' => 'kpi'],
                ['key' => 'products', 'label' => 'Топ товаров', 'preview' => 'table'],
            ],
            'calls' => [
                ['key' => 'kpis', 'label' => 'Звонки KPI', 'preview' => 'kpi'],
                ['key' => 'series', 'label' => 'Динамика звонков', 'preview' => 'chart'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::catalog() as $section => $metrics) {
            $out[$section] = [];
            foreach ($metrics as $metric) {
                $out[$section][$metric['key']] = true;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string, array<string, bool>>
     */
    public static function normalize($raw): array
    {
        $defaults = self::defaults();
        if (!is_array($raw)) {
            return $defaults;
        }

        foreach ($defaults as $section => $metrics) {
            $posted = is_array($raw[$section] ?? null) ? $raw[$section] : [];
            foreach ($metrics as $key => $_on) {
                if (array_key_exists($key, $posted)) {
                    $defaults[$section][$key] = !empty($posted[$key]) && $posted[$key] !== '0';
                }
            }
        }

        return $defaults;
    }

    /**
     * @param array<string,mixed>|null $settings
     */
    public static function enabled(?array $settings, string $section, string $metric): bool
    {
        $toggles = self::normalize($settings['metric_toggles'] ?? null);
        if (!isset($toggles[$section])) {
            return true;
        }
        if (!array_key_exists($metric, $toggles[$section])) {
            return true;
        }

        return !empty($toggles[$section][$metric]);
    }

    /**
     * Sections that have nested metric controls in the builder.
     *
     * @return list<string>
     */
    public static function sectionsWithMetrics(): array
    {
        return array_keys(self::catalog());
    }
}
