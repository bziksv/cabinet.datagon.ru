<?php

namespace App\Services\SiteAudit;

/**
 * Колонки инвентаря «Страницы проверки»: ключи, подписи, пресеты.
 */
class SiteAuditCrawlPagesColumns
{
    public const MISSING = '<ОТСУТСТВУЕТ>';

    /**
     * @return list<array{key:string,label:string,group:string,tip?:string,default?:bool}>
     */
    public static function catalog(): array
    {
        return [
            ['key' => 'url', 'label' => 'URL', 'group' => 'base', 'default' => true, 'locked' => true],
            ['key' => 'url_len', 'label' => 'Длина URL', 'group' => 'base', 'default' => false],
            ['key' => 'https', 'label' => 'HTTPS', 'group' => 'base', 'default' => false],
            ['key' => 'status', 'label' => 'Код', 'group' => 'base', 'default' => true],
            ['key' => 'final_url', 'label' => 'Финальный URL', 'group' => 'base', 'default' => false],
            ['key' => 'content_type', 'label' => 'Тип контента', 'group' => 'base', 'default' => false],
            ['key' => 'size', 'label' => 'Размер', 'group' => 'base', 'default' => false],
            ['key' => 'charset', 'label' => 'Charset', 'group' => 'base', 'default' => false],

            ['key' => 'title', 'label' => 'TITLE', 'group' => 'meta', 'default' => true],
            ['key' => 'title_len', 'label' => 'Длина TITLE', 'group' => 'meta', 'default' => false],
            ['key' => 'description', 'label' => 'Description', 'group' => 'meta', 'default' => true],
            ['key' => 'desc_len', 'label' => 'Длина description', 'group' => 'meta', 'default' => false],
            ['key' => 'keywords', 'label' => 'Keywords', 'group' => 'meta', 'default' => false],
            ['key' => 'keywords_len', 'label' => 'Длина keywords', 'group' => 'meta', 'default' => false],
            ['key' => 'robots', 'label' => 'Meta Robots', 'group' => 'meta', 'default' => true],
            ['key' => 'index', 'label' => 'Индекс', 'group' => 'meta', 'default' => true],
            ['key' => 'canonical', 'label' => 'Canonical', 'group' => 'meta', 'default' => true],

            ['key' => 'h1_count', 'label' => 'H1 шт.', 'group' => 'headings', 'default' => true],
            ['key' => 'h1', 'label' => 'H1 текст', 'group' => 'headings', 'default' => true],
            ['key' => 'h2_count', 'label' => 'H2 шт.', 'group' => 'headings', 'default' => true],
            ['key' => 'h2', 'label' => 'H2 текст', 'group' => 'headings', 'default' => true],
            ['key' => 'h3_count', 'label' => 'H3 шт.', 'group' => 'headings', 'default' => false],
            ['key' => 'h3', 'label' => 'H3 текст', 'group' => 'headings', 'default' => false],
            ['key' => 'h4_count', 'label' => 'H4 шт.', 'group' => 'headings', 'default' => false],
            ['key' => 'h4', 'label' => 'H4 текст', 'group' => 'headings', 'default' => false],
            ['key' => 'h5_count', 'label' => 'H5 шт.', 'group' => 'headings', 'default' => false],
            ['key' => 'h5', 'label' => 'H5 текст', 'group' => 'headings', 'default' => false],
            ['key' => 'h6_count', 'label' => 'H6 шт.', 'group' => 'headings', 'default' => false],
            ['key' => 'h6', 'label' => 'H6 текст', 'group' => 'headings', 'default' => false],

            ['key' => 'words', 'label' => 'Слов', 'group' => 'content', 'default' => true],
            ['key' => 'text_len', 'label' => 'Символов текста', 'group' => 'content', 'default' => false],
            ['key' => 'img', 'label' => 'Img', 'group' => 'content', 'default' => true],
            ['key' => 'img_no_alt', 'label' => 'Img без alt', 'group' => 'content', 'default' => false],

            ['key' => 'out_links', 'label' => 'Внутр. исходящие', 'group' => 'links', 'default' => true],
            ['key' => 'ext_links', 'label' => 'Внеш. ссылки', 'group' => 'links', 'default' => true],
            ['key' => 'depth', 'label' => 'Глубина', 'group' => 'links', 'default' => true],
            ['key' => 'via', 'label' => 'Откуда в обходе', 'group' => 'links', 'default' => false],
        ];
    }

