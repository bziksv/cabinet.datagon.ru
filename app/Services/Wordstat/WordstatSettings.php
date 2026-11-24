<?php
namespace App\Services\Wordstat;

class WordstatSettings
{
    public const DEVICES = ['all', 'desktop', 'phone', 'tablet'];
    public const COLUMNS = ['left', 'right'];

    public static function defaults(): array
    {
        return [
            'region_ids' => [213],
            'device' => 'all',
            'columns' => ['left'],
            'left_limit' => 200,
            'depth' => 0,
            'depth_left_limit' => 50,
            'depth_max_seeds' => 100,
            'minus_words' => [],
            'stop_words' => [],
            'exact_freq' => false,
        ];
    }

    public static function normalize(array $raw): array
    {
        $d = self::defaults();

        $regions = [];
        foreach ((array) ($raw['region_ids'] ?? $d['region_ids']) as $id) {
            if (is_numeric($id) && (int) $id > 0 && ! in_array((int) $id, $regions, true)) {
                $regions[] = (int) $id;
            }
        }
        $regions = array_slice($regions, 0, 100);

        $device = is_string($raw['device'] ?? null) ? $raw['device'] : $d['device'];
        $columns = $d['columns'];
        if (isset($raw['columns']) && is_array($raw['columns'])) {
            $columns = array_values(array_unique(array_filter($raw['columns'], fn ($c) => in_array($c, self::COLUMNS, true))));
        }

        return [
            'region_ids' => $regions !== [] ? $regions : $d['region_ids'],
            'device' => in_array($device, self::DEVICES, true) ? $device : 'all',
            'columns' => $columns !== [] ? $columns : $d['columns'],
            'left_limit' => self::clampInt($raw['left_limit'] ?? $d['left_limit'], 1, 2000),
            'depth' => self::clampInt($raw['depth'] ?? $d['depth'], 0, 2),
            'depth_left_limit' => self::clampInt($raw['depth_left_limit'] ?? $d['depth_left_limit'], 1, 200),
            'depth_max_seeds' => self::clampInt($raw['depth_max_seeds'] ?? $d['depth_max_seeds'], 0, 1000),
            'minus_words' => self::parseWordList($raw['minus_words'] ?? []),
            'stop_words' => self::parseWordList($raw['stop_words'] ?? []),
            'exact_freq' => filter_var($raw['exact_freq'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** @return array<int, string> */
    public static function parseWordList($value): array
    {
        if (! is_array($value) && ! is_scalar($value)) {
            return [];
        }
        $items = is_array($value) ? $value : preg_split('/[\r\n,]+/u', (string) $value);
        $out = [];
        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $word = trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $item)));
            $word = str_replace('ё', 'е', $word);
            if ($word !== '' && ! in_array($word, $out, true)) {
                $out[] = $word;
            }
        }

        return array_slice($out, 0, 500);
    }

    private static function clampInt($value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}
