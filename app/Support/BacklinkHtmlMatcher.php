<?php

namespace App\Support;

/**
 * Поиск целевой ссылки на HTML донора: редиректы / кривые href (http://https://…) / кавычки.
 */
class BacklinkHtmlMatcher
{
    /**
     * Нормализация URL для сравнения.
     */
    public static function normalizeUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = mb_strtolower($url);
        $url = str_replace('\\', '/', $url);

        // Частый мусор площадок объявлений: http://https://… или https://http://…
        $url = preg_replace('#^(?:https?:)?/+https://#u', 'https://', $url) ?? $url;
        $url = preg_replace('#^(?:https?:)?/+http://#u', 'http://', $url) ?? $url;
        $url = preg_replace('#^http://https://#u', 'https://', $url) ?? $url;
        $url = preg_replace('#^https://http://#u', 'http://', $url) ?? $url;

        // Убрать дефолтные порты
        $url = preg_replace('#^(https?://[^/]+):80(/|$)#u', '$1$2', $url) ?? $url;
        $url = preg_replace('#^(https?://[^/]+):443(/|$)#u', '$1$2', $url) ?? $url;

        // www.
        $url = preg_replace('#^(https?://)www\.#u', '$1', $url) ?? $url;

        // хвостовой слэш (кроме корня)
        if (preg_match('#^https?://[^/]+/.+#u', $url)) {
            $url = rtrim($url, '/');
        }

        return $url;
    }

    /**
     * Безанкорная проверка: пустой анкор или анкор совпадает с целевым URL.
     */
    public static function isAnchorless(string $anchor, string $linkUrl): bool
    {
        $anchor = trim($anchor);

        return $anchor === '' || self::urlsMatch($anchor, $linkUrl);
    }

    /**
     * Совпадают ли два URL (с учётом схемы, www, мусорного префикса).
     */
    public static function urlsMatch(string $candidate, string $expected): bool
    {
        $a = self::normalizeUrl($candidate);
        $b = self::normalizeUrl($expected);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        $strip = static function (string $u): string {
            return preg_replace('#^https?://#u', '', $u) ?? $u;
        };

        $as = $strip($a);
        $bs = $strip($b);
        if ($as === '' || $bs === '') {
            return false;
        }
        if ($as === $bs) {
            return true;
        }

        // Относительный path на доноре ≈ path целевого URL
        if (isset($as[0]) && $as[0] === '/') {
            $expectedPath = parse_url('https://' . $bs, PHP_URL_PATH);
            if (is_string($expectedPath) && $expectedPath !== '') {
                $expectedPath = rtrim($expectedPath, '/') ?: '/';
                $rel = rtrim($as, '/') ?: '/';
                if ($rel === $expectedPath) {
                    return true;
                }
            }

            return false;
        }

        // Оба абсолютные host/path — допускаем вхождение только для «длинных» URL
        if (! self::looksLikeHostPath($as) || ! self::looksLikeHostPath($bs)) {
            return false;
        }
        $minLen = min(strlen($as), strlen($bs));
        if ($minLen < 12) {
            return false;
        }

        return strpos($as, $bs) !== false || strpos($bs, $as) !== false;
    }

    private static function looksLikeHostPath(string $hostPath): bool
    {
        return (bool) preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#iu', $hostPath);
    }

    /**
     * @return array{found: bool, node_html?: string, href?: string, text?: string, in_comment_noindex?: bool}
     */
    public static function find(string $html, string $linkUrl, string $anchor = ''): array
    {
        if (! preg_match_all(
            '#<a\b([^>]*)>(.*?)</a>#is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return ['found' => false];
        }

        $anchorNorm = mb_strtolower(trim($anchor));

        foreach ($matches as $match) {
            $attrs = $match[1];
            $inner = $match[2];
            if (! preg_match('#\bhref\s*=\s*(["\'])(.*?)\1#is', $attrs, $hm)) {
                continue;
            }
            $href = html_entity_decode($hm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            $hrefOk = self::urlsMatch($href, $linkUrl);
            $textOk = $text !== '' && self::urlsMatch($text, $linkUrl);
            if (! $hrefOk && ! $textOk) {
                continue;
            }

            // Пустой анкор или анкор = URL цели → безанкорная: достаточно совпадения href.
            $anchorless = self::isAnchorless($anchor, $linkUrl);
            if (! $anchorless) {
                $textLow = mb_strtolower($text);
                $anchorOk = $textLow === $anchorNorm
                    || mb_strpos($textLow, $anchorNorm) !== false
                    || self::urlsMatch($text, $anchor);
                if (! $anchorOk) {
                    continue;
                }
            }

            $full = $match[0];
            $inCommentNoindex = self::isWrappedInCommentNoindex($html, $full);

            return [
                'found' => true,
                'anchorless' => $anchorless,
                'node_html' => $full,
                'href' => $href,
                'text' => $text,
                'in_comment_noindex' => $inCommentNoindex,
                'has_nofollow' => (bool) preg_match('#\brel\s*=\s*(["\'])[^"\']*\bnofollow\b[^"\']*\1#i', $attrs),
            ];
        }

        return ['found' => false];
    }

    /**
     * Яндекс-стиль: <!--noindex-->…<a>…<!--/noindex-->
     */
    public static function isWrappedInCommentNoindex(string $html, string $anchorHtml): bool
    {
        $pos = mb_stripos($html, $anchorHtml);
        if ($pos === false) {
            return false;
        }
        $before = mb_substr($html, max(0, $pos - 80), 80);
        $after = mb_substr($html, $pos + mb_strlen($anchorHtml), 80);

        return (bool) (
            preg_match('#<!--\s*noindex\s*-->\s*$#iu', $before)
            && preg_match('#^\s*<!--\s*/noindex\s*-->#iu', $after)
        );
    }

    /**
     * Скачать HTML донора с follow redirect (http→https и т.п.).
     */
    public static function fetchHtml(string $pageUrl): ?string
    {
        $pageUrl = trim($pageUrl);
        if ($pageUrl === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $pageUrl)) {
            $pageUrl = 'http://' . $pageUrl;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $pageUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            // FAILONERROR ломает часть доноров с промежуточными 4xx на CDN — не используем.
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
            ],
        ]);
        $html = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (! is_string($html) || $html === '' || $code >= 400) {
            return null;
        }

        return $html;
    }
}
