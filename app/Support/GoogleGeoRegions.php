<?php

namespace App\Support;

/**
 * Справочник Google geo (xmlstock geotargets): страны и города мира.
 */
class GoogleGeoRegions
{
    /** @var array<int, array{id: string, name: string, name_en?: string, country_code?: string, type?: string}>|null */
    private static $cache;

    public static function all(): array
    {
        if (self::$cache === null) {
            $path = config_path('google_geo_regions.json');
            if (! is_readable($path)) {
                self::$cache = [];

                return self::$cache;
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            self::$cache = is_array($decoded) ? $decoded : [];
        }

        return self::$cache;
    }

    /**
     * @return array<int, array{id: string, name: string, text: string}>
     */
    public static function search(string $query, int $limit = 25): array
    {
        $query = trim($query);
        if ($query === '') {
            return self::defaults($limit);
        }

        $needles = GoogleGeoSearchAliases::variantsFor($query);
        if ($needles === []) {
            return [];
        }

        $preferredIds = [];
        foreach ($needles as $n) {
            $pref = GoogleGeoSearchAliases::preferredIds()[$n] ?? null;
            if ($pref) {
                $preferredIds[$pref] = true;
            }
        }

        $scored = [];

        foreach (self::all() as $region) {
            $id = (string) ($region['id'] ?? '');
            $name = (string) ($region['name'] ?? '');
            $nameEn = (string) ($region['name_en'] ?? '');
            $cc = (string) ($region['country_code'] ?? '');
            $type = (string) ($region['type'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }

            $nameL = mb_strtolower($name);
            $nameEnL = mb_strtolower($nameEn);
            $ccL = mb_strtolower($cc);

            $score = null;
            foreach ($needles as $needle) {
                $local = null;
                if ($nameL === $needle || $nameEnL === $needle) {
                    $local = 0;
                } elseif ($ccL === $needle && $type === 'Country') {
                    $local = 1;
                } elseif (mb_strpos($nameL, $needle) === 0 || mb_strpos($nameEnL, $needle) === 0) {
                    $local = 2;
                } elseif (mb_strpos($nameL, $needle) !== false || mb_strpos($nameEnL, $needle) !== false) {
                    $local = 3;
                } elseif ($ccL !== '' && mb_strpos($ccL, $needle) !== false) {
                    $local = 4;
                } elseif (mb_strpos($id, $needle) !== false) {
                    $local = 5;
                }
                if ($local !== null && ($score === null || $local < $score)) {
                    $score = $local;
                }
            }

            if ($score === null) {
                continue;
            }

            // Предпочтительные столицы (Вашингтон DC и т.п.) — выше остальных одноимённых
            $prefBoost = isset($preferredIds[$id]) ? 0 : 1;
            $typeBoost = $type === 'Country' ? 0 : 1;
            $scored[] = [
                'score' => $score,
                'prefBoost' => $prefBoost,
                'typeBoost' => $typeBoost,
                'item' => self::formatItem($id, $name, $nameEn, $cc, $type),
            ];
        }

        usort($scored, static function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $a['score'] <=> $b['score'];
            }
            if ($a['prefBoost'] !== $b['prefBoost']) {
                return $a['prefBoost'] <=> $b['prefBoost'];
            }
            if ($a['typeBoost'] !== $b['typeBoost']) {
                return $a['typeBoost'] <=> $b['typeBoost'];
            }

            return strcmp($a['item']['name'], $b['item']['name']);
        });

        $results = [];
        foreach ($scored as $row) {
            $results[] = $row['item'];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public static function find(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        foreach (self::all() as $region) {
            if ((string) ($region['id'] ?? '') === $id) {
                return self::formatItem(
                    $id,
                    (string) $region['name'],
                    (string) ($region['name_en'] ?? ''),
                    (string) ($region['country_code'] ?? ''),
                    (string) ($region['type'] ?? '')
                );
            }
        }

        return null;
    }

    /**
     * @param array<int, string|int> $ids
     * @return array<int, array{id: string, name: string, text: string}>
     */
    public static function resolveMany(array $ids, int $max = 5): array
    {
        $out = [];
        $seen = [];

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $item = self::find($id);
            if ($item === null) {
                return [];
            }

            $seen[$id] = true;
            $out[] = $item;

            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{id: string, name: string, text: string}>
     */
    private static function defaults(int $limit): array
    {
        $preferred = [
            '1011969', // Москва
            '1012040', // СПб
            '2840', // United States
            '1023191', // New York
            '2826', // United Kingdom
            '1006886', // London GB
            '2276', // Germany
            '1003854', // Berlin
            '2112', // Belarus
            '1001493', // Minsk BY
            '2804', // Ukraine
            '1012852', // Kyiv
            '2077', // Kazakhstan
            '9063099', // Almaty
            '2124', // Poland
            '1011419', // Warsaw
        ];
        $out = [];

        foreach ($preferred as $id) {
            $item = self::find($id);
            if ($item) {
                $out[] = $item;
            }
        }

        if (count($out) >= $limit) {
            return array_slice($out, 0, $limit);
        }

        foreach (self::all() as $region) {
            $id = (string) ($region['id'] ?? '');
            if ($id === '' || in_array($id, $preferred, true)) {
                continue;
            }
            $out[] = self::formatItem(
                $id,
                (string) $region['name'],
                (string) ($region['name_en'] ?? ''),
                (string) ($region['country_code'] ?? ''),
                (string) ($region['type'] ?? '')
            );
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private static function formatItem(
        string $id,
        string $name,
        string $nameEn = '',
        string $countryCode = '',
        string $type = ''
    ): array {
        $label = $name;
        if ($nameEn !== '' && $nameEn !== $name) {
            $label = $name . ' (' . $nameEn . ')';
        }
        if ($countryCode !== '') {
            $label .= ' · ' . strtoupper($countryCode);
        }
        if ($type === 'Country') {
            $label .= ' · страна';
        }

        return [
            'id' => $id,
            'name' => $name,
            'name_en' => $nameEn,
            'country_code' => $countryCode,
            'type' => $type,
            'text' => $label . ' [' . $id . ']',
        ];
    }
}
