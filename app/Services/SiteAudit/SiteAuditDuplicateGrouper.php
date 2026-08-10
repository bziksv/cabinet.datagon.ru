<?php

namespace App\Services\SiteAudit;

use Illuminate\Support\Collection;

class SiteAuditDuplicateGrouper
{
    private const GROUPABLE = [
        'duplicate_title',
        'duplicate_description',
        'duplicate_content',
        'html_critical_errors',
    ];

    public static function isGroupable(string $code): bool
    {
        return in_array($code, self::GROUPABLE, true);
    }

    public static function isHtmlErrors(string $code): bool
    {
        return $code === 'html_critical_errors';
    }

    /**
     * @param  Collection|iterable  $rows  SiteAuditFinding-like objects with meta_json
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>,hint:?string,likely_template:bool}>
     */
    public static function group($rows, string $code): array
    {
        if (self::isHtmlErrors($code)) {
            return self::groupHtmlErrors($rows);
        }

        $buckets = [];

        foreach ($rows as $row) {
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $hash = (string) ($meta['hash'] ?? '');
            if ($hash === '') {
                $hash = 'u:' . md5((string) ($row->url ?? '') . '|' . ($row->id ?? uniqid('', true)));
            }

            if (! isset($buckets[$hash])) {
                $buckets[$hash] = [
                    'hash' => $hash,
                    'size' => (int) ($meta['group_size'] ?? 0),
                    'label' => self::labelFor($code, $meta),
                    'severity' => (string) ($row->severity ?? 'other'),
                    'urls' => [],
                    'hint' => null,
                    'likely_template' => false,
                ];
            }

            $buckets[$hash]['urls'][] = [
                'url' => (string) $row->url,
                'severity' => (string) ($row->severity ?? 'other'),
            ];

            if ($buckets[$hash]['size'] < count($buckets[$hash]['urls'])) {
                $buckets[$hash]['size'] = count($buckets[$hash]['urls']);
            }
        }

        return self::sortGroups(array_values($buckets));
    }

    /**
     * Группировка HTML-ошибок по тексту (без номера строки): одна правка в шаблоне → много URL.
     *
     * @param  Collection|iterable  $rows
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>,hint:?string,likely_template:bool}>
     */
    private static function groupHtmlErrors($rows): array
    {
        $buckets = [];
        $pageTotal = 0;

        foreach ($rows as $row) {
            $pageTotal++;
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
            $url = (string) ($row->url ?? '');
            $severity = (string) ($row->severity ?? 'other');
            $seenOnPage = [];

            if ($samples === []) {
                $sig = 'empty';
                $label = 'Ошибка HTML без текста сэмпла';
                $hint = null;
                $samples = [['message' => $label]];
            }

            foreach ($samples as $sample) {
                if (! is_array($sample)) {
                    continue;
                }
                $msg = trim((string) ($sample['message'] ?? ''));
                if ($msg === '') {
                    continue;
                }
                $sig = self::normalizeHtmlSignature($msg);
                if ($sig === '' || isset($seenOnPage[$sig])) {
                    continue;
                }
                $seenOnPage[$sig] = true;

                if (! isset($buckets[$sig])) {
                    $buckets[$sig] = [
                        'hash' => $sig,
                        'size' => 0,
                        'label' => self::clipLabel($msg, 140),
                        'severity' => $severity,
                        'urls' => [],
                        'hint' => SiteAuditFindingHelp::htmlErrorHint($msg),
                        'likely_template' => false,
                        '_urls' => [],
                    ];
                }

                if (! isset($buckets[$sig]['_urls'][$url])) {
                    $buckets[$sig]['_urls'][$url] = true;
                    $buckets[$sig]['urls'][] = [
                        'url' => $url,
                        'severity' => $severity,
                    ];
                    $buckets[$sig]['size'] = count($buckets[$sig]['urls']);
                }
            }
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            unset($bucket['_urls']);
            $bucket['likely_template'] = self::isLikelyTemplate($bucket['size'], $pageTotal);
            $groups[] = $bucket;
        }

        return self::sortGroups($groups);
    }

    /**
     * Нормализация текста ошибки: без строки, без лишних пробелов.
     */
    public static function normalizeHtmlSignature(string $message): string
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return '';
        }

        $m = preg_replace('/\s+/u', ' ', $m) ?: $m;
        // libxml иногда тащит " in Entity", номера строк в тексте
        $m = preg_replace('/\s+in entity$/u', '', $m) ?: $m;
        $m = preg_replace('/\b(line|стр\.?|строка)\s*\d+\b/u', '', $m) ?: $m;
        $m = preg_replace('/\s+/u', ' ', $m) ?: $m;

        return trim($m);
    }

    /**
     * Подсказка «сквозной шаблон», если один паттерн доминирует.
     *
     * @param  array<int, array{size:int,label:string,likely_template?:bool}>  $groups
     * @return array{pages:int,total:int,label:string,pct:int}|null
     */
    public static function sitewideSummary(array $groups, int $pageTotal): ?array
    {
        if ($groups === [] || $pageTotal < 3) {
            return null;
        }

        $top = $groups[0];
        $size = (int) ($top['size'] ?? 0);
        if ($size < 3) {
            return null;
        }
        if (! self::isLikelyTemplate($size, $pageTotal)) {
            return null;
        }

        $pct = (int) round(100 * $size / max(1, $pageTotal));

        return [
            'pages' => $size,
            'total' => $pageTotal,
            'label' => (string) ($top['label'] ?? ''),
            'pct' => $pct,
        ];
    }

    private static function isLikelyTemplate(int $size, int $pageTotal): bool
    {
        if ($size < 5) {
            return false;
        }
        if ($pageTotal <= 0) {
            return $size >= 10;
        }

        return ($size / $pageTotal) >= 0.4;
    }

    /**
     * @param  array<int, array{size:int,label:string}>  $groups
     * @return array<int, array{size:int,label:string}>
     */
    private static function sortGroups(array $groups): array
    {
        usort($groups, static function (array $a, array $b) {
            if ($a['size'] === $b['size']) {
                return strcmp($a['label'], $b['label']);
            }

            return $b['size'] <=> $a['size'];
        });

        return $groups;
    }

    private static function labelFor(string $code, array $meta): string
    {
        if ($code === 'duplicate_description' && ! empty($meta['description'])) {
            return (string) $meta['description'];
        }
        if (! empty($meta['title'])) {
            return (string) $meta['title'];
        }
        if (! empty($meta['description'])) {
            return (string) $meta['description'];
        }

        return 'Совпадение без текста';
    }

    private static function clipLabel(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
}
