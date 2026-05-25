<?php

namespace App\Support;

use Illuminate\Support\Str;

class TextAutoLinker
{
    /**
     * Экранирует HTML, превращает URL в ссылки, сохраняет переносы строк.
     */
    public static function format(string $text, ?int $limit = null): string
    {
        if ($limit !== null) {
            $text = Str::limit($text, $limit);
        }

        $escaped = e($text);
        $linked = self::linkify($escaped);

        return nl2br($linked, false);
    }

    private static function linkify(string $escaped): string
    {
        $pattern = '~(?<![\w/"\'=])(?:https?://|www\.)[^\s<]+[^\s<.,;:!?\)\]\'"»]~iu';

        return preg_replace_callback($pattern, static function (array $match) {
            $visible = $match[0];
            $href = $visible;

            if (stripos($href, 'www.') === 0) {
                $href = 'https://' . $href;
            }

            return '<a href="'
                . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                . '" target="_blank" rel="noopener noreferrer nofollow" class="cabinet-text-autolink">'
                . $visible
                . '</a>';
        }, $escaped) ?? $escaped;
    }
}
