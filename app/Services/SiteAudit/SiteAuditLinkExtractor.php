<?php

namespace App\Services\SiteAudit;

/**
 * Извлечение ссылок и внешних ресурсов из HTML.
 */
class SiteAuditLinkExtractor
{
    /**
     * @param array $opts normalize options
     * @return array{
     *   internal:string[],
     *   external:string[],
     *   external_items:list<array{url:string,text:string}>,
     *   nofollow_links:int,
     *   nofollow_samples:list<array{href:string,text:string,snippet:string,scope:string}>,
     *   external_assets:string[],
     *   external_asset_items:list<array{url:string,kind:string}>,
     *   meta_nofollow:bool
     * }
     */
    public function extract(string $html, string $baseUrl, string $projectHost, array $opts = []): array
    {
        $internal = [];
        $internalCounts = [];
        $external = [];
        $externalItems = [];
        $nofollowLinks = 0;
        $nofollowSamples = [];
        $externalAssets = [];
        $externalAssetItems = [];
        $badLinks = [];

        // Ссылки в <!-- ... --> не живут в DOM для пользователя/бота — не считаем.
        $html = $this->stripHtmlComments($html);
        // Примеры в JSON-LD / <script> / <style> — не кликабельные ссылки страницы.
        $htmlForAnchors = $this->stripNonRenderableMarkup($html);

        $robots = [];
        if (preg_match_all('/<meta\b[^>]*\bname\s*=\s*["\']robots["\'][^>]*>/i', $html, $mt)) {
            foreach ($mt[0] as $tag) {
                if (preg_match('/\bcontent\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $m)) {
                    $robots[] = (isset($m[2]) && $m[2] !== '') ? $m[2] : ($m[3] ?? '');
                }
            }
        }
        $metaNofollow = false;
        foreach ($robots as $r) {
            if (preg_match('/\bnofollow\b/i', $r)) {
                $metaNofollow = true;
                break;
            }
        }

        if (preg_match_all('/<a\b([^>]*)>/i', $htmlForAnchors, $anchors, PREG_OFFSET_CAPTURE)) {
            foreach ($anchors[1] as $i => $attrMatch) {
                $attrs = $attrMatch[0];
                $openFull = $anchors[0][$i][0];
                $openPos = (int) $anchors[0][$i][1];
                $context = $this->anchorContext($htmlForAnchors, $openFull, $openPos);

                if (! preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $hm)) {
                    // <a name="..."> / <a id="..."> — якорь-закладка, не «плохая ссылка».
                    if (preg_match('/\b(name|id)\s*=/i', $attrs)) {
                        continue;
                    }
                    if (count($badLinks) < 15) {
                        $badLinks[] = [
                            'href' => null,
                            'reason' => 'missing_href',
                            'text' => $context['text'],
                            'snippet' => $context['snippet'],
                        ];
                    }
                    continue;
                }
                $hrefRaw = (isset($hm[2]) && $hm[2] !== '') ? $hm[2]
                    : ((isset($hm[3]) && $hm[3] !== '') ? $hm[3] : ($hm[4] ?? ''));
                $href = html_entity_decode(trim($hrefRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                $badReason = $this->badHrefReason($href);
                if ($badReason !== null) {
                    if (count($badLinks) < 15) {
                        $badLinks[] = [
                            'href' => mb_substr($href, 0, 200),
                            'reason' => $badReason,
                            'text' => $context['text'],
                            'snippet' => $context['snippet'],
                        ];
                    }
                    continue;
                }

                if ($href === '' || $href[0] === '#' || stripos($href, 'mailto:') === 0 || stripos($href, 'tel:') === 0) {
                    continue;
                }

                $isNofollow = (bool) preg_match('/\brel\s*=\s*["\'][^"\']*\bnofollow\b/i', $attrs);
                if ($isNofollow) {
                    $nofollowLinks++;
                }

                $abs = SiteAuditUrlNormalizer::resolve($href, $baseUrl, $projectHost, $opts);
                if ($abs) {
                    $internal[$abs] = true;
                    $internalCounts[$abs] = ($internalCounts[$abs] ?? 0) + 1;
                    if ($isNofollow && count($nofollowSamples) < 20) {
                        $nofollowSamples[] = [
                            'href' => $abs,
                            'text' => $context['text'],
                            'snippet' => $context['snippet'],
                            'scope' => 'internal',
                        ];
                    }
                } else {
                    $any = SiteAuditUrlNormalizer::resolve($href, $baseUrl, null, $opts);
                    if ($any) {
                        $external[$any] = true;
                        if (count($externalItems) < 40) {
                            $externalItems[] = [
                                'url' => $any,
                                'text' => $context['text'],
                            ];
                        }
                        if ($isNofollow && count($nofollowSamples) < 20) {
                            $nofollowSamples[] = [
                                'href' => $any,
                                'text' => $context['text'],
                                'snippet' => $context['snippet'],
                                'scope' => 'external',
                            ];
                        }
                    } elseif (count($badLinks) < 15) {
                        $badLinks[] = [
                            'href' => mb_substr($href, 0, 200),
                            'reason' => 'unresolvable',
                            'text' => $context['text'],
                            'snippet' => $context['snippet'],
                        ];
                    }
                }
            }
        }

        $addExternalAsset = function (string $abs, string $kind) use (&$externalAssets, &$externalAssetItems, $projectHost) {
            $h = SiteAuditUrlNormalizer::hostOf($abs);
            if (! $h) {
                return;
            }
            $bare = preg_replace('/^www\./', '', $h);
            $baseBare = preg_replace('/^www\./', '', strtolower($projectHost));
            if ($bare === $baseBare) {
                return;
            }
            if (isset($externalAssets[$abs])) {
                return;
            }
            $externalAssets[$abs] = true;
            $externalAssetItems[] = ['url' => $abs, 'kind' => $kind];
        };

        $imgSrcs = [];
        if (preg_match_all('/<img\b([^>]*)>/i', $html, $imgTags)) {
            foreach ($imgTags[1] as $attrs) {
                if (! preg_match('/\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $sm)) {
                    continue;
                }
                $src = html_entity_decode(trim(
                    (isset($sm[2]) && $sm[2] !== '') ? $sm[2] : ($sm[3] ?? '')
                ), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($src === '' || stripos($src, 'data:') === 0) {
                    continue;
                }
                $abs = SiteAuditUrlNormalizer::resolve($src, $baseUrl, null, $opts);
                if (! $abs) {
                    continue;
                }
                if (! isset($imgSrcs[$abs])) {
                    $altRaw = null;
                    $hasAltAttr = false;
                    if (preg_match('/\balt\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $am)) {
                        $hasAltAttr = true;
                        $altRaw = html_entity_decode(trim(
                            (isset($am[2]) && $am[2] !== '') ? $am[2] : ($am[3] ?? '')
                        ), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    $width = null;
                    $height = null;
                    if (preg_match('/\bwidth\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $wm)) {
                        $width = SiteAuditImageItem::parsePxAttr(
                            (isset($wm[2]) && $wm[2] !== '') ? $wm[2] : ((isset($wm[3]) && $wm[3] !== '') ? $wm[3] : ($wm[4] ?? ''))
                        );
                    }
                    if (preg_match('/\bheight\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $hm)) {
                        $height = SiteAuditImageItem::parsePxAttr(
                            (isset($hm[2]) && $hm[2] !== '') ? $hm[2] : ((isset($hm[3]) && $hm[3] !== '') ? $hm[3] : ($hm[4] ?? ''))
                        );
                    }
                    $loading = null;
                    if (preg_match('/\bloading\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $lm)) {
                        $loading = strtolower(trim(
                            (isset($lm[2]) && $lm[2] !== '') ? $lm[2] : ($lm[3] ?? '')
                        ));
                        if ($loading === '') {
                            $loading = null;
                        }
                    }
                    $imgSrcs[$abs] = [
                        'src' => $abs,
                        'alt' => $altRaw,
                        'has_alt' => $hasAltAttr && $altRaw !== '',
                        'width' => $width,
                        'height' => $height,
                        'loading' => $loading,
                    ];
                }
                if (count($externalAssetItems) < 25) {
                    $addExternalAsset($abs, 'img');
                }
                if (count($imgSrcs) >= 40) {
                    break;
                }
            }
        }

        // Внешние script + stylesheet (+ полный список asset_srcs для других проверок)
        $assetSrcs = [];
        $assetPatterns = [
            'script' => ['/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i'],
            'css' => [
                '/<link\b[^>]*\brel\s*=\s*["\'][^"\']*stylesheet[^"\']*["\'][^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i',
                '/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*\brel\s*=\s*["\'][^"\']*stylesheet[^"\']*["\']/i',
            ],
        ];
        foreach ($assetPatterns as $kind => $patterns) {
            foreach ($patterns as $re) {
                if (! preg_match_all($re, $html, $mm)) {
                    continue;
                }
                foreach ($mm[1] as $src) {
                    $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($src === '' || strpos($src, 'data:') === 0) {
                        continue;
                    }
                    $abs = SiteAuditUrlNormalizer::resolve($src, $baseUrl, null, $opts);
                    if (! $abs) {
                        continue;
                    }
                    $assetSrcs[$abs] = true;
                    if (count($externalAssetItems) < 25) {
                        $addExternalAsset($abs, $kind);
                    }
                    if (count($assetSrcs) >= 40 && count($externalAssetItems) >= 25) {
                        break 3;
                    }
                }
            }
        }

        $dupLinks = [];
        foreach ($internalCounts as $u => $c) {
            if ($c > 1) {
                $dupLinks[] = ['url' => $u, 'count' => $c];
                if (count($dupLinks) >= 10) {
                    break;
                }
            }
        }

        return [
            'internal' => array_keys($internal),
            'external' => array_keys($external),
            'external_items' => $externalItems,
            'nofollow_links' => $nofollowLinks,
            'nofollow_samples' => $nofollowSamples,
            'external_assets' => array_keys($externalAssets),
            'external_asset_items' => $externalAssetItems,
            'meta_nofollow' => $metaNofollow,
            'duplicate_links' => $dupLinks,
            'duplicate_links_count' => count(array_filter($internalCounts, function ($c) {
                return $c > 1;
            })),
            'bad_links' => $badLinks,
            'img_srcs' => array_values($imgSrcs),
            'asset_srcs' => array_keys($assetSrcs),
        ];
    }

    /**
     * Убрать HTML-комментарии, не трогая содержимое script/style/textarea
     * (там могут быть строки с «<!--»).
     */
    private function stripHtmlComments(string $html): string
    {
        if ($html === '' || strpos($html, '<!--') === false) {
            return $html;
        }

        $slots = [];
        $html = preg_replace_callback(
            '/<(script|style|textarea)\b[^>]*>.*?<\/\1>/is',
            static function ($m) use (&$slots) {
                $key = "\x00SA_SLOT_" . count($slots) . "\x00";
                $slots[$key] = $m[0];

                return $key;
            },
            $html
        );

        $html = preg_replace('/<!--.*?-->/s', '', $html);
        if (! is_string($html)) {
            $html = '';
        }

        if ($slots) {
            $html = strtr($html, $slots);
        }

        return $html;
    }

    /**
     * Убрать script/style/noscript перед поиском &lt;a&gt;.
     * Иначе примеры в JSON-LD FAQ (`&lt;a href="https://example.com/"&gt;`)
     * попадают в обход как «битые» URL.
     * Asset src из &lt;script src&gt; берём с исходного HTML отдельно.
     */
    private function stripNonRenderableMarkup(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $out = preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', '', $html);
        if (! is_string($out)) {
            $out = $html;
        }

        return $out;
    }

    /**
     * Текст и короткий HTML-фрагмент якоря для отчёта «какая ссылка».
     *
     * @return array{text:string,snippet:string}
     */
    private function anchorContext(string $html, string $openTag, int $openPos): array
    {
        $rest = substr($html, $openPos + strlen($openTag), 500);
        $inner = '';
        if (is_string($rest) && preg_match('/^(.*?)<\/a>/is', $rest, $m)) {
            $inner = $m[1];
            $snippet = $openTag . $inner . '</a>';
        } else {
            $snippet = $openTag;
        }

        $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';
        if (mb_strlen($text) > 80) {
            $text = mb_substr($text, 0, 79) . '…';
        }

        $snippet = preg_replace('/\s+/u', ' ', $snippet) ?: $snippet;
        if (mb_strlen($snippet) > 160) {
            $snippet = mb_substr($snippet, 0, 159) . '…';
        }

        return [
            'text' => $text,
            'snippet' => $snippet,
        ];
    }

    /**
     * «Плохие» href (не HTTP-битые): пустые, #, javascript:, около-мусор.
     * Нормальные #якорь и mailto/tel сюда не входят.
     */
    private function badHrefReason(string $href): ?string
    {
        if ($href === '' || $href === '#' || preg_match('/^#\s*$/', $href)) {
            return 'empty_or_hash';
        }
        if (stripos($href, 'javascript:') === 0) {
            return 'javascript';
        }
        if (preg_match('/^\s*javascript\s*:/i', $href)) {
            return 'javascript';
        }
        // пробел / кавычки в «сыром» виде
        if (preg_match('/\s/', $href) && ! preg_match('#^https?://#i', $href)) {
            return 'whitespace';
        }
        // Кавычки/бэкслеш внутри href — мусор из вёрстки или примеров в статьях
        // (типа href=\"https://example.com/\" → склейка с базовым URL).
        if (strpbrk($href, "\"'\\<>") !== false) {
            return 'quotes';
        }
        if (preg_match('#https?://.+https?://#i', $href)) {
            return 'nested_url';
        }

        return null;
    }
}
