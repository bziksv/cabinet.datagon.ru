<?php

namespace App\Services\SiteAudit;

/**
 * Столбцы обычных отчётов Site Audit (не crawl_pages / crawl_images).
 * Базовые колонки отчёта + опциональные поля страницы из обхода.
 */
class SiteAuditReportColumns
{
    /**
     * @return list<array{key:string,label:string,group:string,default?:bool,locked?:bool,source?:string}>
     */
    public static function catalog(): array
    {
        return [
            // source=report — колонки самой таблицы находок
            ['key' => 'url', 'label' => 'URL', 'group' => 'report', 'default' => true, 'locked' => true, 'source' => 'report'],
            ['key' => 'severity', 'label' => 'Приор.', 'group' => 'report', 'default' => true, 'source' => 'report'],
            ['key' => 'details', 'label' => 'Детали', 'group' => 'report', 'default' => true, 'locked' => true, 'source' => 'report'],
            ['key' => 'query', 'label' => 'Запрос', 'group' => 'report', 'default' => true, 'source' => 'report'],
            ['key' => 'landing', 'label' => 'Посадочная', 'group' => 'report', 'default' => true, 'source' => 'report'],
            ['key' => 'comp_title', 'label' => 'TITLE', 'group' => 'report', 'default' => true, 'source' => 'report'],
            ['key' => 'from', 'label' => 'Откуда', 'group' => 'report', 'default' => true, 'source' => 'report'],
            // Действия: в панели есть, порядок всегда в конце таблицы (не DnD).
            ['key' => 'actions', 'label' => 'Действия', 'group' => 'report', 'default' => true, 'pinned_end' => true, 'source' => 'report'],

            // source=page — подтягиваются со страницы обхода
            ['key' => 'status', 'label' => 'HTTP', 'group' => 'page', 'default' => false, 'source' => 'page'],
            ['key' => 'final_url', 'label' => 'Финальный URL', 'group' => 'page', 'default' => false, 'source' => 'page'],
            ['key' => 'content_type', 'label' => 'Content-Type', 'group' => 'page', 'default' => false, 'source' => 'page'],
            ['key' => 'size', 'label' => 'Размер', 'group' => 'page', 'default' => false, 'source' => 'page'],
            ['key' => 'charset', 'label' => 'Charset', 'group' => 'page', 'default' => false, 'source' => 'page'],

            ['key' => 'title', 'label' => 'TITLE', 'group' => 'meta', 'default' => false, 'source' => 'page'],
            ['key' => 'title_len', 'label' => 'Длина TITLE', 'group' => 'meta', 'default' => false, 'source' => 'page'],
            ['key' => 'description', 'label' => 'Description', 'group' => 'meta', 'default' => false, 'source' => 'page'],
            ['key' => 'desc_len', 'label' => 'Длина description', 'group' => 'meta', 'default' => false, 'source' => 'page'],
            ['key' => 'keywords', 'label' => 'Keywords', 'group' => 'meta', 'default' => false, 'source' => 'page'],
            ['key' => 'robots', 'label' => 'Meta Robots', 'group' => 'meta', 'default' => false, 'source' => 'page'],
            ['key' => 'index', 'label' => 'Индекс', 'group' => 'meta', 'default' => false, 'source' => 'page'],
            ['key' => 'canonical', 'label' => 'Canonical', 'group' => 'meta', 'default' => false, 'source' => 'page'],

            ['key' => 'h1_count', 'label' => 'H1 шт.', 'group' => 'headings', 'default' => false, 'source' => 'page'],
            ['key' => 'h1', 'label' => 'H1', 'group' => 'headings', 'default' => false, 'source' => 'page'],
            ['key' => 'h2_count', 'label' => 'H2 шт.', 'group' => 'headings', 'default' => false, 'source' => 'page'],
            ['key' => 'h2', 'label' => 'H2', 'group' => 'headings', 'default' => false, 'source' => 'page'],
            ['key' => 'h3', 'label' => 'H3', 'group' => 'headings', 'default' => false, 'source' => 'page'],

            ['key' => 'words', 'label' => 'Слов', 'group' => 'content', 'default' => false, 'source' => 'page'],
            ['key' => 'text_len', 'label' => 'Символов', 'group' => 'content', 'default' => false, 'source' => 'page'],
            ['key' => 'img', 'label' => 'Img', 'group' => 'content', 'default' => false, 'source' => 'page'],
            ['key' => 'img_no_alt', 'label' => 'Img без alt', 'group' => 'content', 'default' => false, 'source' => 'page'],
            ['key' => 'depth', 'label' => 'Глубина', 'group' => 'content', 'default' => false, 'source' => 'page'],
            ['key' => 'via', 'label' => 'Откуда в обходе', 'group' => 'content', 'default' => false, 'source' => 'page'],
        ];
    }

