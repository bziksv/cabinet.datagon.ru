<?php

namespace App\Services\SiteAudit;

/**
 * Раскладывает коды проблем Site Audit по профильным этапам SEO-чеклиста.
 */
class SiteAuditChecklistStageMapper
{
    /**
     * @param  string  $findingCode
     * @param  array<string, mixed>  $findingMeta
     * @param  array<string, array<string, mixed>>  $availableStages
     * @param  string  $stageMode  smart|single
     * @param  string  $fallbackStageKey
     */
    public static function resolveStageKey(
        string $findingCode,
        array $findingMeta,
        array $availableStages,
        string $stageMode = 'smart',
        string $fallbackStageKey = 'audits'
    ): string {
        if ($stageMode === 'single') {
            return self::pickExisting($fallbackStageKey, $availableStages);
        }

        $target = self::guessStageKey($findingCode, $findingMeta);

        if (isset($availableStages[$target])) {
            return $target;
        }

        return self::pickExisting($fallbackStageKey, $availableStages);
    }

    /**
     * @param  array<string, mixed>  $findingMeta
     */
    public static function guessStageKey(string $findingCode, array $findingMeta = []): string
    {
        $c = strtolower($findingCode);
        $group = strtolower((string) ($findingMeta['group'] ?? ''));

        // 1. Мета-теги и индексация
        if (preg_match('/(title|description|meta_|h1|canonical|noindex|nofollow|hreflang|snippet|index_|robots_noindex|open_graph|og_|twitter_card|schema|microdata)/', $c)) {
            return 'meta_index';
        }

        // 2. Ссылочное (внешние ссылки, анкоры, исходящие)
        if (preg_match('/(external_link|outbound|backlink|anchor|broken_external|affiliate)/', $c)) {
            return 'links';
        }

        // 3. Контент и статьи (текст, дубли контента, плагиат, каннибализация)
        if (preg_match('/(thin_content|duplicate_content|simhash|plagiarism|cannibal|word_count|lorem|spelling|adult|profanity|content_risk|empty_body|text_)/', $c)) {
            return 'content';
        }

        // 4. Коммерческие факторы
        if (preg_match('/(contact|phone|email|price|commercial|address|inn|ogrn)/', $c)) {
            return 'commerce';
        }

        // 5. UX и интерфейс
        if (preg_match('/(viewport|mobile|cls|lcp|inp|font|tap_target|flash|iframe|popup|ux_|image_oversize|large_image)/', $c)) {
            return 'ux';
        }

        // 6. Структура и URL
        if (preg_match('/(depth|orphan|url_|get_param|pagination|slash|uppercase|cyrillic_url|long_url|breadcrumb|sitemap_orphan)/', $c)) {
            return 'structure';
        }

        // 7. Техбаза и безопасность (4xx, 5xx, редиректы, SSL, robots, sitemap, скорость, HTML, битые внутренние)
        if (preg_match('/(4xx|5xx|404|3xx|redirect|ssl|https|http_|mixed_content|robots|sitemap|broken_internal|broken_image|ttfb|slow|speed|psi|html_|w3c|vnu|charset|compression|gzip|host_variant|mirror|status_)/', $c)) {
            return 'tech_base';
        }

        if ($group === 'seo') {
            return 'meta_index';
        }
        if ($group === 'tech') {
            return 'tech_base';
        }

        return 'audits';
    }

    /**
     * @param  array<string, array<string, mixed>>  $availableStages
     */
    private static function pickExisting(string $preferred, array $availableStages): string
    {
        if ($preferred !== '' && isset($availableStages[$preferred])) {
            return $preferred;
        }
        if (isset($availableStages['audits'])) {
            return 'audits';
        }
        $keys = array_keys($availableStages);

        return (string) ($keys[0] ?? 'audits');
    }
}
