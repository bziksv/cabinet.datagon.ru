<?php

namespace App\Classes\Monitoring;

use App\Common;
use App\MonitoringSearchengine;
use App\Support\GoogleGeoRegions;

/**
 * Подписи регионов мониторинга на русском (Google — из google_geo_regions.json).
 */
class MonitoringLocationLabel
{
    public static function displayName(string $engine, string $lr, ?string $locationName = null): string
    {
        $engine = strtolower(trim($engine));
        $lr = trim($lr);
        $locationName = trim((string) $locationName);

        if ($engine === 'google' && $lr !== '') {
            $geo = GoogleGeoRegions::find($lr);
            if ($geo !== null && trim((string) ($geo['name'] ?? '')) !== '') {
                return trim((string) $geo['name']);
            }
        }

        if ($locationName !== '' && self::hasCyrillic($locationName)) {
            return $locationName;
        }

        if ($engine === 'yandex' && $lr !== '') {
            $yandex = Common::getRegionName($lr);
            if ($yandex !== '' && $yandex !== $lr) {
                return $yandex;
            }
        }

        if ($lr !== '') {
            $geo = GoogleGeoRegions::find($lr);
            if ($geo !== null && trim((string) ($geo['name'] ?? '')) !== '') {
                return trim((string) $geo['name']);
            }
        }

        return $locationName !== '' ? $locationName : $lr;
    }

    public static function chartLegend(MonitoringSearchengine $se): string
    {
        return self::filterOption($se);
    }

    public static function engineLabel(string $engine): string
    {
        $engine = strtolower(trim($engine));

        if ($engine === 'yandex') {
            return (string) __('Yandex');
        }
        if ($engine === 'google') {
            return (string) __('Google');
        }

        return $engine !== '' ? mb_convert_case($engine, MB_CASE_TITLE, 'UTF-8') : '';
    }

    public static function filterOption(MonitoringSearchengine $se): string
    {
        return self::engineLabel((string) $se->engine) . ' '
            . self::displayName(
                (string) $se->engine,
                (string) $se->lr,
                $se->location ? (string) $se->location->name : null
            )
            . ' [' . $se->lr . ']';
    }

    public static function chromeLabel(MonitoringSearchengine $se): string
    {
        return self::engineLabel((string) $se->engine) . ' · '
            . self::displayName(
                (string) $se->engine,
                (string) $se->lr,
                $se->location ? (string) $se->location->name : null
            );
    }

    private static function hasCyrillic(string $text): bool
    {
        return (bool) preg_match('/\p{Cyrillic}/u', $text);
    }
}
