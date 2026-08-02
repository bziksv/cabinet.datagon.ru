<?php

namespace App\SeoReports;

/**
 * Normalize agency brand color for UI/PDF without breaking contrast.
 */
class SeoReportBrandColor
{
    public static function normalize(?string $hex, string $fallback = '#0f172a'): string
    {
        $hex = trim((string) $hex);
        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
            return $fallback;
        }
        if (strlen($hex) === 4) {
            $hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
        }

        [$r, $g, $b] = self::rgb($hex);
        $luminance = self::relativeLuminance($r, $g, $b);
        // Too light on white backgrounds → darken toward slate.
        if ($luminance > 0.65) {
            return self::mix($hex, '#0f172a', 0.55);
        }
        // Too dark for accents on dark theme → slightly lift.
        if ($luminance < 0.08) {
            return self::mix($hex, '#ffffff', 0.25);
        }

        return strtolower($hex);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [
            hexdec(substr($h, 0, 2)),
            hexdec(substr($h, 2, 2)),
            hexdec(substr($h, 4, 2)),
        ];
    }

    private static function relativeLuminance(int $r, int $g, int $b): float
    {
        $channels = array_map(static function ($c) {
            $c = $c / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function mix(string $a, string $b, float $weightTowardB): string
    {
        $weightTowardB = max(0.0, min(1.0, $weightTowardB));
        [$ar, $ag, $ab] = self::rgb($a);
        [$br, $bg, $bb] = self::rgb($b);
        $r = (int) round($ar * (1 - $weightTowardB) + $br * $weightTowardB);
        $g = (int) round($ag * (1 - $weightTowardB) + $bg * $weightTowardB);
        $bl = (int) round($ab * (1 - $weightTowardB) + $bb * $weightTowardB);

        return sprintf('#%02x%02x%02x', $r, $g, $bl);
    }
}
