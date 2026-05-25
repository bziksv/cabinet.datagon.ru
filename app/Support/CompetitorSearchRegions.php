<?php

namespace App\Support;

class CompetitorSearchRegions
{
    public static function normalizeEngine(?string $engine): string
    {
        $engine = strtolower(trim((string) $engine));

        return $engine === 'google' ? 'google' : 'yandex';
    }

    /**
     * @return array<int, array{id: string, name: string, text: string}>
     */
    public static function search(string $engine, string $query, int $limit = 25): array
    {
        if (self::normalizeEngine($engine) === 'google') {
            return GoogleGeoRegions::search($query, $limit);
        }

        return YandexLrRegions::search($query, $limit);
    }

    public static function find(string $engine, string $id): ?array
    {
        if (self::normalizeEngine($engine) === 'google') {
            return GoogleGeoRegions::find($id);
        }

        return YandexLrRegions::find($id);
    }

    /**
     * @param array<int, string|int> $ids
     * @return array<int, array{id: string, name: string, text: string}>
     */
    public static function resolveMany(string $engine, array $ids, int $max = 5): array
    {
        if (self::normalizeEngine($engine) === 'google') {
            return GoogleGeoRegions::resolveMany($ids, $max);
        }

        return YandexLrRegions::resolveMany($ids, $max);
    }

    public static function defaultRegion(string $engine): ?array
    {
        $defaultId = self::normalizeEngine($engine) === 'google' ? '1011969' : '213';

        return self::find($engine, $defaultId);
    }

    /**
     * @param array<int, string>|string|null $engines
     * @return array<int, string>
     */
    public static function normalizeEnginesList($engines): array
    {
        if (is_string($engines)) {
            $engines = [$engines];
        }

        if (!is_array($engines)) {
            return ['yandex'];
        }

        $allowed = config('cabinet-competitor-analysis.search_engines', ['yandex', 'google']);
        $out = [];

        foreach ($engines as $engine) {
            $engine = self::normalizeEngine($engine);
            if (in_array($engine, $allowed, true) && !in_array($engine, $out, true)) {
                $out[] = $engine;
            }
        }

        return $out ?: ['yandex'];
    }

    public static function regionKey(string $engine, string $regionId): string
    {
        return self::normalizeEngine($engine) . '|' . trim($regionId);
    }

    public static function engineLabel(string $engine): string
    {
        return self::normalizeEngine($engine) === 'google' ? 'Google' : __('Yandex');
    }

    /**
     * @param array<int, array{engine: string, regions: array}> $plan
     * @return array<int, array{engine: string, id: string, name: string, text: string, key: string, tabLabel: string}>
     */
    public static function flattenRegionsForTabs(array $plan): array
    {
        $out = [];

        foreach ($plan as $item) {
            $engine = self::normalizeEngine($item['engine'] ?? '');
            foreach ($item['regions'] ?? [] as $region) {
                $id = (string) ($region['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $name = (string) ($region['name'] ?? '');
                $key = self::regionKey($engine, $id);
                $out[] = [
                    'engine' => $engine,
                    'id' => $id,
                    'name' => $name,
                    'text' => (string) ($region['text'] ?? $name),
                    'key' => $key,
                    'tabLabel' => self::engineLabel($engine) . ' · ' . ($name ?: $id),
                ];
            }
        }

        return $out;
    }

    /**
     * @param array<int, string> $engines
     * @return array<int, array{engine: string, regions: array<int, array{id: string, name: string, text: string}>}>
     */
    public static function buildAnalysisPlanFromRequest(array $engines, array $regionsByEngine, int $maxRegions): array
    {
        $plan = [];

        foreach ($engines as $engine) {
            $engine = self::normalizeEngine($engine);
            $ids = array_values(array_unique(array_filter((array) ($regionsByEngine[$engine] ?? []))));
            if (count($ids) === 0) {
                continue;
            }

            $regions = self::resolveMany($engine, $ids, $maxRegions);
            if (count($regions) !== count($ids)) {
                return [];
            }

            $plan[] = [
                'engine' => $engine,
                'regions' => $regions,
            ];
        }

        return $plan;
    }
}
