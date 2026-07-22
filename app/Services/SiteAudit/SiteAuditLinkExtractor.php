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
     *   nofollow_links:int,
     *   external_assets:string[],
     *   meta_nofollow:bool
     * }
     */
    public function extract(string $html, string $baseUrl, string $projectHost, array $opts = []): array
    {
        $internal = [];
        $internalCounts = [];
        $external = [];
        $nofollowLinks = 0;
        $externalAssets = [];
        $badLinks = [];

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

        if (preg_match_all('/<a\b([^>]*)>/i', $html, $anchors)) {
            foreach ($anchors[1] as $attrs) {
                if (! preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $hm)) {
                    if (count($badLinks) < 15) {
                        $badLinks[] = ['href' => null, 'reason' => 'missing_href'];
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
                } else {
                    $any = SiteAuditUrlNormalizer::resolve($href, $baseUrl, null, $opts);
                    if ($any) {
                        $external[$any] = true;
                    } elseif (count($badLinks) < 15) {
                        $badLinks[] = [
                            'href' => mb_substr($href, 0, 200),
                            'reason' => 'unresolvable',
                        ];
                    }
                }
            }
        }

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
                $imgSrcs[$abs] = true;
                if (count($imgSrcs) >= 40) {
                    break;
                }
            }
        }

        $patterns = [
            '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<link\b[^>]*\brel\s*=\s*["\'][^"\']*stylesheet[^"\']*["\'][^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i',
            '/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*\brel\s*=\s*["\'][^"\']*stylesheet[^"\']*["\']/i',
        ];
        $assetSrcs = [];
        foreach ($patterns as $re) {
            if (! preg_match_all($re, $html, $mm)) {
                continue;
            }
            $group = isset($mm[1]) ? $mm[1] : [];
            foreach ($group as $src) {
                $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($src === '' || strpos($src, 'data:') === 0) {
                    continue;
                }
                $abs = SiteAuditUrlNormalizer::resolve($src, $baseUrl, null, $opts);
                if (! $abs) {
                    continue;
                }
                $assetSrcs[$abs] = true;
                if (count($assetSrcs) >= 40) {
                    break 2;
                }
            }
        }

        $patternsExt = [
            '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i',
        ];
        foreach ($patternsExt as $re) {
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
                $h = SiteAuditUrlNormalizer::hostOf($abs);
                if (! $h) {
                    continue;
                }
                $bare = preg_replace('/^www\./', '', $h);
                $baseBare = preg_replace('/^www\./', '', strtolower($projectHost));
                if ($bare !== $baseBare) {
                    $externalAssets[$abs] = true;
                    if (count($externalAssets) >= 20) {
                        break 2;
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
            'nofollow_links' => $nofollowLinks,
            'external_assets' => array_keys($externalAssets),
            'meta_nofollow' => $metaNofollow,
            'duplicate_links' => $dupLinks,
            'duplicate_links_count' => count(array_filter($internalCounts, function ($c) {
                return $c > 1;
            })),
            'bad_links' => $badLinks,
            'img_srcs' => array_keys($imgSrcs),
            'asset_srcs' => array_keys($assetSrcs),
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

        return null;
    }
}
