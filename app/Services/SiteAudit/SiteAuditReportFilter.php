<?php

namespace App\Services\SiteAudit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Фильтры отчёта Site Audit: URL всегда + смежные поля (title и т.п.) по типу отчёта.
 * «Умный» = учитывает ввод в другой раскладке (йцукен ↔ qwerty).
 */
class SiteAuditReportFilter
{
    /** @var array<string,string> */
    private static $flipMap;

    /**
     * @param  int|null  $crawlId  для опций «Тип контента» из этой проверки
     * @return array<int,array{key:string,label:string,param:string,type?:string,options?:array<string,string>,tip?:string}>
     */
    public static function fieldsForCode(string $code, ?int $crawlId = null): array
    {
        $fields = [
            ['key' => 'url', 'label' => 'URL', 'param' => 'q_url'],
        ];

        if ($code === 'crawl_pages') {
            return self::crawlPagesFields($crawlId);
        }

        if ($code === 'crawl_images') {
            return self::crawlImagesFields();
        }

        if ($code === 'index_count_mismatch') {
            $fields[] = [
                'key' => 'discovered_via',
                'label' => 'Откуда в обходе',
                'param' => 'q_via',
                'type' => 'select',
                'options' => [
                    '' => 'Все',
                    'sitemap' => 'sitemap',
                    'link' => 'По ссылке',
                    'seed' => 'Посев',
                    'home' => 'Главная',
                ],
                'tip' => "Как URL попал в эту проверку.\nsitemap — из карты сайта.\nПо ссылке — с другой страницы.\nПосев — из стартового списка.",
            ];
            $fields[] = [
                'key' => 'url_kind',
                'label' => 'Вид URL',
                'param' => 'q_kind',
                'type' => 'select',
                'options' => [
                    '' => 'Все',
                    'clean' => 'Без ?параметров',
                    'params' => 'С ?параметрами',
                ],
                'tip' => "С параметрами — URL вида /page?cat=…\nЧасто это фильтры/метки, их иногда лучше не держать в индексе.",
            ];

            return $fields;
        }

        if (in_array($code, ['redirect', 'redirect_chain_long', 'redirect_loop'], true)) {
            $fields[] = [
                'key' => 'redirect_kind',
                'label' => 'Тип редиректа',
                'param' => 'q_redirect_kind',
                'type' => 'select',
                'options' => [
                    '' => 'Все',
                    'other_page' => 'Другая страница',
                    'slash_only' => 'Только слэш (/ ↔ /)',
                ],
                'tip' => "«Другая страница» — /old → /new, смена URL не только из‑за слэша.\n«Только слэш» — /about → /about/ (и наоборот).",
            ];
        }

        if ($code === 'keyword_cannibalization') {
            $fields[0]['label'] = 'Лишняя страница';
            $fields[] = [
                'key' => 'details',
                'label' => 'Запрос / посадочная из мониторинга',
                'param' => 'q_details',
                'tip' => "Поиск по запросу или URL посадочной из мониторинга позиций.",
            ];

            return $fields;
        }

        $extra = self::extraKeysForCode($code);
        $labels = [
            'title' => 'Title',
            'description' => 'Description',
            'h1' => 'H1',
            'canonical' => 'Canonical',
            'details' => 'Детали',
        ];

        foreach ($extra as $key) {
            $fields[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'param' => 'q_' . $key,
            ];
        }

        return $fields;
    }

