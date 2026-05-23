<?php

namespace App\Support;

/**
 * Брендинг PDF-отчёта анализа текста (mPDF, DejaVu Sans).
 */
class TextAnalyzerPdfBranding
{
    public const BRAND_NAME = 'Датагон';

    public const BRAND_TAGLINE = 'SEO-инструменты для специалистов';

    public const BRAND_SITE = 'https://datagon.ru';

    public const COLOR_PRIMARY = '#2f5de0';

    public const COLOR_PRIMARY_DARK = '#1e3f9e';

    public const COLOR_ACCENT = '#8fd3ff';

    /**
     * Данные для Blade PDF (пути к картинкам — абсолютные, для mPDF).
     */
    public static function viewData(): array
    {
        return [
            'brandName' => self::BRAND_NAME,
            'brandTagline' => self::BRAND_TAGLINE,
            'brandSite' => self::BRAND_SITE,
            'brandSiteHost' => parse_url(self::BRAND_SITE, PHP_URL_HOST) ?: 'datagon.ru',
            'logoIconPath' => self::logoIconPath(),
            'logoFullPath' => self::logoFullPath(),
        ];
    }

    /**
     * Иконка 64×64 (PNG) для шапки и колонтитула.
     */
    public static function logoIconPath(): string
    {
        $path = public_path('img/logo-icon-pdf.png');
        if (!is_file($path)) {
            self::writeIconPng($path, 64);
        }

        return $path;
    }

    /**
     * Логотип с подписью «Датагон» (PNG) для обложки отчёта.
     */
    public static function logoFullPath(): string
    {
        $path = public_path('img/logo-pdf.png');
        if (!is_file($path)) {
            self::writeFullLogoPng($path);
        }

        return $path;
    }

    /**
     * @param array<int, array<string, mixed>> $graph
     * @return array<int, array<string, mixed>>
     */
    public static function zipfTableRows(array $graph): array
    {
        if ($graph === []) {
            return [];
        }

        $baseY = (int) ($graph[0]['y'] ?? 1);
        $rows = [];
        foreach ($graph as $point) {
            $rank = (int) ($point['rank'] ?? $point['x'] ?? 0);
            if ($rank < 1) {
                continue;
            }
            $actual = (int) ($point['y'] ?? 0);
            $ideal = max(1, (int) round($baseY / $rank));
            $rows[] = [
                'rank' => $rank,
                'word' => (string) ($point['label'] ?? ''),
                'actual' => $actual,
                'ideal' => $ideal,
                'delta' => $actual - $ideal,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $cloudItems
     * @return array<int, array<string, mixed>>
     */
    public static function cloudRowsForPdf(array $cloudItems, int $limit = 18): array
    {
        $slice = array_slice($cloudItems, 0, $limit);
        if ($slice === []) {
            return [];
        }

        $maxWeight = 1;
        foreach ($slice as $item) {
            $w = (int) ($item['weight'] ?? 1);
            if ($w > $maxWeight) {
                $maxWeight = $w;
            }
        }

        $rows = [];
        foreach ($slice as $item) {
            $weight = (int) ($item['weight'] ?? 1);
            $ratio = $maxWeight > 0 ? $weight / $maxWeight : 1;
            $tier = max(1, min(5, (int) round($ratio * 4) + 1));
            $rows[] = [
                'text' => (string) ($item['text'] ?? ''),
                'weight' => $weight,
                'tier' => $tier,
            ];
        }

        return $rows;
    }

    protected static function writeIconPng(string $path, int $size): void
    {
        $im = imagecreatetruecolor($size, $size);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);

        $blue = imagecolorallocate($im, 47, 93, 224);
        $blueDark = imagecolorallocate($im, 30, 63, 158);
        $white = imagecolorallocate($im, 255, 255, 255);
        $accent = imagecolorallocate($im, 143, 211, 255);

        $pad = (int) round($size * 0.0625);
        imagefilledrectangle($im, $pad, $pad, $size - $pad - 1, $size - $pad - 1, $blue);
        imagefilledrectangle($im, $pad + 2, $pad + 2, (int) ($size * 0.55), $size - $pad - 3, $blueDark);

        $x0 = (int) round($size * 0.28);
        $y0 = (int) round($size * 0.28);
        $y1 = (int) round($size * 0.72);
        imagesetthickness($im, max(2, (int) round($size / 14)));
        imageline($im, $x0, $y0, $x0, $y1, $white);
        imageline($im, $x0, $y0, (int) round($size * 0.52), $y0, $white);
        imageline($im, $x0, (int) round(($y0 + $y1) / 2), (int) round($size * 0.52), (int) round(($y0 + $y1) / 2), $white);

        imagefilledellipse($im, (int) round($size * 0.74), (int) round($size * 0.28), (int) round($size * 0.08), (int) round($size * 0.08), $accent);
        imagefilledellipse($im, (int) round($size * 0.82), (int) round($size * 0.36), (int) round($size * 0.06), (int) round($size * 0.06), $accent);

        self::savePng($im, $path);
    }

    protected static function writeFullLogoPng(string $path): void
    {
        $w = 220;
        $h = 48;
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);

        $iconPath = sys_get_temp_dir() . '/dg-logo-icon-tmp.png';
        self::writeIconPng($iconPath, 40);
        $icon = imagecreatefrompng($iconPath);
        if ($icon !== false) {
            imagecopy($im, $icon, 2, 4, 0, 0, 40, 40);
            imagedestroy($icon);
        }
        @unlink($iconPath);

        $textColor = imagecolorallocate($im, 244, 246, 249);
        $font = self::resolveFontPath();
        if ($font !== null) {
            imagettftext($im, 15, 0, 50, 32, $textColor, $font, self::BRAND_NAME);
        } else {
            imagestring($im, 5, 50, 16, 'Datagon', $textColor);
        }

        self::savePng($im, $path);
    }

    protected static function resolveFontPath(): ?string
    {
        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/opt/homebrew/share/fonts/dejavu-sans-fonts/DejaVuSans.ttf',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected static function savePng($im, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagepng($im, $path);
        imagedestroy($im);
    }
}
