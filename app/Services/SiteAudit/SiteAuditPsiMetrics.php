<?php

namespace App\Services\SiteAudit;

/**
 * Пороги Core Web Vitals / Lighthouse и форматирование для UI PSI.
 * @see https://web.dev/articles/vitals
 */
final class SiteAuditPsiMetrics
{
    /**
     * @return 'good'|'needs-improvement'|'poor'|'unknown'
     */
    public static function scoreBand(?int $pct): string
    {
        if ($pct === null) {
            return 'unknown';
        }
        if ($pct >= 90) {
            return 'good';
        }
        if ($pct >= 50) {
            return 'needs-improvement';
        }

        return 'poor';
    }

    /**
     * @return 'good'|'needs-improvement'|'poor'|'unknown'
     */
    public static function lcpBand(?float $ms): string
    {
        if ($ms === null) {
            return 'unknown';
        }
        if ($ms <= 2500) {
            return 'good';
        }
        if ($ms <= 4000) {
            return 'needs-improvement';
        }

        return 'poor';
    }

    /**
     * @return 'good'|'needs-improvement'|'poor'|'unknown'
     */
    public static function clsBand(?float $cls): string
    {
        if ($cls === null) {
            return 'unknown';
        }
        if ($cls <= 0.1) {
            return 'good';
        }
        if ($cls <= 0.25) {
            return 'needs-improvement';
        }

        return 'poor';
    }

    /**
     * TBT как прокси INP в lab (PageSpeed).
     *
     * @return 'good'|'needs-improvement'|'poor'|'unknown'
     */
    public static function tbtBand(?float $ms): string
    {
        if ($ms === null) {
            return 'unknown';
        }
        if ($ms <= 200) {
            return 'good';
        }
        if ($ms <= 600) {
            return 'needs-improvement';
        }

        return 'poor';
    }

    /**
     * @return 'good'|'needs-improvement'|'poor'|'unknown'
     */
    public static function fcpBand(?float $ms): string
    {
        if ($ms === null) {
            return 'unknown';
        }
        if ($ms <= 1800) {
            return 'good';
        }
        if ($ms <= 3000) {
            return 'needs-improvement';
        }

        return 'poor';
    }

    /**
     * @return 'good'|'needs-improvement'|'poor'|'unknown'
     */
    public static function siBand(?float $ms): string
    {
        if ($ms === null) {
            return 'unknown';
        }
        if ($ms <= 3400) {
            return 'good';
        }
        if ($ms <= 5800) {
            return 'needs-improvement';
        }

        return 'poor';
    }

    public static function bandLabel(string $band): string
    {
        if ($band === 'good') {
            return 'Хорошо';
        }
        if ($band === 'needs-improvement') {
            return 'Улучшить';
        }
        if ($band === 'poor') {
            return 'Плохо';
        }

        return '—';
    }

    public static function formatMs(?float $ms): string
    {
        if ($ms === null) {
            return '—';
        }
        if ($ms >= 1000) {
            return round($ms / 1000, 1) . ' с';
        }

        return (int) round($ms) . ' мс';
    }

    public static function formatCls(?float $cls): string
    {
        if ($cls === null) {
            return '—';
        }

        return rtrim(rtrim(number_format($cls, 3, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Краткая строка для таблицы / экспорта (обратная совместимость).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function compactLine(array $meta): string
    {
        $parts = [];
        if (isset($meta['score_pct'])) {
            $parts[] = 'Perf ' . (int) $meta['score_pct'];
        }
        if (isset($meta['lcp_ms'])) {
            $parts[] = 'LCP ' . self::formatMs((float) $meta['lcp_ms']);
        }
        if (isset($meta['cls'])) {
            $parts[] = 'CLS ' . self::formatCls((float) $meta['cls']);
        }
        if (isset($meta['tbt_ms'])) {
            $parts[] = 'TBT ' . self::formatMs((float) $meta['tbt_ms']);
        }
        if (isset($meta['fcp_ms'])) {
            $parts[] = 'FCP ' . self::formatMs((float) $meta['fcp_ms']);
        }
        if (isset($meta['si_ms'])) {
            $parts[] = 'SI ' . self::formatMs((float) $meta['si_ms']);
        }
        foreach (['accessibility', 'best_practices', 'seo'] as $cat) {
            $key = $cat . '_pct';
            if (isset($meta[$key])) {
                if ($cat === 'accessibility') {
                    $label = 'A11y';
                } elseif ($cat === 'best_practices') {
                    $label = 'BP';
                } elseif ($cat === 'seo') {
                    $label = 'SEO';
                } else {
                    $label = $cat;
                }
                $parts[] = $label . ' ' . (int) $meta[$key];
            }
        }

        return $parts !== [] ? implode(' · ', $parts) : 'PageSpeed Insights';
    }

    /**
     * Сводка по списку findings для шапки отчёта.
     *
     * @param  iterable<int, object>  $findings
     * @return array{avg: ?int, good: int, mid: int, poor: int, total: int, worst_url: ?string, worst_pct: ?int}
     */
    public static function summarize($findings): array
    {
        $sum = 0;
        $n = 0;
        $good = 0;
        $mid = 0;
        $poor = 0;
        $worstUrl = null;
        $worstPct = null;

        foreach ($findings as $f) {
            $meta = is_array($f->meta_json ?? null) ? $f->meta_json : [];
            if (! isset($meta['score_pct'])) {
                continue;
            }
            $pct = (int) $meta['score_pct'];
            $sum += $pct;
            $n++;
            $band = self::scoreBand($pct);
            if ($band === 'good') {
                $good++;
            } elseif ($band === 'needs-improvement') {
                $mid++;
            } else {
                $poor++;
            }
            if ($worstPct === null || $pct < $worstPct) {
                $worstPct = $pct;
                $worstUrl = (string) ($f->url ?? '');
            }
        }

        return [
            'avg' => $n > 0 ? (int) round($sum / $n) : null,
            'good' => $good,
            'mid' => $mid,
            'poor' => $poor,
            'total' => $n,
            'worst_url' => $worstUrl,
            'worst_pct' => $worstPct,
        ];
    }
}
