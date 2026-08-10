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

    /** Телефон или компьютер — для шапки отчёта и карточек. */
    public static function strategyLabelRu(string $codeOrStrategy): string
    {
        $s = strtolower($codeOrStrategy);
        if ($s === 'psi_desktop' || $s === 'desktop') {
            return 'Компьютер';
        }

        return 'Телефон';
    }

    public static function strategyHintRu(string $codeOrStrategy): string
    {
        $s = strtolower($codeOrStrategy);
        if ($s === 'psi_desktop' || $s === 'desktop') {
            return 'Замер Google PageSpeed «как на компьютере» (широкий экран, быстрая сеть в лаборатории Lighthouse). Сравнивайте с отчётом «Мобильные устройства».';
        }

        return 'Замер Google PageSpeed «как на телефоне» (узкий экран, замедлённая сеть в лаборатории Lighthouse). Именно мобильные Core Web Vitals важны для ранжирования.';
    }

    /**
     * @return list<array{k:string,name:string,tip:string,v:string,band:string,limit:string}>
     */
    public static function metricCards(array $meta): array
    {
        $lcp = isset($meta['lcp_ms']) ? (float) $meta['lcp_ms'] : null;
        $cls = isset($meta['cls']) ? (float) $meta['cls'] : null;
        $tbt = isset($meta['tbt_ms']) ? (float) $meta['tbt_ms'] : null;
        $fcp = isset($meta['fcp_ms']) ? (float) $meta['fcp_ms'] : null;
        $si = isset($meta['si_ms']) ? (float) $meta['si_ms'] : null;
        $tti = isset($meta['tti_ms']) ? (float) $meta['tti_ms'] : null;
        $ttfb = isset($meta['ttfb_ms']) ? (float) $meta['ttfb_ms'] : null;

        $cards = [
            [
                'k' => 'LCP',
                'name' => 'Главный контент',
                'tip' => 'Largest Contentful Paint — когда на экране появился самый крупный блок (картинка, заголовок, баннер).',
                'v' => self::formatMs($lcp),
                'band' => self::lcpBand($lcp),
                'limit' => 'норма ≤ 2,5 с',
            ],
            [
                'k' => 'CLS',
                'name' => 'Прыжки вёрстки',
                'tip' => 'Cumulative Layout Shift — насколько страница «прыгает» при загрузке (съезжает текст, кнопки).',
                'v' => self::formatCls($cls),
                'band' => self::clsBand($cls),
                'limit' => 'норма ≤ 0,1',
            ],
            [
                'k' => 'TBT',
                'name' => 'Блокировка',
                'tip' => 'Total Blocking Time — сколько главная нить была занята тяжёлым JS и страница не отвечала на клики (лаборатория).',
                'v' => self::formatMs($tbt),
                'band' => self::tbtBand($tbt),
                'limit' => 'норма ≤ 200 мс',
            ],
            [
                'k' => 'FCP',
                'name' => 'Первая отрисовка',
                'tip' => 'First Contentful Paint — когда пользователь впервые увидел хоть какой-то контент.',
                'v' => self::formatMs($fcp),
                'band' => self::fcpBand($fcp),
                'limit' => 'норма ≤ 1,8 с',
            ],
            [
                'k' => 'SI',
                'name' => 'Заполнение экрана',
                'tip' => 'Speed Index — как быстро визуально заполняется экран при загрузке.',
                'v' => self::formatMs($si),
                'band' => self::siBand($si),
                'limit' => 'норма ≤ 3,4 с',
            ],
        ];
        if ($tti !== null) {
            $cards[] = [
                'k' => 'TTI',
                'name' => 'Интерактивность',
                'tip' => 'Time to Interactive — когда страница стабильно реагирует на действия.',
                'v' => self::formatMs($tti),
                'band' => 'unknown',
                'limit' => '',
            ];
        }
        if ($ttfb !== null) {
            $cards[] = [
                'k' => 'TTFB',
                'name' => 'Ответ сервера',
                'tip' => 'Time to First Byte — сколько ждали первый байт ответа сервера.',
                'v' => self::formatMs($ttfb),
                'band' => 'unknown',
                'limit' => 'желательно < 800 мс',
            ];
        }

        return $cards;
    }

    /**
     * @return list<array{k:string,v:?int,band:string,name:string}>
     */
    public static function categoryCards(array $meta, ?int $perfPct): array
    {
        $items = [
            ['k' => 'Perf', 'key' => null, 'v' => $perfPct, 'name' => 'Скорость'],
            ['k' => 'A11y', 'key' => 'accessibility_pct', 'v' => null, 'name' => 'Доступность'],
            ['k' => 'Best', 'key' => 'best_practices_pct', 'v' => null, 'name' => 'Качество'],
            ['k' => 'SEO', 'key' => 'seo_pct', 'v' => null, 'name' => 'SEO'],
        ];
        $out = [];
        foreach ($items as $it) {
            $v = $it['v'];
            if ($it['key'] !== null) {
                $v = isset($meta[$it['key']]) ? (int) $meta[$it['key']] : null;
            }
            $out[] = [
                'k' => $it['k'],
                'v' => $v,
                'band' => self::scoreBand($v),
                'name' => $it['name'],
            ];
        }

        return $out;
    }

    public static function opportunityTitleRu(string $id, ?string $fallback = null): string
    {
        $map = [
            'unused-javascript' => 'Убрать неиспользуемый JavaScript',
            'unused-css-rules' => 'Убрать неиспользуемый CSS',
            'render-blocking-resources' => 'Убрать ресурсы, блокирующие отрисовку',
            'uses-responsive-images' => 'Правильный размер картинок',
            'uses-optimized-images' => 'Сжать и оптимизировать изображения',
            'modern-image-formats' => 'Современные форматы картинок (WebP/AVIF)',
            'offscreen-images' => 'Отложить загрузку картинок вне экрана',
            'unminified-javascript' => 'Минифицировать JavaScript',
            'unminified-css' => 'Минифицировать CSS',
            'uses-text-compression' => 'Включить сжатие текста (gzip/brotli)',
            'uses-rel-preconnect' => 'Добавить preconnect к важным доменам',
            'server-response-time' => 'Ускорить ответ сервера (TTFB)',
            'redirects' => 'Убрать лишние редиректы',
            'uses-long-cache-ttl' => 'Увеличить срок кэша статики',
            'efficient-animated-content' => 'Оптимизировать анимации/GIF',
            'duplicated-javascript' => 'Убрать дублирующийся JavaScript',
            'legacy-javascript' => 'Убрать устаревший JavaScript (полифиллы)',
            'total-byte-weight' => 'Слишком тяжёлая страница',
            'dom-size' => 'Слишком большой DOM',
            'bootup-time' => 'Сократить время выполнения JavaScript',
            'mainthread-work-breakdown' => 'Слишком много работы в главном потоке',
            'third-party-summary' => 'Сторонние скрипты тормозят страницу',
            'font-display' => 'Настроить отображение шрифтов (font-display)',
            'prioritize-lcp-image' => 'Приоритизировать картинку LCP',
            'lcp-lazy-loaded' => 'LCP-картинка загружается с lazy — уберите lazy',
            'uses-passive-event-listeners' => 'Пассивные обработчики событий',
            'critical-request-chains' => 'Сократить цепочки критических запросов',
            'network-rtt' => 'Высокая задержка сети (RTT)',
            'network-server-latency' => 'Высокая задержка сервера',
            'long-tasks' => 'Длинные задачи в главном потоке',
            'non-composited-animations' => 'Анимации без композитинга',
            'unsized-images' => 'Задать размеры картинок (width/height)',
            'viewport' => 'Настроить viewport для мобильных',
        ];

        if (isset($map[$id])) {
            return $map[$id];
        }

        return $fallback !== null && $fallback !== '' ? $fallback : $id;
    }

    public static function cruxCategoryRu(?string $category): string
    {
        $c = strtoupper((string) $category);
        if ($c === 'FAST') {
            return 'хорошо';
        }
        if ($c === 'AVERAGE') {
            return 'средне';
        }
        if ($c === 'SLOW') {
            return 'медленно';
        }

        return $category !== null && $category !== '' ? $category : '—';
    }

    public static function formatBytesRu(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '';
        }
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, ',', ' '), '0'), ',') . ' МБ';
        }

        return number_format((int) round($bytes / 1024), 0, ',', ' ') . ' КБ';
    }

    public static function formatDisplayRu(?string $display): string
    {
        if ($display === null || $display === '') {
            return '';
        }
        $s = $display;
        $s = preg_replace('/\bEst savings of\b/i', 'экономия ≈', $s) ?? $s;
        $s = preg_replace('/\bsavings of\b/i', 'экономия ≈', $s) ?? $s;
        $s = preg_replace('/\bTotal size was\b/i', 'общий размер', $s) ?? $s;
        $s = preg_replace('/\belements\b/i', 'элементов', $s) ?? $s;
        $s = str_replace(['KiB', 'MiB', 'KB', 'MB'], ['КБ', 'МБ', 'КБ', 'МБ'], $s);

        return $s;
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
