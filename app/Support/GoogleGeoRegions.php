<?php

namespace App\Support;

class GoogleGeoRegions
{
    /** @var array<int, array{id: string, name: string}>|null */
    private static $cache;

    public static function all(): array
    {
        if (self::$cache === null) {
            $path = config_path('google_geo_regions.json');
            if (!is_readable($path)) {
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

        $needle = mb_strtolower($query);
        $results = [];

        foreach (self::all() as $region) {
            $id = (string) ($region['id'] ?? '');
            $name = (string) ($region['name'] ?? '');
            $nameEn = (string) ($region['name_en'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }

            $hay = mb_strtolower($name . ' ' . $nameEn . ' ' . $id);
            if (mb_strpos($hay, $needle) === false) {
                continue;
            }

            $results[] = self::formatItem($id, $name, (string) ($region['name_en'] ?? ''));
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
                return self::formatItem($id, (string) $region['name'], (string) ($region['name_en'] ?? ''));
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
        $preferred = ['1011969', '1012040', '1011874', '1011952', '1011934', '1011981', '1012052', '1011896', '1011909'];
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
            $out[] = self::formatItem($id, (string) $region['name'], (string) ($region['name_en'] ?? ''));
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private static function formatItem(string $id, string $name, string $nameEn = ''): array
    {
        $label = $name;
        if ($nameEn !== '' && $nameEn !== $name) {
            $label = $name . ' (' . $nameEn . ')';
        }

        return [
            'id' => $id,
            'name' => $name,
            'name_en' => $nameEn,
            'text' => $label . ' [' . $id . ']',
        ];
    }
}
