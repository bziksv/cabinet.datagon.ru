<?php

namespace App\Support;

/**
 * Богатая демо-проверка Site Audit: 10–100 находок на КАЖДЫЙ код из каталога
 * (кроме external-модулей и virtual-сводок — сводки считаются из children).
 * HTML — ≥5 паттернов, один сквозной.
 */
class SiteAuditDemoFixture
{
    public const DOMAIN = 'demo-audit.titlo.ru';
    public const PROJECT_NAME = 'Демо: полный аудит (фикстура)';
    public const DEMO_VERSION = 3;
    public const SHARE_TOKEN = 'demo-site-audit-rich';

    /**
     * Все seedable-коды (не external / не virtual). severity — как в config/site_audit.php.
     *
     * @var array<string, string>
     */
    private const CODES = [
        'http_4xx' => 'critical',
        'http_5xx' => 'critical',
        'unreachable' => 'critical',
        'redirect' => 'warning',
        'redirect_chain_long' => 'other',
        'redirect_loop' => 'critical',
        'duplicate_title' => 'critical',
        'duplicate_description' => 'critical',
        'duplicate_content' => 'critical',
        'similar_pages' => 'warning',
        'empty_title' => 'critical',
        'empty_description' => 'warning',
        'multiple_title_or_description' => 'critical',
        'noindex' => 'warning',
        'canonical_foreign' => 'other',
        'canonical_not_self' => 'warning',
        'canonical_empty' => 'info',
        'multiple_canonical' => 'warning',
        'page_too_large' => 'warning',
        'missing_h1' => 'warning',
        'multiple_h1' => 'critical',
        'thin_content' => 'warning',
        'title_too_short' => 'warning',
        'title_too_long' => 'warning',
        'description_too_short' => 'warning',
        'description_too_long' => 'warning',
        'title_equals_h1' => 'warning',
        'title_equals_description' => 'warning',
        'description_equals_h1' => 'warning',
        'h1_equals_h2' => 'warning',
        'heading_hierarchy' => 'warning',
        'too_many_strong' => 'warning',
        'duplicate_links' => 'warning',
        'external_links' => 'info',
        'meta_spam' => 'warning',
        'h1_spam' => 'warning',
        'text_nausea' => 'warning',
        'text_bigram_spam' => 'warning',
        'text_trigram_spam' => 'warning',
        'no_unique_images' => 'info',
        'text_in_noindex' => 'warning',
        'images_without_alt' => 'warning',
        'robots_txt_error' => 'warning',
        'robots_txt_closed' => 'critical',
        'robots_blocked' => 'warning',
        'pages_with_iframe' => 'info',
        'mixed_content' => 'warning',
        'insecure_form' => 'critical',
        'bad_doctype' => 'info',
        'meta_nofollow' => 'warning',
        'links_nofollow' => 'info',
        'external_assets' => 'info',
        'soft_404' => 'other',
        'orphan_pages' => 'warning',
        'sitemap_missing' => 'warning',
        'sitemap_error' => 'warning',
        'not_in_sitemap' => 'info',
        'sitemap_not_crawled' => 'info',
        'landing_not_in_sitemap' => 'warning',
        'landing_not_crawled' => 'warning',
        'landing_url_changed' => 'warning',
        'duplicate_url_variants' => 'other',
        'www_both_available' => 'critical',
        'http_https_both_available' => 'critical',
        'page_has_broken_links' => 'warning',
        'broken_internal_link' => 'critical',
        'page_has_bad_links' => 'warning',
        'lost_file' => 'warning',
        'adult_content' => 'warning',
        'negative_content' => 'warning',
        'word_repeat_in_sentence' => 'info',
        'landing_plagiarism_suspect' => 'warning',
        'landing_plagiarism_external' => 'warning',
        'landing_no_inbound_internal' => 'warning',
        'keyword_cannibalization' => 'warning',
        'ad_cannibalization' => 'warning',
        'serp_snippet_cannibalization' => 'warning',
        'landing_query_mismatch' => 'warning',
        'commercial_missing_contacts' => 'info',
        'commercial_missing_price' => 'info',
        'commercial_missing_cta' => 'info',
        'commercial_missing_delivery' => 'info',
        'commercial_missing_payment' => 'info',
        'commercial_missing_stock' => 'info',
        'commercial_missing_reviews' => 'info',
        'broken_image' => 'warning',
        'heavy_image' => 'info',
        'error_spike' => 'warning',
        'psi_mobile' => 'info',
        'psi_desktop' => 'info',
        'deep_pages' => 'info',
        'site_availability' => 'warning',
        'index_count_mismatch' => 'warning',
        'serp_snippets' => 'info',
        'serp_title_mismatch' => 'warning',
        'serp_not_indexed' => 'warning',
        'serp_snippet_source' => 'info',
        'probable_affiliate' => 'info',
        'no_outbound_internal' => 'info',
        'risky_query_params' => 'warning',
        'pagination_param' => 'info',
        'missing_hsts' => 'warning',
        'missing_csp' => 'info',
        'missing_x_frame_options' => 'info',
        'missing_referrer_policy' => 'info',
        'missing_x_content_type_options' => 'info',
        'missing_permissions_policy' => 'info',
        'missing_coop' => 'info',
        'missing_coep' => 'info',
        'missing_corp' => 'info',
        'missing_charset' => 'info',
    ];

