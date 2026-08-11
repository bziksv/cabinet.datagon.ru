<?php

namespace App\Services\SiteAudit;

class SiteAuditFindingPresenter
{
    public static function severityLabel(string $severity): string
    {
        $map = [
            'critical' => 'Грубые',
            'other' => 'Прочие',
            'warning' => 'Предупреждения',
            'info' => 'Инфо',
        ];

        return $map[$severity] ?? $severity;
    }

    /** Короткая метка для дерева отчётов: (грубое), (замечание)… */
    public static function severityTag(string $severity): string
    {
        $map = [
            'critical' => 'грубое',
            'other' => 'прочие',
            'warning' => 'замечание',
            'info' => 'инфо',
        ];

        return $map[$severity] ?? $severity;
    }

    /**
     * Богатая ячейка «Детали» (HTML). null → рисовать обычный metaLine.
     */
    public static function metaDetailsHtml(string $code, $meta, ?string $url = null): ?string
    {
        if (! is_array($meta) || ! $meta) {
            return null;
        }

        if ($code === 'duplicate_url_variants') {
            return self::duplicateUrlVariantsHtml($meta, $url);
        }

        if ($code === 'page_has_bad_links') {
            return self::badLinksDetailsHtml($meta);
        }

        if ($code === 'links_nofollow') {
            return self::nofollowLinksDetailsHtml($meta);
        }

        if ($code === 'page_has_broken_external_links') {
            return self::brokenExternalPageDetailsHtml($meta);
        }

        if ($code === 'broken_external_link') {
            return self::brokenExternalLinkDetailsHtml($meta);
        }

        if ($code === 'serp_title_mismatch') {
            return self::serpTitleMismatchHtml($meta);
        }

        if ($code === 'serp_snippet_source') {
            return self::serpSnippetSourceHtml($meta);
        }

        if ($code === 'lost_file') {
            return self::lostFileDetailsHtml($meta);
        }

        if ($code === 'broken_internal_link') {
            return self::brokenInternalLinkDetailsHtml($meta);
        }

        if (in_array($code, ['http_4xx', 'http_5xx', 'unreachable'], true)) {
            return self::httpStatusDetailsHtml($code, $meta, $url);
        }

        if ($code === 'external_assets') {
            return self::externalAssetsDetailsHtml($meta);
        }

        if ($code === 'external_links') {
            return self::externalLinksDetailsHtml($meta);
        }

        if ($code === 'deep_pages') {
            return self::deepPagesDetailsHtml($meta);
        }

        if (in_array($code, ['redirect', 'redirect_chain_long', 'redirect_loop'], true)) {
            return self::redirectDetailsHtml($meta, $code);
        }

        if (in_array($code, [
            'canonical_foreign',
            'canonical_not_self',
            'pages_with_canonical',
            'similar_pages',
            'landing_url_changed',
            'landing_plagiarism_suspect',
            'landing_plagiarism_external',
            'mixed_content',
            'insecure_form',
            'broken_image',
            'www_both_available',
            'http_https_both_available',
            'page_has_broken_links',
            'duplicate_links',
            'keyword_cannibalization',
            'ad_cannibalization',
            'robots_txt_error',
        ], true)) {
            // URL в деталях — кликабельно и без обрезки (metaLine оставляем для экспорта/CSV).
            $plain = self::metaLine($code, $meta, $url);
            if ($plain === '' || $plain === '—') {
                return null;
            }

            return self::linkifyUrlsInText($plain);
        }

        if ($code !== 'html_critical_errors') {
            return null;
        }

        $n = (int) ($meta['count'] ?? 0);
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        if ($samples === []) {
            return null;
        }

        $parts = [];
        $hints = [];
        if ($n > 0) {
            $parts[] = '<span class="cabinet-sa-html-err__count">ошибок: ' . $n . '</span>';
        }

        foreach (array_slice($samples, 0, 5) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $msg = trim((string) ($sample['message'] ?? ''));
            if ($msg === '') {
                continue;
            }
            $line = isset($sample['line']) && (int) $sample['line'] > 0
                ? (int) $sample['line']
                : null;
            $label = e(self::clip($msg, 90));
            $q = 'html libxml "' . $msg . '"';
            $href = e(self::googleSearchUrl($q));
            $lineBit = $line !== null
                ? ' <span class="text-muted">стр. ' . $line . '</span>'
                : '';
            $hint = SiteAuditFindingHelp::htmlErrorHint($msg);
            $titleAttr = $hint !== null
                ? e($hint)
                : 'Искать в Google';
            $parts[] = '<a class="cabinet-sa-html-err__msg" href="' . $href
                . '" target="_blank" rel="noopener noreferrer" title="' . $titleAttr . '">'
                . $label . '</a>' . $lineBit;
            if ($hint !== null && ! in_array($hint, $hints, true)) {
                $hints[] = $hint;
            }
        }

        if (count($parts) <= ($n > 0 ? 1 : 0)) {
            return null;
        }

        $html = '<div class="cabinet-sa-html-err">'
            . '<div class="cabinet-sa-html-err__line">' . implode(' · ', $parts) . '</div>';
        foreach (array_slice($hints, 0, 2) as $hint) {
            $html .= '<div class="cabinet-sa-html-err__tip">' . e($hint) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function duplicateUrlVariantsHtml(array $meta, ?string $rowUrl = null): ?string
    {
        $variants = [];
        if (isset($meta['variants']) && is_array($meta['variants'])) {
            foreach ($meta['variants'] as $v) {
                $v = trim((string) $v);
                if ($v !== '' && ! in_array($v, $variants, true)) {
                    $variants[] = $v;
                }
            }
        }
        // В «Деталях» только другие адреса — текущий уже в колонке URL.
        $others = $variants;
        if ($rowUrl !== null && $rowUrl !== '') {
            $others = array_values(array_filter($variants, static function ($v) use ($rowUrl) {
                return $v !== $rowUrl;
            }));
        }
        if ($others === []) {
            return null;
        }

        $diff = self::describeUrlVariantDiff($variants);
        $html = '<div class="cabinet-sa-url-variants">';
        $html .= '<div class="cabinet-sa-url-variants__head">';
        $html .= count($others) === 1 ? 'также:' : ('также (' . count($others) . '):');
        if ($diff !== '') {
            $html .= ' <span class="cabinet-sa-url-variants__diff">' . e($diff) . '</span>';
        }
        $html .= '</div><ul class="cabinet-sa-url-variants__list">';
        foreach ($others as $v) {
            $html .= '<li><a href="' . e($v) . '" target="_blank" rel="noopener noreferrer">'
                . e($v) . '</a></li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    /**
     * @param  string[]  $variants
     */
    private static function describeUrlVariantDiff(array $variants): string
    {
        if (count($variants) < 2) {
            return '';
        }
        $bits = [];
        $schemes = [];
        $hosts = [];
        $slash = [];
        foreach ($variants as $v) {
            $p = parse_url($v);
            if (! is_array($p)) {
                continue;
            }
            $schemes[strtolower((string) ($p['scheme'] ?? ''))] = true;
            $hosts[strtolower((string) ($p['host'] ?? ''))] = true;
            $path = (string) ($p['path'] ?? '/');
            $slash[$path !== '/' && substr($path, -1) === '/' ? 'со /' : 'без /'] = true;
        }
        if (count($schemes) > 1) {
            $bits[] = 'http ↔ https';
        }
        if (count($hosts) > 1) {
            $www = false;
            $apex = false;
            foreach (array_keys($hosts) as $h) {
                if (strpos($h, 'www.') === 0) {
                    $www = true;
                } else {
                    $apex = true;
                }
            }
            $bits[] = ($www && $apex) ? 'www ↔ без www' : 'разный хост';
        }
        if (count($slash) > 1) {
            $bits[] = 'со слэшем ↔ без';
        }
        if ($bits === []) {
            $bits[] = 'разный путь/query';
        }

        return implode(', ', $bits);
    }

    public static function googleSearchUrl(string $query): string
    {
        return 'https://www.google.com/search?q=' . rawurlencode($query);
    }

    public static function metaLine(string $code, $meta, ?string $url = null): string
    {
        if (! is_array($meta) || ! $meta) {
            return '—';
        }

        switch ($code) {
            case 'duplicate_title':
            case 'duplicate_description':
                $parts = [];
                if (! empty($meta['group_size'])) {
                    $parts[] = 'в группе: ' . (int) $meta['group_size'];
                }
                if (! empty($meta['title'])) {
                    $parts[] = 'title: ' . self::clip($meta['title'], 80);
                }
                if (! empty($meta['description'])) {
                    $parts[] = 'desc: ' . self::clip($meta['description'], 80);
                }

                return $parts ? implode(' · ', $parts) : '—';

            case 'thin_content':
                return isset($meta['word_count'])
                    ? ('слов: ' . (int) $meta['word_count'] . ' (порог ' . (int) ($meta['threshold'] ?? 0) . ')')
                    : '—';

            case 'title_too_short':
            case 'title_too_long':
            case 'description_too_short':
            case 'description_too_long':
                $bits = [];
                if (isset($meta['length'])) {
                    $bits[] = 'длина: ' . (int) $meta['length'];
                }
                if (isset($meta['min'])) {
                    $bits[] = 'мин: ' . (int) $meta['min'];
                }
                if (isset($meta['max'])) {
                    $bits[] = 'макс: ' . (int) $meta['max'];
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'title_equals_h1':
                return ! empty($meta['h1']) ? ('H1: ' . self::clip($meta['h1'], 80)) : '—';

            case 'description_equals_h1':
                return ! empty($meta['h1']) ? ('H1: ' . self::clip($meta['h1'], 80)) : '—';

            case 'h1_equals_h2':
                $bits = [];
                if (! empty($meta['h1'])) {
                    $bits[] = 'H1: ' . self::clip($meta['h1'], 60);
                }
                if (! empty($meta['h2'])) {
                    $bits[] = 'H2: ' . self::clip($meta['h2'], 60);
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'heading_hierarchy':
                $bits = [];
                $n = (int) ($meta['issue_count'] ?? 0);
                if ($n > 0) {
                    $bits[] = 'проблем: ' . $n;
                }
                foreach (array_slice($meta['issues'] ?? [], 0, 2) as $issue) {
                    if (! is_array($issue)) {
                        continue;
                    }
                    $type = (string) ($issue['type'] ?? '');
                    if ($type === 'before_h1') {
                        $bits[] = 'до H1: H' . (int) ($issue['level'] ?? 0)
                            . (! empty($issue['text']) ? (' «' . self::clip($issue['text'], 40) . '»') : '');
                    } elseif ($type === 'skip') {
                        $bits[] = 'H' . (int) ($issue['from'] ?? 0) . '→H' . (int) ($issue['to'] ?? 0)
                            . (! empty($issue['text']) ? (' «' . self::clip($issue['text'], 40) . '»') : '');
                    }
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'too_many_strong':
                if (isset($meta['strong_count'])) {
                    $thr = isset($meta['threshold']) ? (' / порог ' . (int) $meta['threshold']) : '';

                    return 'strong/b: ' . (int) $meta['strong_count'] . $thr;
                }

                return '—';

            case 'duplicate_links':
                if (isset($meta['count'])) {
                    $sample = '';
                    if (! empty($meta['samples']) && is_array($meta['samples'])) {
                        $first = reset($meta['samples']);
                        if (is_string($first)) {
                            $sample = ' · ' . self::clip($first, 70);
                        } elseif (is_array($first) && ! empty($first['url'])) {
                            $sample = ' · ' . self::clip($first['url'], 70);
                        }
                    }

                    return 'дублей URL: ' . (int) $meta['count'] . $sample;
                }

                return '—';

            case 'external_links':
                return self::externalLinksPlain($meta);

            case 'meta_spam':
                $bits = [];
                if (! empty($meta['title']['word'])) {
                    $bits[] = 'title «' . self::clip($meta['title']['word'], 40) . '»×' . (int) ($meta['title']['count'] ?? 0);
                }
                if (! empty($meta['description']['word'])) {
                    $bits[] = 'desc «' . self::clip($meta['description']['word'], 40) . '»×' . (int) ($meta['description']['count'] ?? 0);
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'h1_spam':
                if (! empty($meta['word'])) {
                    return '«' . self::clip($meta['word'], 40) . '»×' . (int) ($meta['count'] ?? 0);
                }

                return '—';

            case 'text_nausea':
                $bits = [];
                if (isset($meta['nausea_classic'])) {
                    $bits[] = 'класс. ' . $meta['nausea_classic'] . '%';
                }
                if (isset($meta['nausea_academic'])) {
                    $bits[] = 'акад. ' . $meta['nausea_academic'] . '%';
                }
                if (! empty($meta['top_word'])) {
                    $bits[] = 'топ: «' . self::clip($meta['top_word'], 30) . '»×' . (int) ($meta['top_word_count'] ?? 0);
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'text_bigram_spam':
                if (! empty($meta['bigram'])) {
                    return '«' . self::clip($meta['bigram'], 50) . '»×' . (int) ($meta['count'] ?? 0)
                        . (isset($meta['density']) ? (' · ' . $meta['density'] . '%') : '');
                }

                return '—';

            case 'text_trigram_spam':
                if (! empty($meta['trigram'])) {
                    return '«' . self::clip($meta['trigram'], 60) . '»×' . (int) ($meta['count'] ?? 0)
                        . (isset($meta['density']) ? (' · ' . $meta['density'] . '%') : '');
                }

                return '—';

            case 'no_unique_images':
                return 'img: ' . (int) ($meta['img_count'] ?? 0) . ' · unique src: 0';

            case 'text_in_noindex':
                return isset($meta['noindex_text_len'])
                    ? ('символов в noindex: ' . (int) $meta['noindex_text_len'])
                    : '—';

            case 'images_without_alt':
                return isset($meta['img_without_alt'])
                    ? ('без alt: ' . (int) $meta['img_without_alt'] . ' / ' . (int) ($meta['img_count'] ?? 0))
                    : '—';

            case 'redirect':
            case 'redirect_chain_long':
            case 'redirect_loop':
                $chain = ! empty($meta['chain']) && is_array($meta['chain']) ? $meta['chain'] : [];
                $final = ! empty($meta['final']) ? (string) $meta['final'] : null;
                $start = $url ?: (! empty($meta['path'][0]) ? (string) $meta['path'][0] : '');
                if ($start === '' && ! empty($meta['path']) && is_array($meta['path'])) {
                    return SiteAuditRedirectChain::formatDetails(
                        (string) $meta['path'][0],
                        array_slice($meta['path'], 1),
                        $final,
                        64,
                        $code === 'redirect_loop'
                    );
                }
                if ($start !== '') {
                    return SiteAuditRedirectChain::formatDetails(
                        $start,
                        $chain,
                        $final,
                        64,
                        $code === 'redirect_loop'
                    );
                }
                // Старые finding без start URL в meta
                if ($chain) {
                    $line = implode(' → ', array_map(function ($u) {
                        return self::clip((string) $u, 64);
                    }, $chain));
                    if ($code === 'redirect_loop') {
                        $line .= ' · цикл';
                    }
                    $line .= ' · шагов: ' . count($chain);

                    return $line;
                }
                if ($final) {
                    return '→ ' . self::clip($final, 90);
                }

                return '—';

            case 'http_4xx':
            case 'http_5xx':
                $bits = [];
                if (isset($meta['status'])) {
                    $bits[] = 'код ' . (int) $meta['status'];
                }
                $refN = (int) ($meta['referrer_count'] ?? 0);
                if ($refN > 0) {
                    // URL страниц со ссылкой — в колонке «Страница со ссылкой», не дублируем здесь
                    // (длинный URL + nowrap ломал таблицу).
                    $bits[] = 'ссылаются: ' . $refN;
                } elseif (array_key_exists('referrer_count', $meta)) {
                    $bits[] = 'ссылок с страниц проверки нет';
                }
                if (! empty($meta['slash_hint']) || ! empty($meta['false_404_slash'])) {
                    // Не тащим в CSV кривой slash_url от мусорного href
                    if (! self::urlLooksBrokenHref((string) ($url ?? ''))
                        && ! self::urlLooksBrokenHref(trim((string) ($meta['slash_url'] ?? '')))) {
                        $slashUrl = trim((string) ($meta['slash_url'] ?? ''));
                        $bits[] = $slashUrl !== ''
                            ? ('в sitemap часто со слэшем: ' . self::clip($slashUrl, 60))
                            : 'возможен ложный 404: страница жива со слэшем в конце';
                    }
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'unreachable':
                $bits = [];
                if (! empty($meta['error'])) {
                    $bits[] = self::clip((string) $meta['error'], 40);
                }
                $refN = (int) ($meta['referrer_count'] ?? 0);
                if ($refN > 0) {
                    $bits[] = 'ссылаются: ' . $refN;
                } elseif (array_key_exists('referrer_count', $meta)) {
                    $bits[] = 'ссылок с страниц проверки нет';
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'page_too_large':
                $size = isset($meta['size_bytes']) ? self::formatBytes((int) $meta['size_bytes']) : null;
                $thr = isset($meta['threshold']) ? self::formatBytes((int) $meta['threshold']) : null;
                if ($size && $thr) {
                    return $size . ' (порог ' . $thr . ')';
                }

                return $size ?: '—';

            case 'canonical_foreign':
                return ! empty($meta['canonical']) ? self::clip($meta['canonical'], 100) : '—';

            case 'canonical_not_self':
                return ! empty($meta['canonical'])
                    ? ('→ ' . self::clip((string) $meta['canonical'], 100))
                    : 'canonical ≠ URL';

            case 'noindex':
                $bits = [];
                if (! empty($meta['robots'])) {
                    $bits[] = 'robots: ' . $meta['robots'];
                }
                if (! empty($meta['x_robots'])) {
                    $bits[] = 'X-Robots: ' . $meta['x_robots'];
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'robots_txt_error':
                $reason = $meta['reason'] ?? '';
                $map = [
                    'http_status' => 'HTTP ' . ($meta['status'] ?? ''),
                    'too_large' => 'файл слишком большой',
                    'empty' => 'пустой файл',
                    'bad_line' => 'битая строка' . (! empty($meta['line']) ? ' #' . $meta['line'] : ''),
                    'bad_sitemap' => 'битый Sitemap: ' . self::clip((string) ($meta['sitemap'] ?? ''), 60),
                    'fetch_failed' => 'не удалось скачать',
                ];

                return $map[$reason] ?? ($reason !== '' ? $reason : '—');

            case 'robots_txt_closed':
                return 'Disallow: / для User-agent: *';

            case 'robots_blocked':
                return 'закрыт правилом robots.txt';

            case 'sitemap_missing':
                return 'sitemap не найден';

            case 'sitemap_error':
                $reason = (string) ($meta['reason'] ?? '');
                $map = [
                    'empty' => 'пустой ответ',
                    'not_xml' => 'не XML',
                    'fetch_failed' => 'не удалось скачать',
                ];
                if (strpos($reason, 'http_') === 0) {
                    return 'HTTP ' . substr($reason, 5);
                }

                return $map[$reason] ?? ($reason !== '' ? $reason : '—');

            case 'not_in_sitemap':
                return 'нет в sitemap';

            case 'sitemap_not_crawled':
                $reason = (string) ($meta['reason'] ?? '');
                if ($reason === 'crawl_save_gap') {
                    return 'сбой сохранения страниц в проверке';
                }
                if ($reason === 'likely_robots_or_not_queued') {
                    return 'не в очереди (robots / фильтр)';
                }
                if ($reason === 'pages_limit' || isset($meta['pages_limit'])) {
                    return 'лимит проверки: ' . number_format((int) ($meta['pages_limit'] ?? 0), 0, '', ' ');
                }

                return 'не в проверке';

            case 'landing_not_in_sitemap':
                return 'посадочная · нет в sitemap';

            case 'landing_not_crawled':
                return isset($meta['pages_limit'])
                    ? ('посадочная · не в проверке · лимит ' . number_format((int) $meta['pages_limit'], 0, '', ' '))
                    : 'посадочная · не в проверке';

            case 'landing_url_changed':
                $q = trim((string) ($meta['query'] ?? ''));
                $old = self::clip((string) ($meta['old_url'] ?? ''), 60);
                $prefix = $q !== '' ? ('«' . self::clip($q, 40) . '» · ') : '';

                return $prefix . ($old !== '' ? ($old . ' → новый') : 'URL посадочной изменился');

            case 'pages_with_iframe':
                return isset($meta['iframe_count'])
                    ? ('frame/iframe: ' . (int) $meta['iframe_count'])
                    : '—';

            case 'mixed_content':
                $n = (int) ($meta['count'] ?? 0);
                $sample = '';
                if (! empty($meta['samples'][0])) {
                    $sample = ' · ' . self::clip((string) $meta['samples'][0], 70);
                }

                return $n ? ('http-ресурсов: ' . $n . $sample) : '—';

            case 'insecure_form':
                $n = (int) ($meta['count'] ?? 0);
                $sample = '';
                if (! empty($meta['samples'][0])) {
                    $sample = ' · ' . self::clip((string) $meta['samples'][0], 70);
                }

                return $n ? ('форм: ' . $n . $sample) : 'form action=http';

            case 'bad_doctype':
                if (($meta['reason'] ?? '') === 'missing') {
                    return 'DOCTYPE отсутствует';
                }

                return ! empty($meta['doctype'])
                    ? ('DOCTYPE: ' . self::clip((string) $meta['doctype'], 80))
                    : '—';

            case 'pages_with_canonical':
                return ! empty($meta['canonical']) ? self::clip((string) $meta['canonical'], 100) : '—';

            case 'similar_pages':
                $parts = [];
                if (! empty($meta['similar_url'])) {
                    $parts[] = '≈ ' . self::clip((string) $meta['similar_url'], 80);
                }
                if (isset($meta['hamming'])) {
                    $parts[] = 'hamming: ' . (int) $meta['hamming'];
                }

                return $parts ? implode(' · ', $parts) : '—';

            case 'duplicate_content':
                return isset($meta['group_size'])
                    ? ('в группе: ' . (int) $meta['group_size'])
                    : '—';

            case 'meta_nofollow':
                $bits = [];
                if (! empty($meta['robots'])) {
                    $bits[] = 'robots: ' . $meta['robots'];
                }
                if (! empty($meta['x_robots'])) {
                    $bits[] = 'X-Robots: ' . $meta['x_robots'];
                }

                return $bits ? implode(' · ', $bits) : 'nofollow';

            case 'links_nofollow':
                return self::nofollowLinksPlain($meta);

            case 'page_has_broken_external_links':
                return self::brokenExternalPagePlain($meta);

            case 'broken_external_link':
                return self::brokenExternalLinkPlain($meta);

            case 'external_assets':
                return self::externalAssetsPlain($meta);

            case 'soft_404':
                $bits = [];
                if (isset($meta['word_count'])) {
                    $bits[] = 'слов: ' . (int) $meta['word_count'];
                }
                if (! empty($meta['title'])) {
                    $bits[] = 'title: ' . self::clip((string) $meta['title'], 60);
                }

                return $bits ? implode(' · ', $bits) : 'soft 404';

            case 'orphan_pages':
                return 'нет входящих ссылок в проверке';

            case 'duplicate_url_variants':
                $n = (int) ($meta['count'] ?? count($meta['variants'] ?? []));
                $variants = isset($meta['variants']) && is_array($meta['variants']) ? $meta['variants'] : [];
                if ($variants) {
                    return 'вариантов: ' . $n . ' · ' . implode(' | ', array_map(function ($v) {
                        return (string) $v;
                    }, $variants));
                }

                return $n ? ('вариантов: ' . $n) : 'дубль URL';

            case 'www_both_available':
                $bits = [];
                if (! empty($meta['apex_final'])) {
                    $bits[] = self::clip((string) $meta['apex_final'], 40);
                }
                if (! empty($meta['www_final'])) {
                    $bits[] = self::clip((string) $meta['www_final'], 40);
                }

                return $bits ? implode(' ↔ ', $bits) : 'оба зеркала доступны';

            case 'http_https_both_available':
                return ! empty($meta['http_final'])
                    ? ('http без редиректа: ' . self::clip((string) $meta['http_final'], 60))
                    : 'http открыт параллельно с https';

            case 'page_has_broken_links':
                $n = (int) ($meta['count'] ?? 0);
                $sample = ! empty($meta['samples'][0]['url'])
                    ? ' · ' . self::clip((string) $meta['samples'][0]['url'], 60)
                    : '';

                return $n ? ('битых: ' . $n . $sample) : '—';

            case 'broken_internal_link':
                // URL источника — в колонке «Откуда», здесь только статус ответа.
                if (isset($meta['status'])) {
                    $bits = ['HTTP ' . (int) $meta['status']];
                    $refN = (int) ($meta['referrer_count'] ?? 0);
                    if ($refN > 1) {
                        $bits[] = 'ссылок: ' . $refN;
                    }

                    return implode(' · ', $bits);
                }

                return 'битая ссылка';

            case 'page_has_bad_links':
                $n = (int) ($meta['count'] ?? 0);
                $sample = is_array($meta['samples'][0] ?? null) ? $meta['samples'][0] : [];
                $bits = [];
                if ($n > 0) {
                    $bits[] = 'плохих: ' . $n;
                }
                $reason = self::badLinkReasonLabel((string) ($sample['reason'] ?? ''));
                if ($reason !== '') {
                    $bits[] = $reason;
                }
                $href = trim((string) ($sample['href'] ?? ''));
                if ($href !== '') {
                    $bits[] = self::clip($href, 50);
                }
                $text = trim((string) ($sample['text'] ?? ''));
                if ($text !== '') {
                    $bits[] = '«' . self::clip($text, 40) . '»';
                } elseif ($href === '' && ($sample['reason'] ?? '') === 'missing_href') {
                    $snip = trim((string) ($sample['snippet'] ?? ''));
                    if ($snip !== '') {
                        $bits[] = self::clip($snip, 55);
                    }
                }

                return $bits ? implode(' · ', $bits) : 'плохие ссылки';

            case 'html_critical_errors':
                $n = (int) ($meta['count'] ?? 0);
                $msg = ! empty($meta['samples'][0]['message'])
                    ? self::clip((string) $meta['samples'][0]['message'], 70)
                    : '';

                return $n ? ('ошибок: ' . $n . ($msg !== '' ? ' · ' . $msg : '')) : 'ошибки HTML';

            case 'lost_file':
                $asset = ! empty($meta['asset']) ? self::clip((string) $meta['asset'], 55) : '';
                $st = isset($meta['status']) ? ('HTTP ' . (int) $meta['status']) : 'unreachable';

                return $asset !== '' ? ($st . ' · ' . $asset) : $st;

            case 'adult_content':
                $hits = isset($meta['hits']) && is_array($meta['hits'])
                    ? implode(', ', array_slice($meta['hits'], 0, 4))
                    : '';

                return $hits !== '' ? ('hits: ' . $hits) : ('score ' . (int) ($meta['score'] ?? 0));

            case 'negative_content':
                $hits = isset($meta['hits']) && is_array($meta['hits'])
                    ? implode(', ', array_slice($meta['hits'], 0, 4))
                    : '';

                return $hits !== '' ? ('hits: ' . $hits) : ('score ' . (int) ($meta['score'] ?? 0));

            case 'word_repeat_in_sentence':
                $w = ! empty($meta['samples'][0]['word'])
                    ? (string) $meta['samples'][0]['word']
                    : '';
                $c = (int) ($meta['samples'][0]['count'] ?? $meta['count'] ?? 0);

                return $w !== '' ? ($w . ' ×' . $c) : ('повторов: ' . (int) ($meta['count'] ?? 0));

            case 'landing_plagiarism_suspect':
                $src = (string) ($meta['source'] ?? 'internal');
                $peer = ! empty($meta['peer_url']) ? self::clip((string) $meta['peer_url'], 45) : '';

                return $peer !== '' ? ($src . ' · ' . $peer) : $src;

            case 'landing_plagiarism_external':
                $u = isset($meta['uniqueness_pct']) ? ((float) $meta['uniqueness_pct'] . '%') : '';
                $top = ! empty($meta['sources'][0]['url'])
                    ? self::clip((string) $meta['sources'][0]['url'], 40)
                    : '';

                return trim($u . ($top !== '' ? (' · ' . $top) : ''));

            case 'landing_no_inbound_internal':
                return 'входящих внутренних: 0';

            case 'keyword_cannibalization':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 40) : '';
                $land = ! empty($meta['landing_url']) ? self::clip((string) $meta['landing_url'], 35) : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($land !== '' ? (' · посадочная: ' . $land) : '');

            case 'ad_cannibalization':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 36) : '';
                $hint = ! empty($meta['ad_hint']) ? (string) $meta['ad_hint'] : '';
                $land = ! empty($meta['landing_url']) ? self::clip((string) $meta['landing_url'], 30) : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($hint !== '' ? (' · ' . $hint) : '')
                    . ($land !== '' ? (' · SEO: ' . $land) : '');

            case 'serp_snippet_cannibalization':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 36) : '';
                $eng = ! empty($meta['engine']) ? (string) $meta['engine'] : '';
                $pos = isset($meta['position']) ? ('#' . (int) $meta['position']) : '';
                $n = (int) ($meta['own_count'] ?? 0);

                return trim(($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($eng !== '' ? (' · ' . $eng) : '')
                    . ($pos !== '' ? (' · ' . $pos) : '')
                    . ($n > 0 ? (' · своих в ТОП: ' . $n) : ''));

            case 'landing_query_mismatch':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 40) : '';
                $hits = isset($meta['hits_any'], $meta['token_count'])
                    ? ((int) $meta['hits_any'] . '/' . (int) $meta['token_count'] . ' токенов')
                    : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($hits !== '' ? (' · ' . $hits) : '');

            case 'commercial_missing_contacts':
                $miss = isset($meta['missing']) && is_array($meta['missing'])
                    ? implode(', ', $meta['missing'])
                    : '';

                return $miss !== '' ? ('нет: ' . $miss) : 'нет контактов';

            case 'commercial_missing_price':
                return 'нет цены';

            case 'commercial_missing_cta':
                return 'нет CTA';

            case 'commercial_missing_delivery':
                return 'нет доставки';

            case 'commercial_missing_payment':
                return 'нет оплаты';

            case 'commercial_missing_stock':
                return 'нет наличия';

            case 'commercial_missing_reviews':
                return 'нет отзывов';

            case 'broken_image':
                $n = (int) ($meta['count'] ?? 0);
                $img = ! empty($meta['samples'][0]['img'])
                    ? self::clip((string) $meta['samples'][0]['img'], 50)
                    : '';

                return $n ? ('битых img: ' . $n . ($img !== '' ? ' · ' . $img : '')) : 'битое изображение';

            case 'heavy_image':
                $n = (int) ($meta['count'] ?? 0);
                $sz = ! empty($meta['samples'][0]['size_bytes'])
                    ? round(((int) $meta['samples'][0]['size_bytes']) / 1024) . ' KB'
                    : '';

                return $n ? ('тяжёлых: ' . $n . ($sz !== '' ? ' · ' . $sz : '')) : 'тяжёлое изображение';

            case 'error_spike':
                $kind = (string) ($meta['kind'] ?? '');
                if ($kind === 'status_cluster') {
                    return 'код ' . ($meta['status'] ?? '?')
                        . ': ' . (int) ($meta['count'] ?? 0)
                        . ' из ' . (int) ($meta['error_total'] ?? 0)
                        . ' ошибок';
                }
                if ($kind === 'path_cluster') {
                    return ($meta['path_prefix'] ?? '/')
                        . ' · ' . (int) ($meta['count'] ?? 0)
                        . '/' . (int) ($meta['prefix_total'] ?? 0)
                        . ' ошибок ('
                        . (isset($meta['rate']) ? round(((float) $meta['rate']) * 100) . '%' : '?')
                        . ')';
                }
                if ($kind === 'crawl_delta') {
                    return 'было ' . (int) ($meta['prev_count'] ?? 0)
                        . ' → стало ' . (int) ($meta['count'] ?? 0)
                        . (isset($meta['ratio']) ? (' (×' . $meta['ratio'] . ')') : '');
                }

                return 'выброс ошибок';

            case 'psi_mobile':
            case 'psi_desktop':
                return \App\Services\SiteAudit\SiteAuditPsiMetrics::compactLine(is_array($meta) ? $meta : []);

            case 'deep_pages':
                return isset($meta['depth'])
                    ? ('глубина: ' . (int) $meta['depth'] . ' (порог ' . (int) ($meta['threshold'] ?? 0) . ')')
                    : 'глубокая страница';

            case 'site_availability':
                $bits = [];
                if (! empty($meta['root_bad'])) {
                    $bits[] = 'корень: HTTP ' . (isset($meta['root_status']) ? (int) $meta['root_status'] : '—');
                }
                if (isset($meta['fail_rate_pct'])) {
                    $bits[] = 'ошибок: ' . $meta['fail_rate_pct'] . '%';
                }
                if (isset($meta['unreachable']) || isset($meta['http_5xx'])) {
                    $bits[] = 'unreachable ' . (int) ($meta['unreachable'] ?? 0)
                        . ' / 5xx ' . (int) ($meta['http_5xx'] ?? 0);
                }

                return $bits ? implode(' · ', $bits) : 'проблемы доступности';

            case 'index_count_mismatch':
                if (($meta['kind'] ?? '') === 'missing_url') {
                    $bits = [];
                    $engine = (string) ($meta['engine'] ?? 'yandex');
                    $bits[] = $engine === 'yandex' ? 'Яндекс' : ($engine === 'google' ? 'Google' : $engine);
                    if (($meta['source'] ?? '') === 'webmaster') {
                        $bits[] = 'Вебмастер';
                    } elseif (($meta['source'] ?? '') === 'gsc') {
                        $bits[] = 'GSC';
                    }
                    $bits[] = 'нет в индексе';
                    if (! empty($meta['list_truncated'])) {
                        $bits[] = 'список ПС мог быть неполным';
                    }

                    return implode(' · ', $bits);
                }
                $engine = (string) ($meta['engine'] ?? '');
                $bits = [];
                if ($engine === 'yandex') {
                    $bits[] = 'Яндекс';
                } elseif ($engine === 'google') {
                    $bits[] = 'Google';
                } elseif ($engine !== '') {
                    $bits[] = $engine;
                }
                $source = (string) ($meta['source'] ?? '');
                if ($source === 'webmaster') {
                    $bits[] = 'Вебмастер';
                } elseif ($source === 'xml_site') {
                    $bits[] = 'устаревший источник';
                }
                if (! empty($meta['needs_webmaster']) && empty($meta['deep'])) {
                    $bits[] = 'нужен Вебмастер';
                }
                if (! empty($meta['deep']) && (($meta['mode'] ?? '') === 'site_list'
                    || ($meta['mode'] ?? '') === 'webmaster_list'
                    || isset($meta['serp_count']))) {
                    $matched = (int) ($meta['matched'] ?? 0);
                    $crawlN = (int) ($meta['crawl_count'] ?? $meta['pages_total'] ?? 0);
                    $serpN = (int) ($meta['serp_count'] ?? 0);
                    $bits[] = 'список ' . $serpN . ' URL';
                    $bits[] = 'совпало ' . $matched . '/' . max(1, $crawlN);
                    $miss = (int) ($meta['missing_in_index'] ?? 0);
                    $extra = (int) ($meta['extra_in_index'] ?? 0);
                    if ($miss > 0) {
                        $bits[] = 'нет в индексе: ' . $miss;
                    }
                    if ($extra > 0) {
                        $bits[] = 'лишние в индексе: ' . $extra;
                    }
                    if (! empty($meta['truncated'])) {
                        $bits[] = 'список обрезан';
                    }

                    return $bits ? implode(' · ', $bits) : 'сверка индекса';
                }
                if (! empty($meta['deep'])) {
                    $si = (int) ($meta['sample_indexed'] ?? 0);
                    $sm = (int) ($meta['sample_missing'] ?? 0);
                    $sample = (int) ($meta['sample'] ?? ($si + $sm));
                    $bits[] = 'выборка ' . $si . '/' . max(1, $sample) . ' в индексе';
                    if (isset($meta['estimate'])) {
                        $bits[] = 'оценка ~' . (int) $meta['estimate']
                            . (isset($meta['pages_total']) ? (' из ' . (int) $meta['pages_total']) : '');
                    }

                    return $bits ? implode(' · ', $bits) : 'выборка индекса';
                }
                $indexed = isset($meta['indexed']) ? (int) $meta['indexed'] : null;
                $pages = isset($meta['pages_total']) ? (int) $meta['pages_total'] : null;
                $ratio = isset($meta['ratio']) ? round((float) $meta['ratio'], 2) : null;
                if (! empty($meta['ok_count']) && $indexed !== null && $pages !== null) {
                    $bits[] = 'в поиске ' . $indexed . ' · проверка ' . $pages;

                    return $bits ? implode(' · ', $bits) : 'индекс ≈ проверка';
                }
                if (! empty($meta['capped'])) {
                    $bits[] = 'потолок site: ~' . ($indexed ?? 100);
                    if ($pages !== null) {
                        $bits[] = 'проверка ' . $pages;
                    }
                    $bits[] = 'нужна сверка списка';

                    return $bits ? implode(' · ', $bits) : 'оценка site: ограничена';
                }
                if ($indexed !== null && $pages !== null) {
                    $bits[] = 'индекс ' . $indexed . ' vs проверка ' . $pages;
                }
                if ($ratio !== null) {
                    $bits[] = '×' . $ratio;
                }

                return $bits ? implode(' · ', $bits) : 'расхождение индекса и проверки';

            case 'serp_snippets':
                $bits = [];
                if (! empty($meta['page_title'])) {
                    $bits[] = 'title: ' . self::clip((string) $meta['page_title'], 40);
                }
                foreach ((array) ($meta['engines'] ?? []) as $eng => $block) {
                    if (! is_array($block)) {
                        continue;
                    }
                    $label = $eng === 'yandex' ? 'Я' : ($eng === 'google' ? 'G' : (string) $eng);
                    if (! empty($block['error'])) {
                        $bits[] = $label . ': ошибка';
                        continue;
                    }
                    if (empty($block['indexed'])) {
                        $bits[] = $label . ': нет в индексе';
                        continue;
                    }
                    $snip = ! empty($block['snippet'])
                        ? self::clip((string) $block['snippet'], 60)
                        : (! empty($block['title']) ? self::clip((string) $block['title'], 40) : 'есть');
                    $bits[] = $label . ': ' . $snip;
                }

                return $bits ? implode(' · ', $bits) : 'сниппет ПС';

            case 'serp_title_mismatch':
                $bits = [];
                if (! empty($meta['engine'])) {
                    $eng = (string) $meta['engine'];
                    $bits[] = $eng === 'yandex' ? 'Яндекс' : ($eng === 'google' ? 'Google' : $eng);
                }
                if (! empty($meta['page_title'])) {
                    $bits[] = 'на сайте: ' . self::clip((string) $meta['page_title'], 90);
                }
                if (! empty($meta['serp_title'])) {
                    $bits[] = 'в выдаче: ' . self::clip((string) $meta['serp_title'], 90);
                }

                return $bits ? implode(' · ', $bits) : 'title ≠ выдача';

            case 'serp_not_indexed':
                return ! empty($meta['engine'])
                    ? ('нет в индексе: ' . (string) $meta['engine'])
                    : 'нет в индексе ПС';

            case 'index_url_missing':
                // устаревший code; новые сверки пишут в index_count_mismatch
                $bits = [];
                $engine = (string) ($meta['engine'] ?? 'yandex');
                $bits[] = $engine === 'yandex' ? 'Яндекс' : ($engine === 'google' ? 'Google' : $engine);
                if (($meta['source'] ?? '') === 'webmaster') {
                    $bits[] = 'Вебмастер';
                }
                $bits[] = 'нет в индексе';
                if (! empty($meta['list_truncated'])) {
                    $bits[] = 'список ПС мог быть неполным';
                }

                return implode(' · ', $bits);

            case 'serp_snippet_source':
                return self::serpSnippetSourcePlain($meta);

            case 'probable_affiliate':
                $n = (int) ($meta['count'] ?? 0);
                $net = ! empty($meta['samples'][0]['network'])
                    ? (string) $meta['samples'][0]['network']
                    : '';

                return $n
                    ? ('affiliate: ' . $n . ($net !== '' ? ' · ' . $net : ''))
                    : 'affiliate-ссылки';

            case 'missing_permissions_policy':
                return 'нет Permissions-Policy';

            case 'missing_coop':
                return 'нет COOP';

            case 'missing_coep':
                return 'нет COEP';

            case 'missing_corp':
                return 'нет CORP';

            case 'multiple_canonical':
                return isset($meta['count'])
                    ? ('canonical: ' . (int) $meta['count'])
                    : 'несколько canonical';

            case 'no_outbound_internal':
                return 'нет исходящих внутренних ссылок';

            case 'risky_query_params':
                $bits = [];
                if (! empty($meta['keys']) && is_array($meta['keys'])) {
                    $bits[] = 'keys: ' . implode(', ', array_slice($meta['keys'], 0, 5));
                }
                if (! empty($meta['many_keys'])) {
                    $bits[] = 'параметров: ' . (int) ($meta['key_count'] ?? 0);
                }
                if (! empty($meta['long_query'])) {
                    $bits[] = 'query ' . (int) ($meta['query_len'] ?? 0) . ' симв.';
                }

                return $bits ? implode(' · ', $bits) : 'рисковые параметры';

            case 'pagination_param':
                $bits = [];
                if (! empty($meta['pagination_keys']) && is_array($meta['pagination_keys'])) {
                    $bits[] = implode(', ', $meta['pagination_keys']);
                }
                if (! empty($meta['facet_keys']) && is_array($meta['facet_keys'])) {
                    $bits[] = 'facet: ' . implode(', ', $meta['facet_keys']);
                }
                if (! empty($meta['path_pagination'])) {
                    $bits[] = 'path pagination';
                }

                return $bits ? implode(' · ', $bits) : 'пагинация/фильтр';

            case 'empty_title':
                return 'title пустой';

            case 'empty_description':
                return 'description пустой';

            case 'canonical_empty':
                return 'canonical отсутствует';

            case 'missing_hsts':
                return 'нет Strict-Transport-Security';

            case 'missing_x_frame_options':
                return 'нет X-Frame-Options';

            case 'missing_x_content_type_options':
                return 'нет X-Content-Type-Options';

            case 'missing_csp':
                return 'нет Content-Security-Policy';

            case 'missing_referrer_policy':
                return 'нет Referrer-Policy';

            case 'missing_charset':
                return 'charset не объявлен';

            case 'multiple_h1':
                return isset($meta['count']) ? ('H1: ' . (int) $meta['count']) : '—';

            default:
                // discovered_* пишется в meta для колонки «Откуда», в «Детали» не показываем сырой JSON.
                $clean = $meta;
                foreach ([
                    'discovered_via',
                    'discovered_from',
                    'origin_label',
                    'origin_hint',
                    'from_sitemap',
                    'sitemap_href',
                    'referrers',
                    'referrer_count',
                    'slash_hint',
                    'slash_url',
                    'false_404_slash',
                ] as $drop) {
                    unset($clean[$drop]);
                }
                if ($clean === []) {
                    return '—';
                }

                return self::clip(json_encode($clean, JSON_UNESCAPED_UNICODE), 120);
        }
    }

    /**
     * Список nofollow: анкор → URL.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function nofollowLinksDetailsHtml(array $meta): ?string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $n = (int) ($meta['count'] ?? count($samples));
        if ($samples === [] && $n <= 0) {
            return $n > 0 ? ('nofollow-ссылок: ' . $n) : null;
        }

        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>nofollow: ' . $n . '</strong></div>';
        }
        $parts[] = '<ul class="mb-0 ps-3 cabinet-sa-nofollow-list">';
        foreach (array_slice($samples, 0, 15) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $href = trim((string) ($sample['href'] ?? $sample['url'] ?? ''));
            $text = trim((string) ($sample['text'] ?? ''));
            $scope = (string) ($sample['scope'] ?? '');
            $scopeBit = $scope === 'internal'
                ? '<span class="badge badge-light border">внутр.</span> '
                : ($scope === 'external' ? '<span class="badge badge-light border">внешн.</span> ' : '');
            $anchorBit = $text !== ''
                ? '<span class="cabinet-sa-nofollow-list__anchor">«' . e(self::clip($text, 70)) . '»</span>'
                : '<span class="text-muted">без анкора</span>';
            $urlBit = $href !== ''
                ? '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($href) . '</div>'
                : '';
            $parts[] = '<li class="cabinet-sa-nofollow-list__item">'
                . $scopeBit . $anchorBit
                . ($urlBit !== '' ? ' → ' . $urlBit : '')
                . '</li>';
        }
        $parts[] = '</ul>';
        if ($n > count($samples) && $samples !== []) {
            $parts[] = '<div class="text-secondary small mt-1">показаны '
                . count($samples) . ' из ' . $n . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function nofollowLinksPlain(array $meta): string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $n = (int) ($meta['count'] ?? count($samples));
        if ($n <= 0 && $samples === []) {
            return '—';
        }
        $bits = ['nofollow: ' . max($n, count($samples))];
        foreach (array_slice($samples, 0, 2) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $text = trim((string) ($sample['text'] ?? ''));
            $href = trim((string) ($sample['href'] ?? $sample['url'] ?? ''));
            if ($text !== '' && $href !== '') {
                $bits[] = '«' . self::clip($text, 40) . '» → ' . self::clip($href, 50);
            } elseif ($href !== '') {
                $bits[] = self::clip($href, 60);
            }
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalPageDetailsHtml(array $meta): ?string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $n = (int) ($meta['count'] ?? count($samples));
        if ($samples === [] && $n <= 0) {
            return null;
        }
        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>битых внешних: ' . $n . '</strong></div>';
        }
        $parts[] = '<ul class="mb-0 ps-3">';
        foreach (array_slice($samples, 0, 12) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $href = trim((string) ($sample['url'] ?? $sample['href'] ?? ''));
            $text = trim((string) ($sample['text'] ?? ''));
            $status = $sample['status'] ?? null;
            $statusBit = $status !== null && $status !== ''
                ? '<span class="badge badge-danger">' . e((string) $status) . '</span> '
                : '<span class="badge badge-secondary">ошибка</span> ';
            $anchorBit = $text !== ''
                ? '«' . e(self::clip($text, 50)) . '» → '
                : '';
            $parts[] = '<li>' . $statusBit . $anchorBit
                . ($href !== '' ? self::urlLinkHtml($href) : '—')
                . '</li>';
        }
        $parts[] = '</ul>';

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalPagePlain(array $meta): string
    {
        $n = (int) ($meta['count'] ?? 0);
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        if ($n <= 0 && $samples === []) {
            return '—';
        }
        $bits = ['битых внешних: ' . max($n, count($samples))];
        if ($samples) {
            $first = $samples[0];
            if (is_array($first)) {
                $u = (string) ($first['url'] ?? '');
                if ($u !== '') {
                    $bits[] = self::clip($u, 70);
                }
            }
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalLinkDetailsHtml(array $meta): ?string
    {
        return self::brokenInternalLinkDetailsHtml($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalLinkPlain(array $meta): string
    {
        $status = $meta['status'] ?? null;
        $refs = (int) ($meta['referrer_count'] ?? (is_array($meta['referrers'] ?? null) ? count($meta['referrers']) : 0));
        $bits = [];
        if ($status !== null && $status !== '') {
            $bits[] = 'HTTP ' . $status;
        } elseif (! empty($meta['error'])) {
            $bits[] = 'недоступен';
        }
        if ($refs > 0) {
            $bits[] = 'на ' . $refs . ' стр.';
        }

        return $bits !== [] ? implode(' · ', $bits) : 'битая внешняя ссылка';
    }

    /**
     * Список плохих ссылок на странице: href / текст / фрагмент тега.
     *
     * @param array<string,mixed> $meta
     */
    private static function badLinksDetailsHtml(array $meta): ?string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        if ($samples === []) {
            return null;
        }

        $n = (int) ($meta['count'] ?? count($samples));
        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>плохих: ' . $n . '</strong></div>';
        }
        $parts[] = '<ul class="mb-0 ps-3">';
        foreach (array_slice($samples, 0, 10) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $reason = self::badLinkReasonLabel((string) ($sample['reason'] ?? ''));
            $href = trim((string) ($sample['href'] ?? ''));
            $text = trim((string) ($sample['text'] ?? ''));
            $snippet = trim((string) ($sample['snippet'] ?? ''));

            $line = [];
            if ($reason !== '') {
                $line[] = e($reason);
            }
            if ($href !== '') {
                $line[] = 'href=' . self::urlLinkHtml($href);
            }
            if ($text !== '') {
                $line[] = 'текст «' . e(self::clip($text, 60)) . '»';
            }
            if ($snippet !== '' && ($href === '' || $text === '')) {
                $line[] = '<code class="small">' . e(self::clip($snippet, 100)) . '</code>';
            }
            if ($line === []) {
                $line[] = 'плохая ссылка';
            }
            $parts[] = '<li>' . implode(' · ', $line) . '</li>';
        }
        $parts[] = '</ul>';

        return implode('', $parts);
    }

    /**
     * Откуда ПС, похоже, взяла заголовок/текст в выдаче (эвристика).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function serpSnippetSourcePlain(array $meta): string
    {
        $engine = self::serpEngineLabelRu((string) ($meta['engine'] ?? ''));
        $titleSrc = self::serpFieldSourceLabelRu((string) ($meta['title_source'] ?? ''));
        $snipSrc = self::serpFieldSourceLabelRu((string) ($meta['snippet_source'] ?? ''));
        $parts = [];
        if ($engine !== '') {
            $parts[] = $engine;
        }
        if ($titleSrc !== '') {
            $parts[] = 'заголовок в выдаче ≈ ' . $titleSrc;
        }
        if ($snipSrc !== '') {
            $parts[] = 'текст сниппета ≈ ' . $snipSrc;
        }

        return $parts !== [] ? implode('; ', $parts) : 'источник сниппета';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function serpSnippetSourceHtml(array $meta): ?string
    {
        $engine = self::serpEngineLabelRu((string) ($meta['engine'] ?? ''));
        $titleKey = (string) ($meta['title_source'] ?? '');
        $snipKey = (string) ($meta['snippet_source'] ?? '');
        if ($engine === '' && $titleKey === '' && $snipKey === '') {
            return null;
        }

        $titleLine = self::serpSnippetSourceExplain('Заголовок', $titleKey);
        $snipLine = self::serpSnippetSourceExplain('Описание (сниппет)', $snipKey);

        $html = '<div class="cabinet-sa-serp-src">';
        if ($engine !== '') {
            $html .= '<div class="cabinet-sa-serp-src__engine">' . e($engine) . '</div>';
        }
        if ($titleLine !== '') {
            $html .= '<div class="cabinet-sa-serp-src__row">' . e($titleLine) . '</div>';
        }
        if ($snipLine !== '') {
            $html .= '<div class="cabinet-sa-serp-src__row">' . e($snipLine) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function serpSnippetSourceExplain(string $what, string $sourceKey): string
    {
        $sourceKey = strtolower(trim($sourceKey));
        if ($sourceKey === '') {
            return '';
        }
        if ($sourceKey === 'title') {
            return $what . ' в выдаче совпал с тегом TITLE на сайте';
        }
        if ($sourceKey === 'h1') {
            return $what . ' в выдаче совпал с H1 на сайте (не с TITLE)';
        }
        if ($sourceKey === 'description') {
            return $what . ' в выдаче совпал с meta description';
        }
        if ($sourceKey === 'unknown') {
            return $what . ' в выдаче не совпал с TITLE / H1 / description — скорее из текста страницы или переписан ПС';
        }

        return $what . ' ≈ ' . $sourceKey;
    }

    private static function serpEngineLabelRu(string $engine): string
    {
        $e = strtolower(trim($engine));
        if ($e === 'yandex') {
            return 'Яндекс';
        }
        if ($e === 'google') {
            return 'Google';
        }

        return $engine;
    }

    private static function serpFieldSourceLabelRu(string $source): string
    {
        $s = strtolower(trim($source));
        if ($s === 'title') {
            return 'тег TITLE';
        }
        if ($s === 'h1') {
            return 'H1';
        }
        if ($s === 'description') {
            return 'meta description';
        }
        if ($s === 'unknown') {
            return 'не TITLE/H1/description (текст страницы или перепис ПС)';
        }

        return $source;
    }

    /**
     * Сравнение title по всем ПС: совпала / расхождение видно сразу.
     *
     * @param array<string,mixed> $meta
     */
    private static function serpTitleMismatchHtml(array $meta): ?string
    {
        $pageTitle = trim((string) ($meta['page_title'] ?? ''));
        $engines = isset($meta['engines']) && is_array($meta['engines']) ? $meta['engines'] : null;

        // Старые находки без engines — одна ПС как раньше.
        if ($engines === null || $engines === []) {
            $serpTitle = trim((string) ($meta['serp_title'] ?? ''));
            if ($pageTitle === '' && $serpTitle === '') {
                return null;
            }
            $engine = (string) ($meta['engine'] ?? '');
            $engines = [
                $engine !== '' ? $engine : 'ps' => [
                    'indexed' => true,
                    'title' => $serpTitle !== '' ? $serpTitle : null,
                    'snippet' => $meta['snippet'] ?? null,
                    'title_mismatch' => true,
                    'title_match' => false,
                ],
            ];
        }

        $order = ['yandex', 'google'];
        $keys = array_values(array_unique(array_merge($order, array_keys($engines))));

        $html = '<div class="cabinet-sa-serp-diff">';
        if ($pageTitle !== '') {
            $html .= '<div class="cabinet-sa-serp-diff__page">'
                . '<div class="cabinet-sa-serp-diff__label">TITLE на сайте</div>'
                . '<div class="cabinet-sa-serp-diff__text">' . e($pageTitle) . '</div>'
                . '</div>';
        }

        $html .= '<div class="cabinet-sa-serp-diff__engines">';
        foreach ($keys as $engine) {
            if (! isset($engines[$engine]) || ! is_array($engines[$engine])) {
                continue;
            }
            $block = $engines[$engine];
            $engineLabel = $engine === 'yandex' ? 'Яндекс'
                : ($engine === 'google' ? 'Google' : $engine);
            $serpTitle = trim((string) ($block['title'] ?? ''));
            $snippet = trim((string) ($block['snippet'] ?? ''));
            $mismatch = ! empty($block['title_mismatch']);
            $match = ! empty($block['title_match']);
            $indexed = ! empty($block['indexed']);
            $error = trim((string) ($block['error'] ?? ''));

            $statusClass = 'cabinet-sa-serp-diff__engine-card';
            $statusBadge = '';
            if ($error !== '') {
                $statusClass .= ' is-error';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-error">ошибка</span>';
            } elseif (! $indexed) {
                $statusClass .= ' is-miss';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-miss">нет в выдаче</span>';
            } elseif ($match) {
                $statusClass .= ' is-ok';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-ok">совпал</span>';
            } elseif ($mismatch) {
                $statusClass .= ' is-bad';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-bad">≠ TITLE</span>';
            }

            $html .= '<div class="' . $statusClass . '">';
            $html .= '<div class="cabinet-sa-serp-diff__engine-head">'
                . '<span class="cabinet-sa-serp-diff__engine">' . e($engineLabel) . '</span>'
                . $statusBadge
                . '</div>';
            if ($serpTitle !== '') {
                $html .= '<div class="cabinet-sa-serp-diff__label">В выдаче</div>'
                    . '<div class="cabinet-sa-serp-diff__text">' . e($serpTitle) . '</div>';
            } elseif ($error !== '') {
                $html .= '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                    . e(self::clip($error, 120)) . '</div>';
            } elseif (! $indexed) {
                $html .= '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">не найден</div>';
            }
            if ($snippet !== '') {
                $html .= '<div class="cabinet-sa-serp-diff__snippet-inline">'
                    . '<div class="cabinet-sa-serp-diff__label">Сниппет</div>'
                    . '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                    . e($snippet) . '</div></div>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private static function badLinkReasonLabel(string $reason): string
    {
        $map = [
            'missing_href' => 'нет href',
            'empty_or_hash' => 'пустой href / #',
            'javascript' => 'javascript:',
            'whitespace' => 'пробел в href',
            'quotes' => 'кавычки в href',
            'nested_url' => 'два URL в одном href',
        ];

        return $map[$reason] ?? ($reason !== '' ? $reason : '');
    }

    private static function lostFileDetailsHtml(array $meta): string
    {
        $asset = trim((string) ($meta['asset'] ?? ''));
        $st = isset($meta['status']) ? ('HTTP ' . (int) $meta['status']) : 'unreachable';
        $pill = self::httpStatusPillHtml(isset($meta['status']) ? (int) $meta['status'] : null, $st);
        if ($asset === '') {
            return $pill;
        }

        return '<div class="cabinet-sa-details-stack">'
            . $pill
            . '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($asset) . '</div>'
            . '</div>';
    }

    /**
     * Глубина клика + свёрнутый путь от главной по out_links.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function deepPagesDetailsHtml(array $meta): string
    {
        $depth = isset($meta['depth']) ? (int) $meta['depth'] : null;
        $threshold = isset($meta['threshold']) ? (int) $meta['threshold'] : null;
        $line = $depth !== null
            ? ('глубина: ' . $depth . ($threshold !== null ? (' (порог ' . $threshold . ')') : ''))
            : 'глубокая страница';

        $path = [];
        if (! empty($meta['path']) && is_array($meta['path'])) {
            foreach ($meta['path'] as $u) {
                $u = trim((string) $u);
                if ($u !== '') {
                    $path[] = $u;
                }
            }
        }

        $html = '<div class="cabinet-sa-depth">'
            . '<div class="cabinet-sa-depth__line">' . e($line) . '</div>';

        if ($path !== []) {
            $steps = count($path) > 0 ? (count($path) - 1) : 0;
            $html .= '<details class="cabinet-sa-depth-path">'
                . '<summary class="cabinet-sa-depth-path__sum">Показать путь'
                . ($steps > 0 ? (' · ' . $steps . ' ' . self::ruClicksWord($steps)) : '')
                . '</summary>'
                . '<ol class="cabinet-sa-depth-path__list">';
            foreach ($path as $i => $u) {
                $label = self::depthPathLabel($u, $i === 0);
                $html .= '<li class="cabinet-sa-depth-path__item">'
                    . '<a href="' . e($u) . '" target="_blank" rel="noopener" title="' . e($u) . '">'
                    . e($label)
                    . '</a></li>';
            }
            $html .= '</ol></details>';
        }

        $html .= '</div>';

        return $html;
    }

    private static function ruClicksWord(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return 'кликов';
        }
        if ($n1 === 1) {
            return 'клик';
        }
        if ($n1 >= 2 && $n1 <= 4) {
            return 'клика';
        }

        return 'кликов';
    }

    private static function depthPathLabel(string $url, bool $isRoot): string
    {
        if ($isRoot) {
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
            if ($path === '/' || $path === '') {
                return $host !== '' ? ($host . '/') : '/';
            }
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        if ($path === '') {
            $path = '/';
        }
        if (mb_strlen($path) > 64) {
            return self::clip($path, 64);
        }

        return $path;
    }

    /**
     * Исходящие ссылки на чужие домены: хост · полный URL.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function externalLinksDetailsHtml(array $meta): ?string
    {
        $samples = self::normalizeUrlSamples($meta);
        $n = (int) ($meta['count'] ?? count($samples));
        if ($samples === [] && $n <= 0) {
            return null;
        }

        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>внешних ссылок: ' . $n . '</strong></div>';
        }
        if ($samples === []) {
            return $parts ? implode('', $parts) : null;
        }

        $parts[] = '<ul class="mb-0 ps-3 cabinet-sa-ext-assets">';
        foreach (array_slice($samples, 0, 15) as $item) {
            $host = $item['host'] !== '' ? e($item['host']) : '—';
            $parts[] = '<li class="cabinet-sa-ext-assets__item">'
                . '<span class="text-secondary">→ ' . $host . '</span>'
                . '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($item['url']) . '</div>'
                . '</li>';
        }
        $parts[] = '</ul>';
        if ($n > count($samples)) {
            $parts[] = '<div class="text-secondary small mt-1">показаны '
                . count($samples) . ' из ' . $n . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function externalLinksPlain(array $meta): string
    {
        $samples = self::normalizeUrlSamples($meta);
        $n = (int) ($meta['count'] ?? count($samples));
        if ($n <= 0 && $samples === []) {
            return '—';
        }

        $bits = ['внешних: ' . max($n, count($samples))];
        $hosts = [];
        foreach ($samples as $item) {
            if ($item['host'] !== '' && ! isset($hosts[$item['host']])) {
                $hosts[$item['host']] = true;
            }
        }
        if ($hosts) {
            $bits[] = 'хосты: ' . implode(', ', array_slice(array_keys($hosts), 0, 5));
        }
        if ($samples) {
            $bits[] = self::clip($samples[0]['url'], 80);
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{url:string,host:string}>
     */
    private static function normalizeUrlSamples(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $sample) {
            $url = '';
            if (is_string($sample)) {
                $url = trim($sample);
            } elseif (is_array($sample)) {
                $url = trim((string) ($sample['url'] ?? $sample['href'] ?? $sample['src'] ?? ''));
            }
            if ($url === '') {
                continue;
            }
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            $out[] = ['url' => $url, 'host' => $host];
        }

        return $out;
    }

    /**
     * Список внешних script/css/img: тип · хост · полный URL.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function externalAssetsDetailsHtml(array $meta): ?string
    {
        $items = self::normalizeExternalAssetItems($meta);
        $n = (int) ($meta['count'] ?? count($items));
        if ($items === [] && $n <= 0) {
            return null;
        }

        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>внешних файлов: ' . $n . '</strong></div>';
        }
        if ($items === []) {
            return $parts ? implode('', $parts) : null;
        }

        $parts[] = '<ul class="mb-0 ps-3 cabinet-sa-ext-assets">';
        foreach (array_slice($items, 0, 15) as $item) {
            $kindLabel = self::externalAssetKindLabel($item['kind']);
            $host = $item['host'] !== '' ? e($item['host']) : '—';
            $parts[] = '<li class="cabinet-sa-ext-assets__item">'
                . '<span class="cabinet-sa-ext-assets__kind">' . e($kindLabel) . '</span>'
                . ' <span class="text-secondary">с ' . $host . '</span>'
                . '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($item['url']) . '</div>'
                . '</li>';
        }
        $parts[] = '</ul>';
        if ($n > count($items)) {
            $parts[] = '<div class="text-secondary small mt-1">показаны '
                . count($items) . ' из ' . $n . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function externalAssetsPlain(array $meta): string
    {
        $items = self::normalizeExternalAssetItems($meta);
        $n = (int) ($meta['count'] ?? count($items));
        if ($n <= 0 && $items === []) {
            return '—';
        }

        $bits = ['внешних: ' . max($n, count($items))];
        $hosts = [];
        foreach ($items as $item) {
            if ($item['host'] !== '' && ! isset($hosts[$item['host']])) {
                $hosts[$item['host']] = true;
            }
        }
        if ($hosts) {
            $bits[] = 'хосты: ' . implode(', ', array_slice(array_keys($hosts), 0, 5));
        }
        if ($items) {
            $first = $items[0];
            $bits[] = self::externalAssetKindLabel($first['kind']) . ' ' . self::clip($first['url'], 80);
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{url:string,kind:string,host:string}>
     */
    private static function normalizeExternalAssetItems(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $sample) {
            $url = '';
            $kind = 'file';
            if (is_string($sample)) {
                $url = trim($sample);
                $kind = self::guessExternalAssetKind($url);
            } elseif (is_array($sample)) {
                $url = trim((string) ($sample['url'] ?? $sample['src'] ?? $sample['href'] ?? ''));
                $kind = trim((string) ($sample['kind'] ?? $sample['type'] ?? ''));
                if ($kind === '') {
                    $kind = self::guessExternalAssetKind($url);
                }
            }
            if ($url === '') {
                continue;
            }
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            $out[] = ['url' => $url, 'kind' => $kind, 'host' => $host];
        }

        return $out;
    }

    private static function guessExternalAssetKind(string $url): string
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: $url));
        if (preg_match('/\.(js)(\?|$)/', $path)) {
            return 'script';
        }
        if (preg_match('/\.(css)(\?|$)/', $path)) {
            return 'css';
        }
        if (preg_match('/\.(png|jpe?g|gif|webp|svg|ico|avif)(\?|$)/', $path)) {
            return 'img';
        }

        return 'file';
    }

    private static function externalAssetKindLabel(string $kind): string
    {
        switch ($kind) {
            case 'script':
                return 'JS';
            case 'css':
                return 'CSS';
            case 'img':
                return 'картинка';
            default:
                return 'файл';
        }
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function brokenInternalLinkDetailsHtml(array $meta): string
    {
        $status = isset($meta['status']) ? (int) $meta['status'] : 0;
        $parts = [];
        if ($status > 0) {
            $parts[] = self::httpStatusPillHtml($status, 'HTTP ' . $status);
        } else {
            $parts[] = '<span class="cabinet-sa-status-pill">битая ссылка</span>';
        }
        $refN = (int) ($meta['referrer_count'] ?? 0);
        if ($refN > 1) {
            $parts[] = '<span class="text-secondary small">на ' . $refN . ' стр.</span>';
        }

        return '<div class="cabinet-sa-details-stack">' . implode(' ', $parts) . '</div>';
    }

    /**
     * Структурированные детали для http_4xx / http_5xx / unreachable
     * (не одна каша «код · ссылаются · слэш»).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function httpStatusDetailsHtml(string $code, array $meta, ?string $url): string
    {
        $status = isset($meta['status']) ? (int) $meta['status'] : 0;
        $rows = [];

        if ($code === 'unreachable') {
            $err = trim((string) ($meta['error'] ?? ''));
            $rows[] = '<div class="cabinet-sa-http-details__row">'
                . '<span class="cabinet-sa-http-details__k">Статус</span>'
                . '<span class="cabinet-sa-http-details__v">'
                . '<span class="cabinet-sa-status-pill">недоступна</span>'
                . ($err !== '' ? ' <span class="text-secondary">' . e(self::clip($err, 80)) . '</span>' : '')
                . '</span></div>';
        } elseif ($status > 0) {
            $rows[] = '<div class="cabinet-sa-http-details__row">'
                . '<span class="cabinet-sa-http-details__k">Код</span>'
                . '<span class="cabinet-sa-http-details__v">'
                . self::httpStatusPillHtml($status, (string) $status)
                . '</span></div>';
        }

        $refN = (int) ($meta['referrer_count'] ?? 0);
        if ($refN > 1) {
            $rows[] = '<div class="cabinet-sa-http-details__row">'
                . '<span class="cabinet-sa-http-details__k">Ссылаются</span>'
                . '<span class="cabinet-sa-http-details__v">'
                . number_format($refN, 0, '', ' ') . ' стр. '
                . '<span class="text-secondary">(колонка справа)</span>'
                . '</span></div>';
        } elseif (array_key_exists('referrer_count', $meta) && $refN === 0) {
            $rows[] = '<div class="cabinet-sa-http-details__row">'
                . '<span class="cabinet-sa-http-details__k">Ссылаются</span>'
                . '<span class="cabinet-sa-http-details__v text-secondary">нет с страниц проверки</span>'
                . '</div>';
        }

        $slashUrl = trim((string) ($meta['slash_url'] ?? ''));
        $showSlash = (! empty($meta['slash_hint']) || ! empty($meta['false_404_slash']))
            && ! self::urlLooksBrokenHref((string) $url)
            && ! self::urlLooksBrokenHref($slashUrl);
        if ($showSlash) {
            $rows[] = '<div class="cabinet-sa-http-details__row">'
                . '<span class="cabinet-sa-http-details__k">Подсказка</span>'
                . '<span class="cabinet-sa-http-details__v">'
                . ($slashUrl !== ''
                    ? 'Часто жива со слэшем: ' . self::urlLinkHtml($slashUrl)
                    : 'Возможен ложный 404: страница жива со слэшем в конце')
                . '</span></div>';
        }

        if ($rows === []) {
            return '<div class="cabinet-sa-details-stack"><span class="text-secondary">—</span></div>';
        }

        return '<div class="cabinet-sa-http-details">' . implode('', $rows) . '</div>';
    }

    /** URL с кавычками/бэкслешами — почти всегда мусор из HTML, не нормальный адрес. */
    public static function urlLooksBrokenHref(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (strpbrk($url, "\"'\\<>") !== false) {
            return true;
        }
        // Два «https://» в одном URL — склейка href + пример из текста.
        if (preg_match('#https?://.+https?://#i', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Короткий читаемый ярлык для битого URL в первой колонке.
     *
     * @return array{display:string,warn:?string}
     */
    public static function brokenUrlDisplay(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['display' => '—', 'warn' => null];
        }

        // Полный URL всегда видимый — без «…». Метка «кривой href» отдельно.
        return [
            'display' => $url,
            'warn' => self::urlLooksBrokenHref($url) ? 'кривой href' : null,
        ];
    }

    private static function httpStatusPillHtml(?int $status, string $fallbackLabel): string
    {
        $cls = 'cabinet-sa-status-pill';
        if ($status !== null && $status >= 500) {
            $cls .= ' cabinet-sa-status-pill--5xx';
        } elseif ($status !== null && $status >= 400) {
            $cls .= ' cabinet-sa-status-pill--4xx';
        }

        return '<span class="' . $cls . '">' . e($fallbackLabel) . '</span>';
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function redirectDetailsHtml(array $meta, string $code): string
    {
        $plain = self::metaLine($code, $meta, null);

        return self::linkifyUrlsInText($plain !== '' ? $plain : '—');
    }

    /**
     * Полный URL как ссылка (без обрезки).
     */
    public static function urlLinkHtml(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $url)) {
            return '<span class="cabinet-sa-url-break">' . e($url) . '</span>';
        }

        return '<a class="cabinet-sa-url-break" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
            . e($url) . '</a>';
    }

    /**
     * Экранирует текст и делает http(s) URL кликабельными (целиком, без …).
     */
    public static function linkifyUrlsInText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $parts = preg_split('#(https?://[^\s←→·]+)#iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || $parts === []) {
            return e($text);
        }

        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            // хвостовая пунктуация у URL
            if (preg_match('#^(https?://\S+?)([.,;:)\]]+)?$#iu', $part, $m)) {
                $html .= self::urlLinkHtml($m[1]);
                if (! empty($m[2])) {
                    $html .= e($m[2]);
                }
            } else {
                $html .= e($part);
            }
        }

        return '<span class="cabinet-sa-details-block">' . $html . '</span>';
    }

    private static function clip(string $text, int $len): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || self::isUrlLike($text)) {
            return $text;
        }
        if (mb_strlen($text) <= $len) {
            return $text;
        }

        return mb_substr($text, 0, max(1, $len - 1)) . '…';
    }

    /** URL / путь — никогда не режем в деталях (иначе «…» и нельзя скопировать). */
    private static function isUrlLike(string $text): bool
    {
        if (preg_match('#^https?://#i', $text)) {
            return true;
        }

        return (bool) preg_match('#^/[^\s]{2,}$#', $text);
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 2) . ' MB';
    }
}
