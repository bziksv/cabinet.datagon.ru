<?php

namespace App\Support;

class YandexLrRegions
{
    /** @var array<int, array{id: string, name: string}>|null */
    private static $cache;

    public static function all(): array
    {
        if (self::$cache === null) {
            $path = config_path('yandex_lr_regions.json');
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
            if ($id === '' || $name === '') {
                continue;
            }

            $hay = mb_strtolower($name . ' ' . $id);
            if (mb_strpos($hay, $needle) === false) {
                continue;
            }

            $results[] = self::formatItem($id, $name);
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
                return self::formatItem($id, (string) $region['name']);
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
        $preferred = ['213', '2', '47', '54', '65', '39', '193', '172', '35'];
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
            $out[] = self::formatItem($id, (string) $region['name']);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private static function formatItem(string $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'text' => $name . ' [' . $id . ']',
        ];
    }
}
