<?php

namespace App\Services;

use Illuminate\Support\Str;

class SearchSuggestionsService
{
    /** @var string[] */
    private const EN_ALPHABET = [
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
        'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
    ];

    /** @var string[] */
    private const RU_ALPHABET = [
        'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м',
        'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ',
        'ы', 'ь', 'э', 'ю', 'я',
    ];

    /** @var string[] */
    private const DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * @param array{
     *   seeds: string[],
     *   engines: string[],
     *   modes?: array<string, bool>,
     *   presets?: array<string, bool>,
     *   stop_words?: string[],
     *   depth?: int,
     *   yandex_lr?: string,
     *   google_hl?: string,
     *   google_gl?: string,
     * } $options
     * @return array{results: array<int, array<string, mixed>>, cost: int, requests: int, truncated: bool}
     */
    public function collect(array $options): array
    {
        $seeds = $this->normalizeSeeds($options['seeds'] ?? []);
        $engines = array_values(array_intersect(
            ['yandex', 'google'],
            array_map('strtolower', $options['engines'] ?? [])
        ));
        $modes = array_merge(config('cabinet-search-suggestions.modes', []), $options['modes'] ?? []);
        $presets = $options['presets'] ?? [];
        $stopWords = $this->normalizeStopWords($options['stop_words'] ?? []);
        $depth = max(1, min(
            (int) ($options['depth'] ?? 1),
            (int) config('cabinet-search-suggestions.max_depth', 3)
        ));
        $yandexLr = (string) ($options['yandex_lr'] ?? config('cabinet-search-suggestions.default_yandex_lr', '213'));
        $googleHl = (string) ($options['google_hl'] ?? config('cabinet-search-suggestions.default_google_hl', 'ru'));
        $googleGl = (string) ($options['google_gl'] ?? config('cabinet-search-suggestions.default_google_gl', 'ru'));
        $maxResults = (int) config('cabinet-search-suggestions.max_results', 5000);

        if ($seeds === [] || $engines === []) {
            return ['results' => [], 'cost' => 0, 'requests' => 0, 'truncated' => false];
        }

        $queue = [];
        foreach ($seeds as $seed) {
            foreach ($engines as $engine) {
                $queue[] = [
                    'seed' => $seed,
                    'engine' => $engine,
                    'level' => 1,
                ];
            }
        }

        $seenQueries = [];
        $seenSuggest = [];
        $rows = [];
        $requests = 0;
        $truncated = false;

        while ($queue !== []) {
            $job = array_shift($queue);
            $seed = $job['seed'];
            $engine = $job['engine'];
            $level = (int) $job['level'];
            $jobKey = $engine . '|' . mb_strtolower($seed, 'UTF-8');

            if (isset($seenQueries[$jobKey])) {
                continue;
            }
            $seenQueries[$jobKey] = true;

            $variants = $this->buildVariants($seed, $modes, $presets);

            foreach ($variants as $variant) {
                $suggests = $this->fetchSuggest($engine, $variant, $yandexLr, $googleHl, $googleGl);
                $requests++;

                foreach ($suggests as $suggest) {
                    $suggest = trim(preg_replace('/\s+/u', ' ', $suggest) ?? '');
                    if ($suggest === '' || $this->matchesStopWord($suggest, $stopWords)) {
                        continue;
                    }

                    $rowKey = $engine . '|' . mb_strtolower($suggest, 'UTF-8');
                    if (isset($seenSuggest[$rowKey])) {
                        continue;
                    }
                    $seenSuggest[$rowKey] = true;

                    $rows[] = [
                        'seed' => $seed,
                        'query' => $variant,
                        'suggest' => $suggest,
                        'engine' => $engine,
                        'level' => $level,
                        'words' => $this->wordCount($suggest),
                        'type' => $this->detectType($seed, $variant, $suggest),
                    ];

                    if (count($rows) >= $maxResults) {
                        $truncated = true;
                        break 3;
                    }

                    if ($level < $depth) {
                        $nextKey = $engine . '|' . mb_strtolower($suggest, 'UTF-8');
                        if (! isset($seenQueries[$nextKey])) {
                            $queue[] = [
                                'seed' => $suggest,
                                'engine' => $engine,
                                'level' => $level + 1,
                            ];
                        }
                    }
                }
            }
        }

        $cost = count($seeds) * count($engines);

        return [
            'results' => $rows,
            'cost' => $cost,
            'requests' => $requests,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param string[] $seeds
     * @return string[]
     */
    public function normalizeSeeds(array $seeds): array
    {
        $max = (int) config('cabinet-search-suggestions.max_seeds', 100);
        $out = [];
        foreach ($seeds as $seed) {
            $seed = trim(preg_replace('/\s+/u', ' ', (string) $seed) ?? '');
            if ($seed === '') {
                continue;
            }
            $key = mb_strtolower($seed, 'UTF-8');
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = $seed;
            if (count($out) >= $max) {
                break;
            }
        }

        return array_values($out);
    }

    public static function estimateCost(int $seedCount, int $engineCount): int
    {
        return max(0, $seedCount) * max(0, $engineCount);
    }

    /**
     * @param array<string, bool> $modes
     * @param array<string, bool> $presets
     * @return string[]
     */
    private function buildVariants(string $seed, array $modes, array $presets): array
    {
        $variants = [];

        if (! empty($modes['phrase'])) {
            $variants[] = $seed;
        }
        if (! empty($modes['space'])) {
            $variants[] = $seed . ' ';
        }
        if (! empty($modes['en'])) {
            foreach (self::EN_ALPHABET as $ch) {
                $variants[] = $seed . ' ' . $ch;
            }
        }
        if (! empty($modes['ru'])) {
            foreach (self::RU_ALPHABET as $ch) {
                $variants[] = $seed . ' ' . $ch;
            }
        }
        if (! empty($modes['digits'])) {
            foreach (self::DIGITS as $ch) {
                $variants[] = $seed . ' ' . $ch;
            }
        }

        $presetMap = config('cabinet-search-suggestions.presets', []);
        foreach (['local', 'shopping', 'questions', 'reviews'] as $preset) {
            if (empty($presets[$preset])) {
                continue;
            }
            foreach ($presetMap[$preset] ?? [] as $suffix) {
                $variants[] = $seed . ' ' . $suffix;
            }
        }

        if ($variants === []) {
            $variants[] = $seed;
        }

        return array_values(array_unique($variants));
    }

    /**
     * @return string[]
     */
    private function fetchSuggest(
        string $engine,
        string $query,
        string $yandexLr,
        string $googleHl,
        string $googleGl
    ): array {
        $pause = (int) config('cabinet-search-suggestions.request_pause_ms', 80);
        if ($pause > 0) {
            usleep($pause * 1000);
        }

        try {
            if ($engine === 'yandex') {
                return $this->fetchYandex($query, $yandexLr);
            }

            return $this->fetchGoogle($query, $googleHl, $googleGl);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return string[]
     */
    private function fetchYandex(string $query, string $lr): array
    {
        $url = 'https://suggest.yandex.ru/suggest-ya.cgi?' . http_build_query([
            'v' => '4',
            'part' => $query,
            'uil' => 'ru',
            'lr' => $lr,
            'n' => 15,
        ]);

        $body = $this->httpGet($url);
        if ($body === null) {
            return [];
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded) || ! isset($decoded[1]) || ! is_array($decoded[1])) {
            return [];
        }

        return $this->flattenSuggestList($decoded[1]);
    }

    /**
     * @return string[]
     */
    private function fetchGoogle(string $query, string $hl, string $gl): array
    {
        $url = 'https://suggestqueries.google.com/complete/search?' . http_build_query([
            'client' => 'firefox',
            'q' => $query,
            'hl' => $hl,
            'gl' => $gl,
        ]);

        $body = $this->httpGet($url);
        if ($body === null) {
            return [];
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded) || ! isset($decoded[1]) || ! is_array($decoded[1])) {
            return [];
        }

        return $this->flattenSuggestList($decoded[1]);
    }

    private function httpGet(string $url): ?string
    {
        $timeout = (int) config('cabinet-search-suggestions.request_timeout', 12);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TitloSuggest/1.0)',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }

            return (string) $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "User-Agent: Mozilla/5.0 (compatible; TitloSuggest/1.0)\r\nAccept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return $body === false ? null : (string) $body;
    }

    /**
     * @param array<int, mixed> $list
     * @return string[]
     */
    private function flattenSuggestList(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $out[] = $item;
                continue;
            }
            if (is_array($item) && isset($item[0]) && is_string($item[0])) {
                $out[] = $item[0];
            }
        }

