<?php

namespace App\SeoReports;

/**
 * Подписи измерений Метрики (каналы/устройства) для отчётов.
 * API иногда отдаёт английские name — нормализуем по id и известным EN-строкам.
 */
final class SeoReportMetrikaLabels
{
    /** @var array<string, string> */
    private const BY_ID = [
        'direct' => 'Direct traffic',
        'organic' => 'Search engine traffic',
        'ad' => 'Ad traffic',
        'referral' => 'Link traffic',
        'internal' => 'Internal traffic',
        'social' => 'Social network traffic',
        'recommend' => 'Recommendation system traffic',
        'messenger' => 'Messenger traffic',
        'email' => 'Mailing traffic',
        'saved' => 'Cached pages traffic',
        'qr' => 'QR code traffic',
        'undefined' => 'Undefined traffic',
        'desktop' => 'Desktop',
        'mobile' => 'Smartphones',
        'tablet' => 'Tablets',
        'tv' => 'TV',
    ];

    public static function label(?string $name, $id = null): string
    {
        $name = trim((string) $name);
        $idKey = strtolower(trim((string) $id));

        if ($idKey !== '' && isset(self::BY_ID[$idKey])) {
            return __(self::BY_ID[$idKey]);
        }

        if ($name === '') {
            return '—';
        }

        return __($name);
    }

    /** Подмена известных EN-подписей Метрики в уже сохранённых текстах инсайтов. */
    public static function localizeText(string $text): string
    {
        $map = [];
        foreach (array_values(self::BY_ID) as $en) {
            $map[$en] = __($en);
        }
        foreach (['PC', 'PCs', 'Smartphones', 'Tablets', 'TV', 'Desktop'] as $en) {
            $map[$en] = __($en);
        }
        uksort($map, static function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        return strtr($text, $map);
    }
}
