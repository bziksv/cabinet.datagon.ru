<?php

namespace App\Support;

/**
 * Модули в бета-тестировании: плашки в меню/на главной и баннеры на страницах.
 */
class CabinetModuleBeta
{
    /** @var list<string> фрагменты path в link модуля */
    private const LINK_MARKERS = [
        'site-audit',
        'seo-checklist',
        'seo-reports',
    ];

    public static function isBetaLink(?string $link): bool
    {
        if ($link === null || $link === '') {
            return false;
        }

        $path = (string) (parse_url($link, PHP_URL_PATH) ?: $link);
        $path = strtolower($path);

        foreach (self::LINK_MARKERS as $marker) {
            if (strpos($path, '/' . $marker) !== false || strpos($path, $marker) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Короткий ключ для текстов баннера: site-audit | seo-checklist | seo-reports.
     */
    public static function keyFromLink(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }

        $path = strtolower((string) (parse_url($link, PHP_URL_PATH) ?: $link));
        foreach (self::LINK_MARKERS as $marker) {
            if (strpos($path, $marker) !== false) {
                return $marker;
            }
        }

        return null;
    }
}
