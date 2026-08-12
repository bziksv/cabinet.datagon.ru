<?php

namespace App\Services\SiteAudit;

/**
 * Нормализация img из img_srcs_json.
 * Старые проверки — строки; новые — {src,alt,has_alt,width,height,loading,…};
 * после агрегации могут появиться status / size_bytes / ok.
 */
class SiteAuditImageItem
{
    /**
     * @param  mixed  $raw
     * @return array{
     *   src:string,
     *   alt:?string,
     *   has_alt:?bool,
     *   width:?int,
     *   height:?int,
     *   loading:?string,
     *   status:?int,
     *   size_bytes:?int,
     *   ok:?bool,
     *   content_type:?string
     * }|null
     */
    public static function normalizeOne($raw): ?array
    {
        if (is_string($raw)) {
            $src = trim($raw);
            if ($src === '') {
                return null;
            }

            return self::blank($src);
        }
        if (! is_array($raw)) {
            return null;
        }
        $src = trim((string) ($raw['src'] ?? ''));
        if ($src === '') {
            return null;
        }
        $alt = array_key_exists('alt', $raw) ? $raw['alt'] : null;
        if ($alt !== null) {
            $alt = trim((string) $alt);
        }
        $hasAlt = array_key_exists('has_alt', $raw)
            ? (bool) $raw['has_alt']
            : ($alt !== null && $alt !== '');

        $loading = null;
        if (array_key_exists('loading', $raw) && $raw['loading'] !== null && $raw['loading'] !== '') {
            $loading = strtolower(trim((string) $raw['loading']));
            if ($loading === '') {
                $loading = null;
            }
        }

        $status = self::nullableInt($raw['status'] ?? null);
        $sizeBytes = self::nullableInt($raw['size_bytes'] ?? null);
        $ok = null;
        if (array_key_exists('ok', $raw) && $raw['ok'] !== null && $raw['ok'] !== '') {
            $ok = (bool) $raw['ok'];
        } elseif ($status !== null) {
            $ok = $status >= 200 && $status < 400;
        }

        $ct = null;
        if (! empty($raw['content_type'])) {
            $ct = trim((string) $raw['content_type']);
            if ($ct === '') {
                $ct = null;
            }
        }

        return [
            'src' => $src,
            'alt' => $alt,
            'has_alt' => $hasAlt,
            'width' => self::nullableInt($raw['width'] ?? null),
            'height' => self::nullableInt($raw['height'] ?? null),
            'loading' => $loading,
            'status' => $status,
            'size_bytes' => $sizeBytes,
            'ok' => $ok,
            'content_type' => $ct,
        ];
    }

    /**
     * @param  mixed  $list
     * @return list<array<string,mixed>>
     */
    public static function normalizeList($list): array
    {
        if (! is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $raw) {
            $item = self::normalizeOne($raw);
            if ($item === null) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public static function blank(string $src): array
    {
        return [
            'src' => $src,
            'alt' => null,
            'has_alt' => null,
            'width' => null,
            'height' => null,
            'loading' => null,
            'status' => null,
            'size_bytes' => null,
            'ok' => null,
            'content_type' => null,
        ];
    }

    public static function extensionOf(string $src): string
    {
        $path = (string) (parse_url($src, PHP_URL_PATH) ?: '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            return 'jpg';
        }
        if ($ext === '') {
            return 'other';
        }
        if (! in_array($ext, ['webp', 'png', 'jpg', 'gif', 'svg', 'ico', 'avif', 'bmp'], true)) {
            return 'other';
        }

        return $ext;
    }

    public static function hostOf(string $url): string
    {
        $h = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        return preg_replace('/^www\./', '', $h) ?: '';
    }

    /**
     * width/height из атрибута: «1200», «1200px»; проценты и авто — null.
     */
    public static function parsePxAttr($raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim((string) $raw);
        if ($raw === '' || ! preg_match('/^(\d+)\s*(px)?$/i', $raw, $m)) {
            return null;
        }
        $n = (int) $m[1];

        return $n > 0 ? $n : null;
    }

    public static function formatSizeBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return '—';
        }
        if ($bytes < 1024) {
            return number_format($bytes, 0, '', ' ') . ' байт';
        }

        return str_replace('.', ',', number_format($bytes / 1024, 2, '.', ' ')) . ' Кб';
    }

    public static function formatDimensions(?int $w, ?int $h): string
    {
        if ($w === null && $h === null) {
            return '—';
        }
        if ($w === null) {
            return '×' . number_format($h, 0, '', ' ');
        }
        if ($h === null) {
            return number_format($w, 0, '', ' ') . '×';
        }

        return number_format($w, 0, '', ' ') . '×' . number_format($h, 0, '', ' ');
    }

    /**
     * @param  mixed  $v
     */
    private static function nullableInt($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }

        return null;
    }
}
