<?php

namespace App\Support;

/**
 * Обучающие ролики модулей: self-host в public/media/module-videos/ (вне git).
 */
class ModuleVideos
{
    public const RELATIVE_DIR = 'media/module-videos';

    public static function isEnabled(): bool
    {
        return (bool) config('cabinet-module-videos.enabled', true);
    }

    public static function diskPath(string $youtubeId): string
    {
        return public_path(self::RELATIVE_DIR . '/' . self::safeId($youtubeId) . '.mp4');
    }

    public static function posterDiskPath(string $youtubeId): string
    {
        return public_path(self::RELATIVE_DIR . '/' . self::safeId($youtubeId) . '.jpg');
    }

    public static function hasVideo(string $youtubeId): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        return is_readable(self::diskPath($youtubeId));
    }

    public static function videoUrl(string $youtubeId): ?string
    {
        if (!self::hasVideo($youtubeId)) {
            return null;
        }

        return asset(self::RELATIVE_DIR . '/' . self::safeId($youtubeId) . '.mp4');
    }

    public static function posterUrl(string $youtubeId): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $id = self::safeId($youtubeId);
        if (is_readable(self::posterDiskPath($youtubeId))) {
            return asset(self::RELATIVE_DIR . '/' . $id . '.jpg');
        }

        return null;
    }

    /**
     * HTML описания модуля: локальные постеры/плеер, без iframe_api в разметке.
     */
    public static function rewriteDescriptionHtml(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        if (!self::isEnabled()) {
            return $html;
        }

        $html = preg_replace(
            '#<script[^>]*src=["\']https?://www\.youtube\.com/iframe_api["\'][^>]*>\s*</script>#i',
            '',
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '#<div\s+([^>]*\bdata-id=["\']([a-zA-Z0-9_-]{11})["\'][^>]*)>#i',
            static function (array $m): string {
                $attrs = $m[1];
                $youtubeId = $m[2];
                $isCourse = (bool) preg_match(
                    '#\bid=["\']video-course["\']#i',
                    $attrs
                ) || (bool) preg_match('#\bclass=["\'][^"\']*\bvideo-course\b#i', $attrs);
                if (!$isCourse) {
                    return $m[0];
                }

                return self::rewriteVideoCourseOpenTag($attrs, $youtubeId);
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '#//img\.youtube\.com/vi/([a-zA-Z0-9_-]{11})/[^"\']+#',
            static function (array $m): string {
                $poster = self::posterUrl($m[1]);

                return $poster !== null
                    ? htmlspecialchars($poster, ENT_QUOTES, 'UTF-8')
                    : $m[0];
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '#(?:<p>\s*)?<iframe[^>]+src=["\']https?://(?:www\.)?youtube\.com/embed/([a-zA-Z0-9_-]{11})[^"\']*["\'][^>]*>\s*</iframe>(?:\s*</p>)?#i',
            static function (array $m): string {
                $url = self::videoUrl($m[1]);
                if ($url === null) {
                    return $m[0];
                }

                $poster = self::posterUrl($m[1]) ?? '';

                return '<div class="video-course" data-id="' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '"'
                    . ' data-local="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                    . '<video class="module-video-selfhosted" controls playsinline preload="metadata"'
                    . ($poster !== '' ? ' poster="' . e($poster) . '"' : '')
                    . ' src="' . e($url) . '"></video>'
                    . '</div>';
            },
            $html
        ) ?? $html;

        return $html;
    }

    private static function rewriteVideoCourseOpenTag(string $attrs, string $youtubeId): string
    {
        $local = self::videoUrl($youtubeId);
        $poster = self::posterUrl($youtubeId);
        $attrs = preg_replace('#\sdata-local=["\'][^"\']*["\']#i', '', $attrs) ?? $attrs;

        if ($local !== null) {
            $attrs .= ' data-local="' . htmlspecialchars($local, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<div ' . trim($attrs) . '>';
    }

    private static function safeId(string $youtubeId): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $youtubeId) ?: $youtubeId;
    }
}