        return $out;
    }

    /**
     * @param string[] $stopWords
     */
    private function matchesStopWord(string $suggest, array $stopWords): bool
    {
        if ($stopWords === []) {
            return false;
        }

        $hay = ' ' . mb_strtolower($suggest, 'UTF-8') . ' ';
        foreach ($stopWords as $word) {
            if ($word === '') {
                continue;
            }
            if (mb_strpos($hay, ' ' . $word . ' ', 0, 'UTF-8') !== false) {
                return true;
            }
            if (Str::contains($word, ' ') && mb_strpos(mb_strtolower($suggest, 'UTF-8'), $word, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $words
     * @return string[]
     */
    private function normalizeStopWords(array $words): array
    {
        $out = [];
        foreach ($words as $word) {
            $word = trim(mb_strtolower((string) $word, 'UTF-8'));
            if ($word !== '') {
                $out[$word] = $word;
            }
        }

        return array_values($out);
    }

    private function wordCount(string $text): int
    {
        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? count($parts) : 0;
    }

    private function detectType(string $seed, string $query, string $suggest): string
    {
        $seedLower = mb_strtolower($seed, 'UTF-8');
        $suggestLower = mb_strtolower($suggest, 'UTF-8');

        if ($suggestLower === $seedLower) {
            return 'точное';
        }

        if (mb_strpos($suggestLower, $seedLower, 0, 'UTF-8') === 0) {
            return 'дополнение';
        }

        if (mb_strpos($suggestLower, $seedLower, 0, 'UTF-8') !== false) {
            return 'вхождение';
        }

        $seedWords = preg_split('/\s+/u', $seedLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $suggestWords = preg_split('/\s+/u', $suggestLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($seedWords !== [] && count($seedWords) === count(array_intersect($seedWords, $suggestWords))) {
            return 'перестановка';
        }

        if (mb_strpos($query, $seed, 0, 'UTF-8') === false) {
            return 'в начале';
        }

        return 'подсказка';
    }
}