    /**
     * @return string[]
     */
    public static function extraKeysForCode(string $code): array
    {
        $map = [
            'duplicate_title' => ['title'],
            'empty_title' => ['title'],
            'title_too_short' => ['title'],
            'title_too_long' => ['title'],
            'title_equals_h1' => ['title', 'h1'],
            'title_equals_description' => ['title', 'description'],
            'description_equals_h1' => ['description', 'h1'],
            'h1_equals_h2' => ['h1'],
            'heading_hierarchy' => ['details'],
            'soft_404' => ['title'],
            'duplicate_description' => ['description'],
            'empty_description' => ['description'],
            'description_too_short' => ['description'],
            'description_too_long' => ['description'],
            'missing_h1' => ['h1'],
            'multiple_h1' => ['h1'],
            'duplicate_links' => ['details'],
            'external_links' => ['details'],
            'meta_spam' => ['title', 'description'],
            'h1_spam' => ['h1'],
            'text_nausea' => ['details'],
            'text_bigram_spam' => ['details'],
            'text_trigram_spam' => ['details'],
            'text_in_noindex' => ['details'],
            'canonical_empty' => ['canonical'],
            'canonical_foreign' => ['canonical'],
            'canonical_not_self' => ['canonical'],
            'pages_with_canonical' => ['canonical'],
            'multiple_canonical' => ['canonical'],
            'insecure_form' => ['details'],
            'redirect' => ['details'],
            'redirect_chain_long' => ['details'],
            'redirect_loop' => ['details'],
            'broken_internal_link' => ['details'],
            'page_has_broken_links' => ['details'],
            'links_nofollow' => ['details'],
            'page_has_broken_external_links' => ['details'],
            'broken_external_link' => ['details'],
            'page_has_bad_links' => ['details'],
            'html_critical_errors' => ['details'],
            'lost_file' => ['details'],
            'adult_content' => ['details'],
            'negative_content' => ['details'],
            'word_repeat_in_sentence' => ['details'],
            'landing_plagiarism_suspect' => ['details'],
            'landing_plagiarism_external' => ['details'],
            'landing_no_inbound_internal' => ['details'],
            'keyword_cannibalization' => ['details'],
            'ad_cannibalization' => ['details'],
            'serp_snippet_cannibalization' => ['details'],
            'landing_query_mismatch' => ['details'],
            'commercial_missing_contacts' => ['details'],
            'commercial_missing_price' => ['details'],
            'commercial_missing_cta' => ['details'],
            'commercial_missing_delivery' => ['details'],
            'commercial_missing_payment' => ['details'],
            'commercial_missing_stock' => ['details'],
            'commercial_missing_reviews' => ['details'],
            'broken_image' => ['details'],
            'heavy_image' => ['details'],
            'error_spike' => ['details'],
            'psi_mobile' => ['details'],
            'psi_desktop' => ['details'],
            'similar_pages' => ['details'],
            'duplicate_url_variants' => ['details'],
            'site_availability' => ['details'],
            'index_count_mismatch' => ['details'],
            'no_outbound_internal' => ['details'],
            'risky_query_params' => ['details'],
            'pagination_param' => ['details'],
            'missing_hsts' => ['details'],
            'missing_x_frame_options' => ['details'],
            'missing_x_content_type_options' => ['details'],
            'serp_not_indexed' => ['details'],
            'serp_snippet_source' => ['details'],
            'probable_affiliate' => ['details'],
            'missing_csp' => ['details'],
            'missing_referrer_policy' => ['details'],
            'missing_permissions_policy' => ['details'],
            'missing_coop' => ['details'],
            'missing_coep' => ['details'],
            'missing_corp' => ['details'],
            'missing_charset' => ['details'],
        ];

        return $map[$code] ?? [];
    }

