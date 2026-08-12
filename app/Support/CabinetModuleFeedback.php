<?php

namespace App\Support;

/**
 * Определяет модуль кабинета по URL страницы для тикета обратной связи.
 */
class CabinetModuleFeedback
{
    /**
     * Префиксы path → [код для тикета, подпись].
     * Порядок важен: более длинные / специфичные — раньше.
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    private static function pathMap(): array
    {
        return [
            ['site-audit', 'site_audit', 'Аудит сайта'],
            ['monitoring-v2', 'monitoring', 'Мониторинг позиций'],
            ['monitoring', 'monitoring', 'Мониторинг позиций'],
            ['analyze-relevance', 'relevance', 'Релевантность'],
            ['relevance-history', 'relevance', 'Релевантность'],
            ['history', 'relevance', 'Релевантность'],
            ['seo-checklist', 'seo_checklist', 'SEO-чеклист'],
            ['checklist', 'seo_checklist', 'SEO-чеклист'],
            ['seo-reports', 'seo_reports', 'SEO-отчёты'],
            ['reports', 'seo_reports', 'SEO-отчёты'],
            ['http-headers', 'http_headers', 'HTTP-заголовки'],
            ['site-monitoring', 'site_monitoring', 'Мониторинг сайта'],
            ['domain-information', 'domain_information', 'Срок домена'],
            ['domain-records', 'domain_records', 'Записи домена'],
            ['index-check', 'index_check', 'Проверка индекса'],
            ['esenin-text-check', 'esenin_text_check', 'Текстовый анализ'],
            ['text-uniqueness', 'text_uniqueness', 'Уникальность текста'],
            ['search-suggestions', 'search_suggestions', 'Подсказки'],
            ['phrase-commerce', 'phrase_commerce', 'Гео и коммерция'],
            ['site-types', 'site_types', 'Типы сайтов'],
            ['competitors', 'competitors', 'Конкуренты'],
            ['cluster', 'cluster', 'Кластеризация'],
            ['positions', 'positions', 'Позиции'],
            ['support', 'support', 'Поддержка'],
            ['ideas', 'ideas', 'Идеи'],
            ['news', 'news', 'Новости'],
            ['profile', 'profile', 'Профиль'],
            ['tariff', 'tariff', 'Тариф'],
            ['balance', 'balance', 'Баланс'],
        ];
    }

    /**
     * @return array{code:string,label:string}
     */
    public static function resolveFromPath(string $path): array
    {
        $path = '/' . ltrim(strtolower(parse_url($path, PHP_URL_PATH) ?: $path), '/');
        $path = rtrim($path, '/') ?: '/';

        if ($path === '/' || $path === '/home' || strpos($path, '/home/') === 0) {
            return ['code' => 'home', 'label' => 'Главная'];
        }

        foreach (self::pathMap() as $row) {
            [$prefix, $code, $label] = $row;
            $needle = '/' . $prefix;
            if ($path === $needle || strpos($path, $needle . '/') === 0) {
                return ['code' => $code, 'label' => $label];
            }
        }

        return ['code' => 'cabinet', 'label' => 'Кабинет'];
    }

    /**
     * @return array{code:string,label:string}
     */
    public static function resolveFromRequest(): array
    {
        return self::resolveFromPath(request()->getPathInfo() ?: '/');
    }

    public static function labelForCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return 'Кабинет';
        }

        static $byCode;
        if ($byCode === null) {
            $byCode = [
                'home' => 'Главная',
                'cabinet' => 'Кабинет',
            ];
            foreach (self::pathMap() as $row) {
                $byCode[$row[1]] = $row[2];
            }
        }

        return $byCode[$code] ?? $code;
    }
}