    /**
     * @return array{
     *   project: array<string,mixed>,
     *   crawl: array<string,mixed>,
     *   pages: list<array<string,mixed>>,
     *   findings: list<array<string,mixed>>,
     *   stats: list<array<string,mixed>>,
     *   summary: array{pages:int,findings:int,codes:int}
     * }
     */
    public static function build(int $userId, ?string $now = null): array
    {
        $now = $now ?: date('Y-m-d H:i:s');
        $base = 'https://' . self::DOMAIN;
        $paths = self::pagePaths();
        $pages = [];
        foreach ($paths as $i => $path) {
            $url = $base . $path;
            $title = 'Демо страница ' . ($i + 1) . ' — ' . trim($path, '/');
            if ($title === 'Демо страница 1 — ') {
                $title = 'Демо-аудит — главная';
            }
            $pages[] = [
                'url' => $url,
                'url_hash' => hash('sha256', $url),
                'final_url' => $url,
                'status_code' => 200,
                'redirect_chain' => null,
                'size_bytes' => 8000 + ($i * 37) % 40000,
                'content_type' => 'text/html; charset=utf-8',
                'charset' => 'utf-8',
                'title' => $title,
                'title_hash' => hash('sha256', mb_strtolower($title)),
                'description' => 'Описание демо-страницы ' . ($i + 1) . ' для витрины аудита.',
                'description_hash' => hash('sha256', 'desc-' . $i),
                'h1' => 'H1 демо ' . ($i + 1),
                'h1_count' => 1,
                'h2_count' => 2 + ($i % 3),
                'canonical' => $url,
                'robots_meta' => null,
                'noindex' => 0,
                'word_count' => 120 + ($i * 11) % 800,
                'text_len' => 800 + ($i * 40) % 5000,
                'content_hash' => hash('sha256', 'content-' . $i),
                'content_unchanged' => 0,
                'simhash' => null,
                'out_links_json' => json_encode([$base . '/', $base . '/catalog/'], JSON_UNESCAPED_UNICODE),
                'img_srcs_json' => null,
                'asset_srcs_json' => null,
                'click_depth' => min(8, (int) floor($i / 15)),
                'discovered_via' => $i === 0 ? 'seed' : (($i % 5 === 0) ? 'sitemap' : 'link'),
                'discovered_from' => $i === 0 ? null : $base . '/',
                'img_count' => 2 + ($i % 5),
                'img_without_alt' => $i % 4,
                'unique_img_src_count' => 2,
                'strong_count' => $i % 7,
                'em_count' => 0,
                'nausea_classic' => 0,
                'nausea_academic' => 0,
                'top_word' => null,
                'top_word_count' => 0,
                'top_bigram' => null,
                'top_bigram_count' => 0,
                'top_trigram' => null,
                'top_trigram_count' => 0,
                'noindex_text_len' => 0,
                'html_storage_key' => null,
                'html_bytes_gz' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $urls = array_column($pages, 'url');
        $findings = [];
        $counts = [];
        $buckets = ['critical' => 0, 'other' => 0, 'warning' => 0, 'info' => 0];

        $push = static function (string $code, string $severity, string $url, ?array $meta) use (&$findings, &$counts, &$buckets, $now) {
            $findings[] = [
                'code' => $code,
                'severity' => $severity,
                'url' => $url,
                'url_hash' => hash('sha256', $url),
                'meta_json' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $counts[$code] = ($counts[$code] ?? 0) + 1;
            if (isset($buckets[$severity])) {
                $buckets[$severity]++;
            }
        };

        // HTML: ≥5 паттернов
        $htmlPatterns = [
            ['message' => 'error parsing attribute name', 'n' => 72],
            ['message' => 'Unexpected end tag : ol', 'n' => 18],
            ['message' => 'Opening and ending tag mismatch: div and span', 'n' => 14],
            ['message' => 'Незакрытый HTML-комментарий <!--', 'n' => 12],
            ['message' => 'Несколько закрывающих тегов </body>', 'n' => 10],
            ['message' => 'error parsing attribute value', 'n' => 9],
            ['message' => 'Attribute ";" not allowed', 'n' => 8],
        ];
        $htmlOffset = 0;
        foreach ($htmlPatterns as $pi => $pat) {
            for ($j = 0; $j < $pat['n']; $j++) {
                $url = $urls[($htmlOffset + $j) % count($urls)];
                $line = 80 + ($pi * 40) + ($j % 30);
                $push('html_critical_errors', 'other', $url, [
                    'count' => 1 + ($j % 3),
                    'samples' => [
                        ['line' => $line, 'level' => 'error', 'message' => $pat['message']],
                    ],
                ]);
            }
            $htmlOffset += $pat['n'];
        }

        foreach (self::CODES as $code => $sev) {
            $n = self::countForCode($code);
            $brokenTarget = in_array($code, ['broken_internal_link', 'http_4xx'], true);
            for ($j = 0; $j < $n; $j++) {
                $url = $brokenTarget
                    ? ($base . '/missing/page-' . ($j + 1) . '/')
                    : $urls[$j % count($urls)];
                $push($code, $sev, $url, self::metaFor($code, $base, $url, $j));
            }
        }

        // Отчёт «Страницы с rel=canonical» берёт счётчик из counts_json, строки — из pages.
        $counts['pages_with_canonical'] = count($pages);

        $project = [
            'user_id' => $userId,
            'team_id' => null,
            'domain' => self::DOMAIN,
            'name' => self::PROJECT_NAME,
            'settings_json' => json_encode([
                'demo' => true,
                'demo_version' => self::DEMO_VERSION,
                'note' => 'Синтетическая проверка: находки на каждый код каталога',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $crawl = [
            'user_id' => $userId,
            'status' => 'done',
            'pages_total' => count($pages),
            'pages_fetched' => count($pages),
            'pages_limit' => 500,
            'buckets_json' => json_encode($buckets, JSON_UNESCAPED_UNICODE),
            'counts_json' => json_encode($counts, JSON_UNESCAPED_UNICODE),
            'progress_json' => json_encode([
                'demo' => true,
                'demo_version' => self::DEMO_VERSION,
                'robots' => ['ok' => true],
                'sitemap' => ['found' => true, 'url_count' => count($urls)],
                // Чтобы в дереве не было «не было» у probe-вкладок
                'psi' => ['ran' => true, 'ok' => true, 'checked' => 20],
                'serp_snippets' => ['ran' => true, 'ok' => true, 'engines' => ['yandex', 'google']],
                'serp_cannibalization' => ['ran' => true, 'ok' => true],
                'serp_index' => [
                    'ran' => true,
                    'ok' => true,
                    'deep' => [
                        'source' => 'demo',
                        'serp_count' => 80,
                        'matched' => 50,
                        'crawl_count' => count($pages),
                        'missing_in_index' => (int) ($counts['index_count_mismatch'] ?? 0),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'error' => null,
            'save_html' => '0',
            'share_token' => self::SHARE_TOKEN,
            'share_enabled_at' => $now,
            'share_white_label' => 0,
            'share_brand_name' => null,
            'share_brand_url' => null,
            'share_brand_logo' => null,
            'started_at' => date('Y-m-d H:i:s', strtotime($now) - 900),
            'finished_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $stats = [];
        foreach ($buckets as $bucket => $value) {
            $stats[] = [
                'bucket' => $bucket,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [
            'project' => $project,
            'crawl' => $crawl,
            'pages' => $pages,
            'findings' => $findings,
            'stats' => $stats,
            'summary' => [
                'pages' => count($pages),
                'findings' => count($findings),
                'codes' => count($counts),
            ],
        ];
    }

    /** Стабильно 10…100 на код. */
    private static function countForCode(string $code): int
    {
        $n = 10 + (abs(crc32($code)) % 91);

        return min(100, max(10, $n));
    }

    /**
     * @return list<string>
     */
    private static function pagePaths(): array
    {
        $paths = ['/', '/catalog/', '/about/', '/contacts/', '/delivery/', '/cart/', '/blog/'];
        for ($i = 1; $i <= 40; $i++) {
            $paths[] = '/catalog/item-' . $i . '/';
        }
        for ($i = 1; $i <= 35; $i++) {
            $paths[] = '/blog/post-' . $i . '/';
        }
        for ($i = 1; $i <= 25; $i++) {
            $paths[] = '/info/page-' . $i . '/';
        }
        for ($i = 1; $i <= 15; $i++) {
            $paths[] = '/promo/offer-' . $i . '/';
        }

        return $paths;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function metaFor(string $code, string $base, string $url, int $j): ?array
    {
        switch ($code) {
            case 'broken_internal_link':
            case 'http_4xx':
                return [
                    'status' => 404,
                    'referrers' => [$base . '/catalog/', $base . '/blog/'],
                    'referrer_count' => 2,
                ];
            case 'http_5xx':
                return ['status' => 500 + ($j % 3)];
            case 'unreachable':
                return ['error' => 'timeout'];
            case 'redirect':
                return [
                    'to' => $base . '/catalog/',
                    'status' => 301,
                    'chain' => [$url, $base . '/catalog/'],
                ];
            case 'redirect_chain_long':
                return [
                    'chain' => [$url, $base . '/a/', $base . '/b/', $base . '/c/', $base . '/'],
                    'hops' => 4,
                ];
            case 'redirect_loop':
                return [
                    'chain' => [$url, $base . '/loop-a/', $url],
                    'loop' => true,
                ];
            case 'duplicate_title':
                $title = 'Дубль TITLE группы ' . ((int) floor($j / 8) + 1);

                return ['title' => $title, 'hash' => hash('sha256', $title), 'group_size' => 8];
            case 'duplicate_description':
                $desc = 'Одинаковое description #' . ((int) floor($j / 6) + 1);

                return ['description' => $desc, 'hash' => hash('sha256', $desc), 'group_size' => 6];
            case 'duplicate_content':
                return ['hash' => hash('sha256', 'dup-content-' . ((int) floor($j / 4))), 'group_size' => 4];
            case 'similar_pages':
                return [
                    'similar_url' => $base . '/catalog/item-' . (($j % 40) + 1) . '/',
                    'distance' => 3 + ($j % 5),
                ];
            case 'thin_content':
                return ['word_count' => 40 + ($j % 30)];
            case 'images_without_alt':
            case 'page_has_broken_links':
            case 'too_many_strong':
            case 'external_links':
            case 'duplicate_links':
            case 'links_nofollow':
                return ['count' => 1 + ($j % 4)];
            case 'broken_image':
                return ['src' => $base . '/img/broken-' . ($j + 1) . '.jpg', 'status' => 404];
            case 'lost_file':
                return [
                    'asset' => $base . '/assets/app-' . ($j % 5) . '.css',
                    'status' => 404,
                    'referrers' => [$url],
                ];
            case 'page_has_bad_links':
                return [
                    'count' => 1,
                    'samples' => [['href' => '', 'reason' => 'missing_href', 'text' => 'клик']],
                ];
            case 'canonical_not_self':
                return ['canonical' => $base . '/'];
            case 'canonical_foreign':
                return ['canonical' => 'https://other-shop.example/page'];
            case 'serp_title_mismatch':
                return [
                    'engine' => $j % 2 ? 'google' : 'yandex',
                    'page_title' => 'TITLE на сайте ' . ($j + 1),
                    'serp_title' => 'TITLE в выдаче ' . ($j + 1),
                ];
            case 'index_count_mismatch':
            case 'serp_not_indexed':
                return ['engine' => 'yandex', 'in_index' => false];
            case 'serp_snippets':
            case 'serp_snippet_source':
                return [
                    'engine' => $j % 2 ? 'google' : 'yandex',
                    'snippet' => 'Демо-сниппет для витрины #' . ($j + 1),
                ];
            case 'psi_mobile':
            case 'psi_desktop':
                return [
                    'score' => 35 + ($j % 40),
                    'strategy' => $code === 'psi_mobile' ? 'mobile' : 'desktop',
                ];
            case 'landing_plagiarism_suspect':
                return [
                    'peer_url' => $base . '/blog/post-' . (($j % 35) + 1) . '/',
                    'source' => 'internal',
                ];
            case 'landing_plagiarism_external':
                return ['uniqueness' => 42 + ($j % 20), 'peer' => 'https://copy.example/page'];
            case 'word_repeat_in_sentence':
                return ['count' => 3, 'samples' => [['word' => 'диван', 'count' => 4]]];
            case 'text_nausea':
            case 'text_bigram_spam':
            case 'text_trigram_spam':
            case 'meta_spam':
            case 'h1_spam':
                return ['nausea' => 7.5 + ($j % 5), 'top_word' => 'купить'];
            case 'adult_content':
            case 'negative_content':
                return ['hits' => ['demo', 'fixture'], 'score' => 2 + ($j % 3)];
            case 'heavy_image':
                return ['src' => $base . '/img/big-' . $j . '.jpg', 'bytes' => 2500000];
            case 'page_too_large':
                return ['size_bytes' => 2500000 + $j * 1000];
            case 'duplicate_url_variants':
                return ['variants' => [$url, rtrim($url, '/') . '?utm=1']];
            case 'risky_query_params':
                return ['params' => ['session_id', 'token']];
            case 'pagination_param':
                return ['params' => ['page']];
            case 'www_both_available':
            case 'http_https_both_available':
                return ['variants' => [$url, str_replace('https://', 'http://', $url)]];
            case 'robots_txt_closed':
            case 'robots_txt_error':
            case 'sitemap_missing':
            case 'sitemap_error':
            case 'site_availability':
            case 'error_spike':
                return ['detail' => 'demo-fixture'];
            case 'keyword_cannibalization':
            case 'ad_cannibalization':
            case 'serp_snippet_cannibalization':
            case 'landing_query_mismatch':
                return [
                    'query' => 'купить диван демо',
                    'urls' => [$url, $base . '/catalog/'],
                ];
            case 'commercial_missing_price':
            case 'commercial_missing_contacts':
            case 'commercial_missing_cta':
            case 'commercial_missing_delivery':
            case 'commercial_missing_payment':
            case 'commercial_missing_stock':
            case 'commercial_missing_reviews':
            case 'landing_not_in_sitemap':
            case 'landing_not_crawled':
            case 'landing_url_changed':
            case 'landing_no_inbound_internal':
                return ['missing' => true];
            default:
                return ['demo' => true];
        }
    }
}
