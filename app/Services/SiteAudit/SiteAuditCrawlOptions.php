<?php

namespace App\Services\SiteAudit;

class SiteAuditCrawlOptions
{
    public static function normalize(array $input): array
    {
        $presets = config('site_audit.speed_presets', []);
        $speed = (string) ($input['crawl_speed'] ?? 'normal');
        if (! isset($presets[$speed])) {
            $speed = 'normal';
        }

        $rps = isset($input['rps']) ? (float) $input['rps'] : (float) ($presets[$speed] ?? 1.0);
        $rps = max(0.1, min(20.0, $rps));

        return array_merge($input, [
            'crawl_speed' => $speed,
            'rps' => $rps,
            'save_html' => $input['save_html'] ?? 'off',
            'exclude_patterns' => SiteAuditUrlFilter::parsePatterns($input['exclude_patterns'] ?? []),
            'virtual_robots' => self::normalizeVirtualRobots($input['virtual_robots'] ?? ''),
            // URL-нормализация всегда включена (не опция UI).
            'unify_www' => true,
            'force_https' => true,
            'strip_trailing_slash' => true,
            // Битые ссылки всегда проверяем (не опция UI).
            'check_broken_links' => true,
        ]);
    }

    private static function normalizeVirtualRobots($raw): string
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return '';
        }
        $max = (int) config('site_audit.robots_max_bytes', 512000);

        return strlen($text) > $max ? substr($text, 0, $max) : $text;
    }
}