    /**
     * Фильтры инвентаря «Картинки проверки».
     *
     * @return list<array<string,mixed>>
     */
    public static function crawlImagesFields(): array
    {
        return [
            [
                'key' => 'url',
                'label' => 'URL картинки',
                'param' => 'q_url',
                'group' => 'main',
                'tip' => "Часть URL файла картинки.\nМожно на русской или английской раскладке.",
            ],
            [
                'key' => 'page',
                'label' => 'Страница',
                'param' => 'q_page',
                'group' => 'main',
                'tip' => "Страница HTML, на которой стоит этот img.",
            ],
            [
                'key' => 'status',
                'label' => 'Код ответа',
                'param' => 'q_status',
                'type' => 'multiselect',
                'group' => 'main',
                'placeholder' => 'Все коды',
                'options' => self::imageStatusFilterOptions(),
                'tip' => "HTTP-код файла картинки (HEAD при агрегации).\n2xx / 4xx / err — без проверки.\nНа старых проверках код есть не у всех — нужен новый обход.",
            ],
            [
                'key' => 'ext',
                'label' => 'Тип файла',
                'param' => 'q_ext',
                'type' => 'multiselect',
                'group' => 'main',
                'placeholder' => 'Все типы',
                'options' => [
                    'webp' => 'webp',
                    'png' => 'png',
                    'jpg' => 'jpg / jpeg',
                    'gif' => 'gif',
                    'svg' => 'svg',
                    'avif' => 'avif',
                    'ico' => 'ico',
                    'other' => 'другое',
                ],
                'tip' => "По расширению в пути URL.\nМожно выбрать несколько.",
            ],
            [
                'key' => 'broken',
                'label' => 'Битая',
                'param' => 'q_broken',
                'type' => 'select',
                'group' => 'main',
                'options' => [
                    '' => 'Все',
                    '1' => 'Да (ошибка ответа)',
                    '0' => 'Нет (OK)',
                ],
                'tip' => "Да — файл не отдался (4xx/5xx/сеть).\nНет — успешный ответ.\nБез данных после HEAD — не попадёт ни в «да», ни в «нет».",
            ],
            [
                'key' => 'has_alt',
                'label' => 'Alt',
                'param' => 'q_has_alt',
                'type' => 'select',
                'group' => 'more',
                'options' => [
                    '' => 'Все',
                    '1' => 'Есть',
                    '0' => 'Нет / пустой',
                ],
                'tip' => "Есть — непустой alt.\nНет — атрибута нет или пустой.\nДля старых проверок без alt — нужен новый обход.",
            ],
            [
                'key' => 'alt',
                'label' => 'Текст alt',
                'param' => 'q_alt',
                'group' => 'more',
                'tip' => "Поиск по тексту alt (после нового обхода).",
            ],
            [
                'key' => 'size_kb',
                'label' => 'Размер, Кб',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_size_kb_min',
                'param_max' => 'q_size_kb_max',
                'tip' => "Вес файла по Content-Length (HEAD).\nБез HEAD в строке — фильтр её не найдёт.",
            ],
            [
                'key' => 'width',
                'label' => 'Ширина, px',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_width_min',
                'param_max' => 'q_width_max',
                'tip' => "Атрибут width в HTML (не реальный размер файла).",
            ],
            [
                'key' => 'height',
                'label' => 'Высота, px',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_height_min',
                'param_max' => 'q_height_max',
                'tip' => "Атрибут height в HTML.",
            ],
            [
                'key' => 'https',
                'label' => 'HTTPS',
                'param' => 'q_https',
                'type' => 'select',
                'group' => 'more',
                'options' => [
                    '' => 'Все',
                    '1' => 'HTTPS',
                    '0' => 'HTTP',
                ],
                'tip' => "Протокол в URL картинки.",
            ],
            [
                'key' => 'external',
                'label' => 'Внешняя',
                'param' => 'q_external',
                'type' => 'select',
                'group' => 'more',
                'options' => [
                    '' => 'Все',
                    '1' => 'С другого хоста',
                    '0' => 'С того же хоста',
                ],
                'tip' => "Хост картинки ≠ хост страницы.",
            ],
            [
                'key' => 'loading',
                'label' => 'loading',
                'param' => 'q_loading',
                'type' => 'select',
                'group' => 'more',
                'options' => [
                    '' => 'Все',
                    'lazy' => 'lazy',
                    'eager' => 'eager',
                    'none' => 'Нет атрибута',
                ],
                'tip' => "Атрибут loading у img.",
            ],
            [
                'key' => 'url_len',
                'label' => 'Длина URL',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_url_len_min',
                'param_max' => 'q_url_len_max',
                'tip' => "Число символов в URL картинки.",
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function imageStatusFilterOptions(): array
    {
        return [
            '2xx' => '2xx',
            '3xx' => '3xx',
            '4xx' => '4xx',
            '5xx' => '5xx',
            '200' => '200',
            '301' => '301',
            '302' => '302',
            '403' => '403',
            '404' => '404',
            '500' => '500',
            'err' => 'Сеть / без кода',
            'unknown' => 'Не проверяли',
        ];
    }

    /**
     * Фильтры инвентаря «Страницы проверки» (все условия через AND).
     *
     * @param  int|null  $crawlId
     * @return list<array<string,mixed>>
     */
    public static function crawlPagesFields(?int $crawlId = null): array
    {
        $contentTypeOptions = $crawlId ? self::contentTypeOptionsForCrawl($crawlId) : [];

        return [
            [
                'key' => 'url',
                'label' => 'URL',
                'param' => 'q_url',
                'group' => 'main',
                'tip' => "Часть адреса страницы.\nМожно на русской или английской раскладке.",
            ],
            [
                'key' => 'title',
                'label' => 'TITLE',
                'param' => 'q_title',
                'group' => 'main',
                'tip' => "Поиск по TITLE страницы.",
            ],
            [
                'key' => 'description',
                'label' => 'Description',
                'param' => 'q_description',
                'group' => 'main',
                'tip' => "Поиск по meta description.",
            ],
            [
                'key' => 'h1',
                'label' => 'H1',
                'param' => 'q_h1',
                'group' => 'main',
                'tip' => "Поиск по тексту H1.",
            ],
            [
                'key' => 'index',
                'label' => 'Индекс',
                'param' => 'q_index',
                'type' => 'select',
                'group' => 'main',
                'options' => [
                    '' => 'Все',
                    'index' => 'Открыты (index)',
                    'noindex' => 'Закрыты (noindex)',
                ],
                'tip' => "Закрыты — meta robots / X-Robots с noindex.\nОткрыты — без noindex.",
            ],
            [
                'key' => 'words',
                'label' => 'Слов',
                'type' => 'range',
                'group' => 'main',
                'param_min' => 'q_words_min',
                'param_max' => 'q_words_max',
                'tip' => "Диапазон числа слов на странице.\nМожно указать только «от» или только «до».",
            ],
            [
                'key' => 'status',
                'label' => 'Код ответа',
                'type' => 'multiselect',
                'group' => 'main',
                'param' => 'q_status',
                'options' => self::statusCodeFilterOptions(),
                'placeholder' => 'Все коды',
                'tip' => "Можно выбрать несколько кодов.\nГруппы 2xx / 3xx / 4xx / 5xx — целый класс ответов.",
            ],
            [
                'key' => 'depth',
                'label' => 'Глубина',
                'type' => 'range',
                'group' => 'main',
                'param_min' => 'q_depth_min',
                'param_max' => 'q_depth_max',
                'tip' => "Глубина кликов от главной.",
            ],

            [
                'key' => 'h2',
                'label' => 'H2',
                'param' => 'q_h2',
                'group' => 'more',
                'tip' => "Поиск по тексту H2 (после нового обхода с текстами заголовков).",
            ],
            [
                'key' => 'canonical',
                'label' => 'Canonical',
                'param' => 'q_canonical',
                'group' => 'more',
                'tip' => "Поиск по значению rel=canonical.",
            ],
            [
                'key' => 'robots',
                'label' => 'Meta Robots',
                'param' => 'q_robots',
                'group' => 'more',
                'tip' => "Поиск по тексту meta robots (например nofollow).",
            ],
            [
                'key' => 'keywords',
                'label' => 'Keywords',
                'param' => 'q_keywords',
                'group' => 'more',
                'tip' => "Поиск по meta keywords.",
            ],
            [
                'key' => 'content_type',
                'label' => 'Тип контента',
                'param' => 'q_content_type',
                'type' => 'multiselect',
                'group' => 'more',
                'placeholder' => 'Все типы',
                'options' => $contentTypeOptions,
                'tip' => "Типы из этой проверки.\nМожно выбрать несколько.",
            ],
            [
                'key' => 'final_url',
                'label' => 'Финальный URL',
                'param' => 'q_final_url',
                'group' => 'more',
                'tip' => "URL после редиректов.",
            ],
            [
                'key' => 'https',
                'label' => 'HTTPS',
                'param' => 'q_https',
                'type' => 'select',
                'group' => 'more',
                'options' => [
                    '' => 'Все',
                    '1' => 'HTTPS',
                    '0' => 'HTTP',
                ],
                'tip' => "Протокол в адресе страницы.",
            ],
            [
                'key' => 'via',
                'label' => 'Откуда в обходе',
                'param' => 'q_via',
                'type' => 'multiselect',
                'group' => 'more',
                'placeholder' => 'Все источники',
                'options' => [
                    'sitemap' => 'sitemap',
                    'link' => 'По ссылке',
                    'seed' => 'Посев',
                    'home' => 'Главная',
                ],
                'tip' => "Можно выбрать несколько источников.\nКак URL попал в эту проверку.",
            ],
            [
                'key' => 'title_len',
                'label' => 'Длина TITLE',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_title_len_min',
                'param_max' => 'q_title_len_max',
                'tip' => "Число символов в TITLE.",
            ],
            [
                'key' => 'desc_len',
                'label' => 'Длина description',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_desc_len_min',
                'param_max' => 'q_desc_len_max',
                'tip' => "Число символов в description.",
            ],
            [
                'key' => 'size_kb',
                'label' => 'Размер, Кб',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_size_kb_min',
                'param_max' => 'q_size_kb_max',
                'tip' => "Размер ответа сервера в килобайтах.",
            ],
            [
                'key' => 'h1_count',
                'label' => 'H1 шт.',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_h1_count_min',
                'param_max' => 'q_h1_count_max',
                'tip' => "Сколько тегов H1 на странице.",
            ],
            [
                'key' => 'h2_count',
                'label' => 'H2 шт.',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_h2_count_min',
                'param_max' => 'q_h2_count_max',
                'tip' => "Сколько тегов H2 на странице.",
            ],
            [
                'key' => 'img',
                'label' => 'Img',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_img_min',
                'param_max' => 'q_img_max',
                'tip' => "Число тегов img.",
            ],
            [
                'key' => 'img_no_alt',
                'label' => 'Img без alt',
                'param' => 'q_img_no_alt',
                'type' => 'select',
                'group' => 'more',
                'options' => [
                    '' => 'Все',
                    '1' => 'Есть',
                    '0' => 'Нет',
                ],
                'tip' => "Есть — хотя бы одна картинка без alt.\nНет — все img с alt (или картинок нет).\nВ таблице видно точное число.",
            ],
            [
                'key' => 'out_links',
                'label' => 'Внутр. ссылки',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_out_links_min',
                'param_max' => 'q_out_links_max',
                'tip' => "Исходящие внутренние ссылки.",
            ],
            [
                'key' => 'ext_links',
                'label' => 'Внеш. ссылки',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_ext_links_min',
                'param_max' => 'q_ext_links_max',
                'tip' => "Исходящие внешние ссылки.",
            ],
            [
                'key' => 'text_len',
                'label' => 'Символов текста',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_text_len_min',
                'param_max' => 'q_text_len_max',
                'tip' => "Длина текста страницы в символах.",
            ],
            [
                'key' => 'url_len',
                'label' => 'Длина URL',
                'type' => 'range',
                'group' => 'more',
                'param_min' => 'q_url_len_min',
                'param_max' => 'q_url_len_max',
                'tip' => "Число символов в URL.",
            ],
        ];
    }

    /**
     * Distinct Content-Type этой проверки для мультиселекта.
     *
     * @return array<string,string>
     */
    public static function contentTypeOptionsForCrawl(int $crawlId): array
    {
        $rows = \App\SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull('content_type')
            ->where('content_type', '!=', '')
            ->distinct()
            ->orderBy('content_type')
            ->limit(100)
            ->pluck('content_type');

        $out = [];
        foreach ($rows as $raw) {
            $ct = trim((string) $raw);
            if ($ct === '' || isset($out[$ct])) {
                continue;
            }
            $out[$ct] = mb_strlen($ct) > 52 ? (mb_substr($ct, 0, 49) . '…') : $ct;
        }

        return $out;
    }

    /**
     * Опции фильтра HTTP-кодов (группы + частые коды).
     *
     * @return array<string,string>
     */
    public static function statusCodeFilterOptions(): array
    {
        return [
            '2xx' => '2xx — успех',
            '200' => '200 OK',
            '201' => '201 Created',
            '204' => '204 No Content',
            '3xx' => '3xx — редирект',
            '301' => '301 Moved Permanently',
            '302' => '302 Found',
            '303' => '303 See Other',
            '307' => '307 Temporary Redirect',
            '308' => '308 Permanent Redirect',
            '4xx' => '4xx — ошибка клиента',
            '400' => '400 Bad Request',
            '401' => '401 Unauthorized',
            '403' => '403 Forbidden',
            '404' => '404 Not Found',
            '410' => '410 Gone',
            '429' => '429 Too Many Requests',
            '5xx' => '5xx — ошибка сервера',
            '500' => '500 Internal Server Error',
            '502' => '502 Bad Gateway',
            '503' => '503 Service Unavailable',
            '504' => '504 Gateway Timeout',
        ];
    }

    /**
     * @return array<string,string> key => value
     */
    public static function valuesFromRequest(Request $request, string $code, ?int $crawlId = null): array
    {
        $out = [];
        foreach (self::fieldsForCode($code, $crawlId) as $field) {
            $type = (string) ($field['type'] ?? 'text');
            if ($type === 'range') {
                $minRaw = (string) $request->input($field['param_min'] ?? '', '');
                $maxRaw = (string) $request->input($field['param_max'] ?? '', '');
                $min = self::parseIntFilter($minRaw);
                $max = self::parseIntFilter($maxRaw);
                if ($min !== null) {
                    $out[$field['key'] . '_min'] = (string) $min;
                }
                if ($max !== null) {
                    $out[$field['key'] . '_max'] = (string) $max;
                }
                continue;
            }

            if ($type === 'multiselect') {
                $param = (string) ($field['param'] ?? ('q_' . $field['key']));
                $raw = $request->input($param, []);
                if (is_string($raw)) {
                    if ($raw === '') {
                        $raw = [];
                    } else {
                        $parts = preg_split('/\s*,\s*/', $raw);
                        $raw = is_array($parts) ? $parts : [];
                    }
                }
                if (! is_array($raw)) {
                    $raw = [];
                }
                $allowed = array_map('strval', array_keys($field['options'] ?? []));
                $picked = [];
                foreach ($raw as $item) {
                    $item = trim((string) $item);
                    if ($item === '' || ! in_array($item, $allowed, true)) {
                        continue;
                    }
                    $picked[$item] = true;
                }
                if ($picked !== []) {
                    $out[$field['key']] = implode(',', array_keys($picked));
                }
                continue;
            }

            $param = (string) ($field['param'] ?? ('q_' . $field['key']));
            $v = trim((string) $request->input($param, ''));
            if ($v === '') {
                continue;
            }
            if (($field['key'] ?? '') === 'redirect_kind'
                && ! in_array($v, ['other_page', 'slash_only'], true)
            ) {
                continue;
            }
            if (in_array($field['key'] ?? '', ['discovered_via', 'via'], true)
                && ! in_array($v, ['sitemap', 'link', 'seed', 'home'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'url_kind'
                && ! in_array($v, ['clean', 'params'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'index'
                && ! in_array($v, ['index', 'noindex'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'https'
                && ! in_array($v, ['0', '1'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'img_no_alt'
                && ! in_array($v, ['0', '1'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'has_alt'
                && ! in_array($v, ['0', '1'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'external'
                && ! in_array($v, ['0', '1'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'broken'
                && ! in_array($v, ['0', '1'], true)
            ) {
                continue;
            }
            if (($field['key'] ?? '') === 'loading'
                && ! in_array($v, ['lazy', 'eager', 'none'], true)
            ) {
                continue;
            }
            $out[$field['key']] = $v;
        }

        if ($code === 'crawl_pages') {
            $batch = (string) $request->input('q_batch', '');
            $batch = str_replace(["\r\n", "\r"], "\n", $batch);
            $batch = trim($batch);
            if ($batch !== '') {
                $lines = SiteAuditBatchUrlLookup::parseLines($batch);
                if ($lines !== []) {
                    $out['batch'] = implode("\n", $lines);
                }
            }
        }

        return $out;
    }

    /**
     * Целое из фильтра: «1 000», «1000», пусто → null.
     */
    public static function parseIntFilter(string $raw): ?int
    {
        $raw = trim(str_replace(["\xC2\xA0", ' '], '', $raw));
        if ($raw === '' || ! preg_match('/^-?\d+$/', $raw)) {
            return null;
        }

        return (int) $raw;
    }

    public static function hasActive(array $values): bool
    {
        return $values !== [];
    }

    /**
     * Фильтр findings (+ join pages при title/description/h1/canonical).
     */
    public static function applyToFindings(Builder $query, int $crawlId, array $values): Builder
    {
        if (isset($values['url'])) {
            self::applySmartLike($query, 'site_audit_findings.url', $values['url']);
        }

        $pageCols = ['title', 'description', 'h1', 'canonical'];
        foreach ($pageCols as $col) {
            if (! isset($values[$col])) {
                continue;
            }
            $term = $values[$col];
            $query->whereExists(function ($sub) use ($crawlId, $col, $term) {
                $sub->selectRaw('1')
                    ->from('site_audit_pages')
                    ->whereColumn('site_audit_pages.url_hash', 'site_audit_findings.url_hash')
                    ->where('site_audit_pages.crawl_id', $crawlId);
                self::applySmartLike($sub, 'site_audit_pages.' . $col, $term);
            });
        }

        if (isset($values['details'])) {
            // meta_json + куски meta для удобства
            self::applySmartLike($query, 'site_audit_findings.meta_json', $values['details']);
        }

        if (isset($values['redirect_kind'])) {
            self::applyRedirectKind($query, (string) $values['redirect_kind']);
        }

        if (isset($values['discovered_via'])) {
            $via = (string) $values['discovered_via'];
            if (in_array($via, ['sitemap', 'link', 'seed', 'home'], true)) {
                $query->whereExists(function ($sub) use ($crawlId, $via) {
                    $sub->selectRaw('1')
                        ->from('site_audit_pages')
                        ->whereColumn('site_audit_pages.url_hash', 'site_audit_findings.url_hash')
                        ->where('site_audit_pages.crawl_id', $crawlId)
                        ->where('site_audit_pages.discovered_via', $via);
                });
            }
        }

        if (isset($values['url_kind'])) {
            $kind = (string) $values['url_kind'];
            if ($kind === 'params') {
                $query->where('site_audit_findings.url', 'like', '%?%');
            } elseif ($kind === 'clean') {
                $query->where('site_audit_findings.url', 'not like', '%?%');
            }
        }

        return $query;
    }

    /**
     * Фильтр редиректов: слэш-only vs смена страницы (url + meta.final / meta.slash_only).
     */
    public static function applyRedirectKind(Builder $query, string $kind): void
    {
        if (! in_array($kind, ['other_page', 'slash_only'], true)) {
            return;
        }

        $ids = [];
        (clone $query)
            ->select(['site_audit_findings.id', 'site_audit_findings.url', 'site_audit_findings.meta_json'])
            ->orderBy('site_audit_findings.id')
            ->chunkById(400, function ($rows) use (&$ids, $kind) {
                foreach ($rows as $row) {
                    if (self::findingMatchesRedirectKind($row, $kind)) {
                        $ids[] = (int) $row->id;
                    }
                }
            }, 'site_audit_findings.id', 'id');

        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('site_audit_findings.id', $ids);
    }

    /**
     * @param object $row {url, meta_json}
     */
    public static function findingMatchesRedirectKind($row, string $kind): bool
    {
        $meta = $row->meta_json ?? null;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($meta)) {
            $meta = [];
        }

        if (array_key_exists('slash_only', $meta) || isset($meta['redirect_kind'])) {
            $slash = ! empty($meta['slash_only'])
                || (($meta['redirect_kind'] ?? '') === 'slash_only');
            if (($meta['redirect_kind'] ?? '') === 'loop') {
                $slash = false;
            }

            return $kind === 'slash_only' ? $slash : ! $slash;
        }

        $final = (string) ($meta['final'] ?? '');
        if ($final === '' && ! empty($meta['path']) && is_array($meta['path'])) {
            $final = (string) end($meta['path']);
        }
        $slash = $final !== ''
            && SiteAuditRedirectChain::isSlashOnlyRedirect((string) $row->url, $final);

        return $kind === 'slash_only' ? $slash : ! $slash;
    }

    /**
     * Фильтр pages (canonical report и инвентарь страниц). Все условия через AND.
     */
    public static function applyToPages(Builder $query, array $values): Builder
    {
        if (isset($values['url'])) {
            self::applySmartLike($query, 'site_audit_pages.url', $values['url']);
        }
        foreach (['title', 'description', 'h1', 'canonical'] as $col) {
            if (isset($values[$col])) {
                self::applySmartLike($query, 'site_audit_pages.' . $col, $values[$col]);
            }
        }
        if (isset($values['robots'])) {
            self::applySmartLike($query, 'site_audit_pages.robots_meta', $values['robots']);
        }
        if (isset($values['keywords'])) {
            self::applySmartLike($query, 'site_audit_pages.keywords_meta', $values['keywords']);
        }
        if (isset($values['content_type'])) {
            $types = self::multiFilterTokens($values['content_type']);
            if (count($types) === 1) {
                $query->where('site_audit_pages.content_type', $types[0]);
            } elseif (count($types) > 1) {
                $query->whereIn('site_audit_pages.content_type', $types);
            }
        }
        if (isset($values['final_url'])) {
            self::applySmartLike($query, 'site_audit_pages.final_url', $values['final_url']);
        }
        if (isset($values['h2'])) {
            // Текст H2 лежит в headings_json (и частично как JSON-строка).
            self::applySmartLike($query, 'site_audit_pages.headings_json', $values['h2']);
        }

        if (($values['index'] ?? '') === 'noindex') {
            $query->where('site_audit_pages.noindex', 1);
        } elseif (($values['index'] ?? '') === 'index') {
            $query->where(function ($q) {
                $q->where('site_audit_pages.noindex', 0)
                    ->orWhereNull('site_audit_pages.noindex');
            });
        }

        if (($values['https'] ?? '') === '1') {
            $query->where('site_audit_pages.url', 'like', 'https://%');
        } elseif (($values['https'] ?? '') === '0') {
            $query->where('site_audit_pages.url', 'like', 'http://%')
                ->where('site_audit_pages.url', 'not like', 'https://%');
        }

        $viaParts = self::multiFilterTokens($values['via'] ?? '');
        $viaParts = array_values(array_filter($viaParts, static function ($v) {
            return in_array($v, ['sitemap', 'link', 'seed', 'home'], true);
        }));
        if (count($viaParts) === 1) {
            $query->where('site_audit_pages.discovered_via', $viaParts[0]);
        } elseif (count($viaParts) > 1) {
            $query->whereIn('site_audit_pages.discovered_via', $viaParts);
        }

        self::applyColumnIntRange($query, 'site_audit_pages.word_count', $values, 'words');
        self::applyStatusCodeFilter($query, $values);
        self::applyColumnIntRange($query, 'site_audit_pages.click_depth', $values, 'depth');
        self::applyColumnIntRange($query, 'site_audit_pages.h1_count', $values, 'h1_count');
        self::applyColumnIntRange($query, 'site_audit_pages.h2_count', $values, 'h2_count');
        self::applyColumnIntRange($query, 'site_audit_pages.img_count', $values, 'img');

        if (($values['img_no_alt'] ?? '') === '1') {
            $query->where('site_audit_pages.img_without_alt', '>=', 1);
        } elseif (($values['img_no_alt'] ?? '') === '0') {
            $query->where(function ($q) {
                $q->where('site_audit_pages.img_without_alt', 0)
                    ->orWhereNull('site_audit_pages.img_without_alt');
            });
        } else {
            // Старые ссылки с от–до.
            self::applyColumnIntRange($query, 'site_audit_pages.img_without_alt', $values, 'img_no_alt');
        }
        self::applyColumnIntRange($query, 'site_audit_pages.text_len', $values, 'text_len');

        self::applyColumnIntRange($query, 'CHAR_LENGTH(COALESCE(site_audit_pages.title, \'\'))', $values, 'title_len', true);
        self::applyColumnIntRange($query, 'CHAR_LENGTH(COALESCE(site_audit_pages.description, \'\'))', $values, 'desc_len', true);
        self::applyColumnIntRange($query, 'CHAR_LENGTH(site_audit_pages.url)', $values, 'url_len', true);
        self::applyColumnIntRange($query, 'COALESCE(JSON_LENGTH(site_audit_pages.out_links_json), 0)', $values, 'out_links', true);
        self::applyColumnIntRange($query, 'COALESCE(JSON_LENGTH(site_audit_pages.ext_links_json), 0)', $values, 'ext_links', true);

        // Размер: фильтр в Кб → байты.
        $sizeMin = isset($values['size_kb_min']) ? (int) $values['size_kb_min'] : null;
        $sizeMax = isset($values['size_kb_max']) ? (int) $values['size_kb_max'] : null;
        if ($sizeMin !== null) {
            $query->where('site_audit_pages.size_bytes', '>=', $sizeMin * 1024);
        }
        if ($sizeMax !== null) {
            $query->where('site_audit_pages.size_bytes', '<=', $sizeMax * 1024);
        }

        return $query;
    }

    /**
     * Фильтр по кодам ответа: 200,404 и/или классы 2xx…5xx.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  array<string,string>  $values
     */
    private static function applyStatusCodeFilter($query, array $values): void
    {
        $raw = trim((string) ($values['status'] ?? ''));
        if ($raw === '') {
            // Совместимость со старыми ссылками от–до.
            self::applyColumnIntRange($query, 'site_audit_pages.status_code', $values, 'status');

            return;
        }

        $exact = [];
        $classes = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            if (preg_match('/^([2-5])xx$/i', $token, $m)) {
                $classes[] = (int) $m[1] * 100;
                continue;
            }
            if (preg_match('/^\d{3}$/', $token)) {
                $exact[] = (int) $token;
            }
        }
        $exact = array_values(array_unique($exact));
        $classes = array_values(array_unique($classes));
        if ($exact === [] && $classes === []) {
            return;
        }

        $query->where(function ($q) use ($exact, $classes) {
            $first = true;
            if ($exact !== []) {
                $q->whereIn('site_audit_pages.status_code', $exact);
                $first = false;
            }
            foreach ($classes as $base) {
                if ($first) {
                    $q->whereBetween('site_audit_pages.status_code', [$base, $base + 99]);
                    $first = false;
                } else {
                    $q->orWhereBetween('site_audit_pages.status_code', [$base, $base + 99]);
                }
            }
        });
    }

    /**
     * Токены мультифильтра («a,b» → ['a','b']).
     *
     * @return list<string>
     */
    public static function multiFilterTokensPublic($raw): array
    {
        return self::multiFilterTokens($raw);
    }

    /**
     * Токены мультифильтра («a,b» → ['a','b']).
     *
     * @return list<string>
     */
    private static function multiFilterTokens($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $token) {
            $token = trim((string) $token);
            if ($token === '' || isset($out[$token])) {
                continue;
            }
            $out[$token] = true;
        }

        return array_keys($out);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private static function applyColumnIntRange($query, string $columnOrExpr, array $values, string $baseKey, bool $raw = false): void
    {
        $minKey = $baseKey . '_min';
        $maxKey = $baseKey . '_max';
        $min = isset($values[$minKey]) ? (int) $values[$minKey] : null;
        $max = isset($values[$maxKey]) ? (int) $values[$maxKey] : null;
        if ($min === null && $max === null) {
            return;
        }
        if ($raw) {
            if ($min !== null) {
                $query->whereRaw($columnOrExpr . ' >= ?', [$min]);
            }
            if ($max !== null) {
                $query->whereRaw($columnOrExpr . ' <= ?', [$max]);
            }

            return;
        }
        if ($min !== null) {
            $query->where($columnOrExpr, '>=', $min);
        }
        if ($max !== null) {
            $query->where($columnOrExpr, '<=', $max);
        }
    }

    /**
     * Query-string для ссылок CSV/пагинации.
     *
     * @return array<string,string>
     */
    public static function queryParams(array $values): array
    {
        $params = [];
        foreach ($values as $key => $val) {
            if ($key === 'status' && is_string($val) && strpos($val, ',') !== false) {
                $params['q_status'] = array_values(array_filter(array_map('trim', explode(',', $val))));
                continue;
            }
            if ($key === 'status' && is_string($val) && $val !== '') {
                $params['q_status'] = [$val];
                continue;
            }
            $params['q_' . $key] = $val;
        }

        return $params;
    }

    public static function applySmartLike($query, string $column, string $term): void
    {
        $needles = self::needles($term);
        if ($needles === []) {
            return;
        }

        $query->where(function ($q) use ($column, $needles) {
            foreach ($needles as $needle) {
                $q->orWhere($column, 'like', '%' . self::escapeLike($needle) . '%');
            }
        });
    }

    /**
     * @return string[]
     */
    public static function needles(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $flipped = self::flipLayout($term);
        $out = [$term];
        if ($flipped !== '' && mb_strtolower($flipped) !== mb_strtolower($term)) {
            $out[] = $flipped;
        }

        return array_values(array_unique($out));
    }

    /** Совпадение строки с умным поиском (как LIKE в фильтрах). */
    public static function smartContains(string $haystack, string $term): bool
    {
        $hay = mb_strtolower($haystack);
        foreach (self::needles($term) as $needle) {
            if ($needle !== '' && mb_strpos($hay, mb_strtolower($needle)) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function flipLayout(string $text): string
    {
        $map = self::flipMap();
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        foreach ($chars as $ch) {
            $out .= $map[$ch] ?? $ch;
        }

        return $out;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return array<string,string>
     */
    private static function flipMap(): array
    {
        if (self::$flipMap !== null) {
            return self::$flipMap;
        }

        $pairs = [
            ['`', 'ё'], ['q', 'й'], ['w', 'ц'], ['e', 'у'], ['r', 'к'], ['t', 'е'],
            ['y', 'н'], ['u', 'г'], ['i', 'ш'], ['o', 'щ'], ['p', 'з'], ['[', 'х'],
            [']', 'ъ'], ['a', 'ф'], ['s', 'ы'], ['d', 'в'], ['f', 'а'], ['g', 'п'],
            ['h', 'р'], ['j', 'о'], ['k', 'л'], ['l', 'д'], [';', 'ж'], ["'", 'э'],
            ['z', 'я'], ['x', 'ч'], ['c', 'с'], ['v', 'м'], ['b', 'и'], ['n', 'т'],
            ['m', 'ь'], [',', 'б'], ['.', 'ю'], ['/', '.'],
            ['~', 'Ё'], ['Q', 'Й'], ['W', 'Ц'], ['E', 'У'], ['R', 'К'], ['T', 'Е'],
            ['Y', 'Н'], ['U', 'Г'], ['I', 'Ш'], ['O', 'Щ'], ['P', 'З'], ['{', 'Х'],
            ['}', 'Ъ'], ['A', 'Ф'], ['S', 'Ы'], ['D', 'В'], ['F', 'А'], ['G', 'П'],
            ['H', 'Р'], ['J', 'О'], ['K', 'Л'], ['L', 'Д'], [':', 'Ж'], ['"', 'Э'],
            ['Z', 'Я'], ['X', 'Ч'], ['C', 'С'], ['V', 'М'], ['B', 'И'], ['N', 'Т'],
            ['M', 'Ь'], ['<', 'Б'], ['>', 'Ю'], ['?', ','],
        ];

        $map = [];
        foreach ($pairs as $pair) {
            $map[$pair[0]] = $pair[1];
            $map[$pair[1]] = $pair[0];
        }
        self::$flipMap = $map;

        return self::$flipMap;
    }
}