    /**
     * @return array<string, array{label:string,cols:list<string>}>
     */
    public static function presets(): array
    {
        return [
            'main' => [
                'label' => 'Основные',
                'cols' => ['url', 'status', 'title', 'description', 'h1_count', 'h1', 'h2_count', 'h2', 'words', 'out_links', 'ext_links', 'img', 'robots', 'canonical', 'index', 'depth'],
            ],
            'seo_meta' => [
                'label' => 'SEO meta',
                'cols' => ['url', 'status', 'title', 'title_len', 'description', 'desc_len', 'keywords', 'keywords_len', 'robots', 'index', 'canonical'],
            ],
            'headings' => [
                'label' => 'Заголовки H1–H6',
                'cols' => [
                    'url',
                    'h1_count', 'h1',
                    'h2_count', 'h2',
                    'h3_count', 'h3',
                    'h4_count', 'h4',
                    'h5_count', 'h5',
                    'h6_count', 'h6',
                ],
            ],
            'links' => [
                'label' => 'Ссылки и размер',
                'cols' => ['url', 'status', 'https', 'content_type', 'size', 'words', 'out_links', 'ext_links', 'img', 'depth', 'via'],
            ],
            'all' => [
                'label' => 'Все столбцы',
                'cols' => array_column(self::catalog(), 'key'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        $keys = [];
        foreach (self::catalog() as $col) {
            if (! empty($col['default']) || ! empty($col['locked'])) {
                $keys[] = $col['key'];
            }
        }

        return $keys;
    }

    /**
     * Ключ колонки UI → SQL ORDER BY (без ASC/DESC).
     * Только выражения по колонкам site_audit_pages.
     *
     * @return array<string,string>
     */
    public static function sortableSql(): array
    {
        return [
            'url' => 'site_audit_pages.url',
            'url_len' => 'CHAR_LENGTH(site_audit_pages.url)',
            'https' => "CASE WHEN site_audit_pages.url LIKE 'https://%' THEN 1 ELSE 0 END",
            'status' => 'site_audit_pages.status_code',
            'final_url' => 'site_audit_pages.final_url',
            'content_type' => 'site_audit_pages.content_type',
            'size' => 'site_audit_pages.size_bytes',
            'charset' => 'site_audit_pages.charset',
            'title' => 'site_audit_pages.title',
            'title_len' => 'CHAR_LENGTH(COALESCE(site_audit_pages.title, \'\'))',
            'description' => 'site_audit_pages.description',
            'desc_len' => 'CHAR_LENGTH(COALESCE(site_audit_pages.description, \'\'))',
            'keywords' => 'site_audit_pages.keywords_meta',
            'keywords_len' => 'CHAR_LENGTH(COALESCE(site_audit_pages.keywords_meta, \'\'))',
            'robots' => 'site_audit_pages.robots_meta',
            'index' => 'site_audit_pages.noindex',
            'canonical' => 'site_audit_pages.canonical',
            'h1' => 'site_audit_pages.h1',
            'h2' => "JSON_UNQUOTE(JSON_EXTRACT(site_audit_pages.headings_json, '$.h2[0]'))",
            'h3' => "JSON_UNQUOTE(JSON_EXTRACT(site_audit_pages.headings_json, '$.h3[0]'))",
            'h4' => "JSON_UNQUOTE(JSON_EXTRACT(site_audit_pages.headings_json, '$.h4[0]'))",
            'h5' => "JSON_UNQUOTE(JSON_EXTRACT(site_audit_pages.headings_json, '$.h5[0]'))",
            'h6' => "JSON_UNQUOTE(JSON_EXTRACT(site_audit_pages.headings_json, '$.h6[0]'))",
            'h1_count' => 'site_audit_pages.h1_count',
            'h2_count' => 'site_audit_pages.h2_count',
            'h3_count' => "COALESCE(JSON_LENGTH(JSON_EXTRACT(site_audit_pages.headings_json, '$.h3')), 0)",
            'h4_count' => "COALESCE(JSON_LENGTH(JSON_EXTRACT(site_audit_pages.headings_json, '$.h4')), 0)",
            'h5_count' => "COALESCE(JSON_LENGTH(JSON_EXTRACT(site_audit_pages.headings_json, '$.h5')), 0)",
            'h6_count' => "COALESCE(JSON_LENGTH(JSON_EXTRACT(site_audit_pages.headings_json, '$.h6')), 0)",
            'words' => 'site_audit_pages.word_count',
            'text_len' => 'site_audit_pages.text_len',
            'img' => 'site_audit_pages.img_count',
            'img_no_alt' => 'site_audit_pages.img_without_alt',
            'out_links' => 'COALESCE(JSON_LENGTH(site_audit_pages.out_links_json), 0)',
            'ext_links' => 'COALESCE(JSON_LENGTH(site_audit_pages.ext_links_json), 0)',
            'depth' => 'site_audit_pages.click_depth',
            'via' => 'site_audit_pages.discovered_via',
        ];
    }

    /**
     * Числовые/булевы колонки: первый клик — по убыванию.
     *
     * @return list<string>
     */
    public static function numericSortKeys(): array
    {
        return [
            'url_len', 'https', 'status', 'size', 'title_len', 'desc_len', 'keywords_len',
            'index', 'h1_count', 'h2_count', 'h3_count', 'h4_count', 'h5_count', 'h6_count',
            'words', 'text_len', 'img', 'img_no_alt',
            'out_links', 'ext_links', 'depth',
        ];
    }

    /**
     * @return array{0:string,1:string} [sortKey, dir asc|desc]
     */
    public static function normalizeSort(?string $sort, ?string $dir): array
    {
        $map = self::sortableSql();
        $sort = (string) $sort;
        if ($sort === '' || ! isset($map[$sort])) {
            return ['url', 'asc'];
        }
        $dir = strtolower((string) $dir) === 'desc' ? 'desc' : 'asc';

        return [$sort, $dir];
    }

    public static function defaultDir(string $sortKey): string
    {
        return in_array($sortKey, self::numericSortKeys(), true) ? 'desc' : 'asc';
    }

    /**
     * Тексты заголовков уровня (все через « · »), без подмены на количество.
     *
     * @param  list<string>|null  $texts
     * @param  int|null  $knownCount
     */
    public static function headingCell(?array $texts, ?int $knownCount = null): string
    {
        $texts = is_array($texts) ? array_values(array_filter(array_map('trim', $texts))) : [];
        if ($texts !== []) {
            $show = array_slice($texts, 0, 12);
            $joined = implode(' · ', $show);
            $extra = count($texts) - count($show);

            return $extra > 0 ? ($joined . ' (+' . $extra . ')') : $joined;
        }
        if ($knownCount !== null && $knownCount === 0) {
            return self::MISSING;
        }
        // count > 0, но текстов не сохранён (старый обход) — не подменяем цифрой.
        if ($knownCount !== null && $knownCount > 0) {
            return '—';
        }

        return '—';
    }

    public static function missingOrText(?string $text): string
    {
        $text = trim((string) $text);

        return $text !== '' ? $text : self::MISSING;
    }
}
