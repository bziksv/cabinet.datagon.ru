<?php

namespace App\SeoReports;

/**
 * Показатели внутри секций отчёта (вкл/выкл в шаблоне).
 * Ключи совпадают со snapshot / blade, где возможно.
 */
class SeoReportMetricRegistry
{
    /**
     * @return array<string, list<array{key:string,label:string}>>
     */
    public static function catalog(): array
    {
        return [
            'traffic' => [
                ['key' => 'visits', 'label' => 'Визиты'],
                ['key' => 'users', 'label' => 'Пользователи'],
                ['key' => 'pageviews', 'label' => 'Просмотры'],
                ['key' => 'bounce_rate', 'label' => 'Отказы'],
                ['key' => 'page_depth', 'label' => 'Глубина'],
                ['key' => 'avg_visit_duration', 'label' => 'Время на сайте'],
                ['key' => 'series_users', 'label' => 'График пользователей по дням'],
                ['key' => 'channels', 'label' => 'Каналы'],
                ['key' => 'channel_months', 'label' => 'Каналы по месяцам'],
                ['key' => 'sources', 'label' => 'Источники'],
                ['key' => 'search', 'label' => 'Поисковый трафик'],
                ['key' => 'devices', 'label' => 'Устройства'],
                ['key' => 'geo', 'label' => 'География'],
                ['key' => 'landings', 'label' => 'Топ посадочных'],
                ['key' => 'landings_search', 'label' => 'Посадочные из поиска'],
                ['key' => 'landings_social', 'label' => 'Посадочные из соцсетей'],
                ['key' => 'comment', 'label' => 'Комментарий к трафику'],
            ],
            'positions' => [
                ['key' => 'summary', 'label' => 'Сводка TOP-3 / 10 / 30 / 100'],
                ['key' => 'dynamics', 'label' => 'Динамика: рост / без изменений / падение'],
                ['key' => 'top_baskets', 'label' => 'Корзины TOP'],
                ['key' => 'visibility_by_engine', 'label' => 'Видимость по ПС'],
                ['key' => 'visibility_series', 'label' => 'График видимости TOP-10'],
                ['key' => 'phrases_improved', 'label' => 'Выросшие запросы'],
                ['key' => 'phrases_worsened', 'label' => 'Упавшие запросы'],
                ['key' => 'by_engine', 'label' => 'Сводка по поисковым системам'],
                ['key' => 'quick_wins', 'label' => 'Быстрые победы'],
                ['key' => 'risk', 'label' => 'Risk-лист'],
                ['key' => 'groups', 'label' => 'Группы запросов'],
                ['key' => 'competitors', 'label' => 'Конкуренты'],
            ],
            'conversions' => [
                ['key' => 'goals', 'label' => 'Таблица целей'],
                ['key' => 'channels_by_goal', 'label' => 'Каналы по целям'],
                ['key' => 'search_goals', 'label' => 'Конверсии из поиска'],
                ['key' => 'ad_goals', 'label' => 'Конверсии из рекламы'],
                ['key' => 'social_goals', 'label' => 'Конверсии из соцсетей'],
            ],
            'gsc' => [
                ['key' => 'kpis', 'label' => 'KPI: клики / показы / CTR / позиция'],
                ['key' => 'queries', 'label' => 'Топ запросов'],
                ['key' => 'pages', 'label' => 'Топ страниц'],
            ],
            'webmaster' => [
                ['key' => 'kpis', 'label' => 'KPI: клики / показы / CTR / позиция'],
                ['key' => 'queries', 'label' => 'Топ запросов'],
                ['key' => 'pages', 'label' => 'Топ страниц'],
            ],
            'direct' => [
                ['key' => 'kpis', 'label' => 'KPI сессий'],
                ['key' => 'spend', 'label' => 'Расход / клики / CPC'],
                ['key' => 'series_visits', 'label' => 'График визитов'],
                ['key' => 'engines', 'label' => 'Площадки'],
                ['key' => 'campaigns', 'label' => 'Кампании'],
                ['key' => 'platforms', 'label' => 'Платформы'],
                ['key' => 'phrases', 'label' => 'Поисковые фразы'],
                ['key' => 'landings', 'label' => 'Посадочные'],
                ['key' => 'conversions', 'label' => 'Конверсии'],
                ['key' => 'fix', 'label' => 'Что поправить'],
            ],
            'google_ads' => [
                ['key' => 'kpis', 'label' => 'KPI сессий'],
                ['key' => 'campaigns', 'label' => 'Кампании'],
                ['key' => 'landings', 'label' => 'Посадочные'],
                ['key' => 'phrases', 'label' => 'Фразы'],
                ['key' => 'conversions', 'label' => 'Конверсии'],
            ],
            'vk_ads' => [
                ['key' => 'kpis', 'label' => 'KPI'],
                ['key' => 'campaigns', 'label' => 'Кампании'],
            ],
            'meta_ads' => [
                ['key' => 'kpis', 'label' => 'KPI'],
                ['key' => 'campaigns', 'label' => 'Кампании'],
            ],
            'vk_smm' => [
                ['key' => 'kpis', 'label' => 'KPI / ER'],
                ['key' => 'posts', 'label' => 'Топ-посты'],
            ],
            'ecommerce' => [
                ['key' => 'kpis', 'label' => 'Выручка и заказы'],
                ['key' => 'products', 'label' => 'Топ товаров'],
            ],
            'calls' => [
                ['key' => 'kpis', 'label' => 'Звонки KPI'],
                ['key' => 'series', 'label' => 'Динамика звонков'],
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