    /**
     * Ключи столбцов, которые всегда в конце таблицы (не участвуют в DnD).
     *
     * @return list<string>
     */
    public static function pinnedEndKeys(): array
    {
        $out = [];
        foreach (self::catalog() as $col) {
            if (! empty($col['pinned_end'])) {
                $out[] = $col['key'];
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'report' => 'Отчёт',
            'page' => 'Страница',
            'meta' => 'Meta / SEO',
            'headings' => 'Заголовки',
            'content' => 'Контент',
        ];
    }

    /**
     * @return array<string, array{label:string,cols:list<string>}>
     */
    public static function presets(): array
    {
        return [
            'report' => [
                'label' => 'Как в отчёте',
                'cols' => ['url', 'severity', 'details', 'query', 'landing', 'comp_title', 'from', 'actions'],
            ],
            'seo_meta' => [
                'label' => '+ SEO meta',
                'cols' => [
                    'url', 'details', 'status', 'title', 'description', 'h1', 'canonical', 'robots', 'index', 'actions',
                ],
            ],
            'meta_all' => [
                'label' => '+ Все meta',
                'cols' => [
                    'url', 'details', 'status', 'title', 'title_len', 'description', 'desc_len',
                    'keywords', 'robots', 'index', 'canonical', 'h1', 'h2', 'actions',
                ],
            ],
            'headings' => [
                'label' => '+ Заголовки',
                'cols' => ['url', 'details', 'h1_count', 'h1', 'h2_count', 'h2', 'h3', 'actions'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        $out = [];
        foreach (self::catalog() as $col) {
            if (! empty($col['default']) || ! empty($col['locked'])) {
                $out[] = $col['key'];
            }
        }

        return $out;
    }

    /**
     * Ключи page-* колонок для выборки из site_audit_pages.
     *
     * @return list<string>
     */
    public static function pageColumnKeys(): array
    {
        $out = [];
        foreach (self::catalog() as $col) {
            if (($col['source'] ?? '') === 'page') {
                $out[] = $col['key'];
            }
        }

        return $out;
    }

    /**
     * @param  object|array  $page
     * @return array<string, mixed>
     */
    public static function pageToCols($page): array
    {
        $get = static function ($key, $default = null) use ($page) {
            if (is_array($page)) {
                return $page[$key] ?? $default;
            }

            return $page->{$key} ?? $default;
        };

        $title = trim((string) $get('title', ''));
        $desc = trim((string) $get('description', ''));
        $h1 = trim((string) $get('h1', ''));
        $headings = $get('headings_json');
        if (! is_array($headings)) {
            $headings = [];
        }
        $h2list = isset($headings['h2']) && is_array($headings['h2']) ? $headings['h2'] : [];
        $h3list = isset($headings['h3']) && is_array($headings['h3']) ? $headings['h3'] : [];
        $h2 = isset($h2list[0]) ? trim((string) $h2list[0]) : '';
        $h3 = isset($h3list[0]) ? trim((string) $h3list[0]) : '';
        $via = (string) $get('discovered_via', '');
        $viaLabels = [
            'sitemap' => 'sitemap',
            'link' => 'по ссылке',
            'seed' => 'посев',
            'home' => 'главная',
        ];
        $size = $get('size_bytes');
        $sizeLabel = $size !== null && $size !== ''
            ? number_format((int) $size, 0, '', ' ') . ' B'
            : '';

        return [
            'status' => $get('status_code'),
            'final_url' => trim((string) $get('final_url', '')),
            'content_type' => trim((string) $get('content_type', '')),
            'size' => $sizeLabel,
            'charset' => trim((string) $get('charset', '')),
            'title' => $title,
            'title_len' => $title !== '' ? mb_strlen($title) : null,
            'description' => $desc,
            'desc_len' => $desc !== '' ? mb_strlen($desc) : null,
            'keywords' => trim((string) $get('keywords_meta', '')),
            'robots' => trim((string) $get('robots_meta', '')),
            'index' => $get('noindex') ? 'noindex' : 'index',
            'canonical' => trim((string) $get('canonical', '')),
            'h1_count' => $get('h1_count'),
            'h1' => $h1,
            'h2_count' => $get('h2_count'),
            'h2' => $h2,
            'h3' => $h3,
            'words' => $get('word_count'),
            'text_len' => $get('text_len'),
            'img' => $get('img_count'),
            'img_no_alt' => $get('img_without_alt'),
            'depth' => $get('click_depth'),
            'via' => $viaLabels[$via] ?? $via,
        ];
    }
}
