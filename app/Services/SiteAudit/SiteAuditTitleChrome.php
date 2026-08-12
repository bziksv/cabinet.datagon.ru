<?php

namespace App\Services\SiteAudit;

/**
 * Хвосты TITLE вроде « | Blog | Prime LTD» / « — Компания» повторяются на всём сайте
 * и не должны считаться «общими словами» похожих страниц.
 */
class SiteAuditTitleChrome
{
    /**
     * Самые частые суффиксы TITLE по краулу (после | / — / – / ·).
     *
     * @param  iterable<int, string|null>  $titles
     * @return list<string> нормализованные суффиксы (lowercase), частые ≥ порога
     */
    public static function detectCommonSuffixes($titles, int $pageCount, float $minShare = 0.35): array
    {
        if ($pageCount < 5) {
            return [];
        }

        $counts = [];
        $seenTitles = 0;
        foreach ($titles as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $seenTitles++;
            foreach (self::suffixCandidates($title) as $suf) {
                $counts[$suf] = ($counts[$suf] ?? 0) + 1;
            }
        }
        if ($seenTitles < 5) {
            return [];
        }

        $need = max(4, (int) ceil($seenTitles * $minShare));
        $out = [];
        foreach ($counts as $suf => $c) {
            if ($c >= $need && mb_strlen($suf) >= 3) {
                $out[] = $suf;
            }
        }
        usort($out, static function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        return array_slice($out, 0, 12);
    }

    /**
     * Убрать известные суффиксы / сегменты бренда из TITLE.
     *
     * @param  list<string>  $suffixes
     */
    public static function stripSuffixes(string $title, array $suffixes): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        if ($title === '' || $suffixes === []) {
            return $title;
        }

        $lower = mb_strtolower($title);
        foreach ($suffixes as $suf) {
            $suf = mb_strtolower(trim((string) $suf));
            if ($suf === '' || mb_strlen($suf) < 3) {
                continue;
            }
            // точное окончание
            if (mb_substr($lower, -mb_strlen($suf)) === $suf) {
                $cut = mb_strlen($title) - mb_strlen($suf);
                $title = rtrim(mb_substr($title, 0, max(0, $cut)), " \t|-–—·•:/");
                $lower = mb_strtolower($title);
            }
        }

        // сегменты после разделителя, целиком входящие в список суффиксов/их куски
        $parts = preg_split('/\s*[|–—·•]\s*/u', $title) ?: [$title];
        if (count($parts) > 1) {
            $kept = [];
            $sufSet = [];
            foreach ($suffixes as $suf) {
                $sufSet[mb_strtolower(trim((string) $suf))] = true;
            }
            foreach ($parts as $i => $part) {
                $p = trim($part);
                $pl = mb_strtolower($p);
                if ($i > 0 && (isset($sufSet[$pl]) || self::partLooksLikeBrand($pl, $sufSet))) {
                    continue;
                }
                $kept[] = $p;
            }
            if ($kept !== []) {
                $title = implode(' | ', $kept);
            }
        }

        return trim($title);
    }

    /**
     * Токены домена проекта (prime-ltd.su → prime, ltd) — почти всегда шаблон.
     *
     * @return list<string>
     */
    public static function domainBrandTokens(?string $domain): array
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^https?://#i', '', $domain) ?: '';
        $domain = preg_replace('#^www\.#i', '', $domain) ?: '';
        $domain = explode('/', $domain)[0] ?? $domain;
        $domain = preg_replace('/:\d+$/', '', $domain) ?: '';
        if ($domain === '') {
            return [];
        }
        $host = explode('.', $domain)[0] ?? '';
        $parts = preg_split('/[^a-z0-9а-яё]+/iu', $host, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = mb_strtolower($p);
            if (mb_strlen($p) >= 3 && ! in_array($p, ['www', 'com', 'net', 'org', 'info'], true)) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private static function suffixCandidates(string $title): array
    {
        $out = [];
        $parts = preg_split('/\s*[|–—·•]\s*/u', $title) ?: [];
        $n = count($parts);
        if ($n >= 2) {
            // последний сегмент и «последние два»
            $last = trim((string) $parts[$n - 1]);
            if ($last !== '') {
                $out[] = mb_strtolower($last);
            }
            if ($n >= 3) {
                $tail2 = trim($parts[$n - 2] . ' | ' . $parts[$n - 1]);
                if ($tail2 !== '') {
                    $out[] = mb_strtolower($tail2);
                }
            }
        }
        // « — Brand» / « - Brand»
        if (preg_match('/\s+[-–—]\s+([^-–—|]{3,80})\s*$/u', $title, $m)) {
            $out[] = mb_strtolower(trim($m[1]));
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * @param  array<string, bool>  $sufSet
     */
    private static function partLooksLikeBrand(string $partLower, array $sufSet): bool
    {
        foreach ($sufSet as $suf => $_) {
            if ($suf === '') {
                continue;
            }
            if ($partLower === $suf || mb_strpos($suf, $partLower) !== false || mb_strpos($partLower, $suf) !== false) {
                return true;
            }
        }

        return false;
    }
}
