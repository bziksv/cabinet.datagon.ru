<?php

namespace App\Services\SiteAudit;

/**
 * Столбцы инвентаря «Картинки проверки».
 */
class SiteAuditCrawlImagesColumns
{
    public const MISSING = '<ОТСУТСТВУЕТ>';

    /**
     * @return list<array{key:string,label:string,group:string,default?:bool,locked?:bool}>
     */
    public static function catalog(): array
    {
        return [
            ['key' => 'url', 'label' => 'URL картинки', 'group' => 'base', 'default' => true, 'locked' => true],
            ['key' => 'page_url', 'label' => 'Страница', 'group' => 'base', 'default' => true],
            ['key' => 'status', 'label' => 'Код', 'group' => 'base', 'default' => true],
            ['key' => 'size', 'label' => 'Размер', 'group' => 'base', 'default' => true],
            ['key' => 'dims', 'label' => 'Ш×В', 'group' => 'base', 'default' => true],
            ['key' => 'ext', 'label' => 'Тип', 'group' => 'base', 'default' => true],
            ['key' => 'https', 'label' => 'HTTPS', 'group' => 'base', 'default' => false],
            ['key' => 'external', 'label' => 'Внешняя', 'group' => 'base', 'default' => true],
            ['key' => 'alt', 'label' => 'Alt', 'group' => 'meta', 'default' => true],
            ['key' => 'has_alt', 'label' => 'Есть alt', 'group' => 'meta', 'default' => false],
            ['key' => 'loading', 'label' => 'loading', 'group' => 'meta', 'default' => false],
            ['key' => 'content_type', 'label' => 'Content-Type', 'group' => 'meta', 'default' => false],
            ['key' => 'width', 'label' => 'Ширина', 'group' => 'meta', 'default' => false],
            ['key' => 'height', 'label' => 'Высота', 'group' => 'meta', 'default' => false],
            ['key' => 'url_len', 'label' => 'Длина URL', 'group' => 'meta', 'default' => false],
            ['key' => 'file', 'label' => 'Файл', 'group' => 'meta', 'default' => false],
        ];
    }

    /**
     * @return array<string,array{label:string,cols:list<string>}>
     */
    public static function presets(): array
    {
        return [
            'main' => [
                'label' => 'Основные',
                'cols' => ['url', 'page_url', 'status', 'size', 'dims', 'ext', 'external', 'alt'],
            ],
            'weight' => [
                'label' => 'Вес / битые',
                'cols' => ['url', 'page_url', 'status', 'size', 'ext', 'https', 'external', 'content_type'],
            ],
            'meta' => [
                'label' => 'Alt / атрибуты',
                'cols' => ['url', 'page_url', 'alt', 'has_alt', 'dims', 'width', 'height', 'loading', 'file'],
            ],
            'all' => [
                'label' => 'Все столбцы',
                'cols' => array_column(self::catalog(), 'key'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        $keys = [];
        foreach (self::catalog() as $col) {
            if (! empty($col['default']) || ! empty($col['locked'])) {
                $keys[] = $col['key'];
            }
        }

        return $keys;
    }

    /**
     * Ключи, по которым можно сортировать (in-memory).
     *
     * @return array<string,true>
     */
    public static function sortableKeys(): array
    {
        return [
            'url' => true,
            'page_url' => true,
            'status' => true,
            'size' => true,
            'dims' => true,
            'ext' => true,
            'https' => true,
            'external' => true,
            'alt' => true,
            'has_alt' => true,
            'loading' => true,
            'content_type' => true,
            'width' => true,
            'height' => true,
            'url_len' => true,
            'file' => true,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function normalizeSort(?string $sort, ?string $dir): array
    {
        $sort = (string) $sort;
        if ($sort === '' || ! isset(self::sortableKeys()[$sort])) {
            return ['url', 'asc'];
        }
        $dir = strtolower((string) $dir) === 'desc' ? 'desc' : 'asc';

        return [$sort, $dir];
    }

    public static function defaultDir(string $sortKey): string
    {
        return in_array($sortKey, [
            'status', 'size', 'dims', 'width', 'height', 'url_len', 'https', 'external', 'has_alt',
        ], true) ? 'desc' : 'asc';
    }

    /**
     * Значение для сравнения строк инвентаря.
     *
     * @param  array<string,mixed>  $meta
     * @return mixed
     */
    public static function sortValue(string $key, string $url, array $meta)
    {
        switch ($key) {
            case 'url':
                return mb_strtolower($url);
            case 'page_url':
                return mb_strtolower((string) ($meta['page_url'] ?? ''));
            case 'status':
                return isset($meta['status']) ? (int) $meta['status'] : null;
            case 'size':
                return isset($meta['size_bytes']) ? (int) $meta['size_bytes'] : null;
            case 'dims':
                $w = isset($meta['width']) ? (int) $meta['width'] : 0;
                $h = isset($meta['height']) ? (int) $meta['height'] : 0;
                if ($w <= 0 && $h <= 0) {
                    return null;
                }

                return $w * max(1, $h);
            case 'ext':
                return (string) ($meta['ext'] ?? '');
            case 'https':
                return ! empty($meta['https']) ? 1 : 0;
            case 'external':
                return ! empty($meta['external']) ? 1 : 0;
            case 'alt':
                return mb_strtolower((string) ($meta['alt'] ?? ''));
            case 'has_alt':
                if (! array_key_exists('has_alt', $meta) || $meta['has_alt'] === null) {
                    return null;
                }

                return $meta['has_alt'] ? 1 : 0;
            case 'loading':
                return (string) ($meta['loading'] ?? '');
            case 'content_type':
                return mb_strtolower((string) ($meta['content_type'] ?? ''));
            case 'width':
                return isset($meta['width']) ? (int) $meta['width'] : null;
            case 'height':
                return isset($meta['height']) ? (int) $meta['height'] : null;
            case 'url_len':
                return isset($meta['url_len']) ? (int) $meta['url_len'] : mb_strlen($url);
            case 'file':
                return mb_strtolower((string) ($meta['file'] ?? ''));
            default:
                return null;
        }
    }
}
