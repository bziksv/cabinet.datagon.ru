<?php

namespace App\Classes\Xml;

use Illuminate\Support\Facades\Log;

class RiverFacade
{
    protected $user;

    protected $key;

    protected $region;

    protected $query;

    protected $countAttempts;

    public function __construct($region)
    {
        $this->user = config('xmlriver.user');
        $this->key = config('xmlriver.key');
        $this->region = $region;
        $this->countAttempts = 3;
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setRegions($region)
    {
        $this->region = $region;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @param bool $searchInItems Для базовой частоты — подставить нормализованную фразу из popular, если есть.
     * @return array{number: int, phrase: string}
     */
    public function riverRequest(bool $searchInItems = true): array
    {
        $query = (string) $this->getQuery();
        $empty = [
            'number' => 0,
            'phrase' => $query,
        ];

        try {
            $riverResponse = null;

            for ($attempt = 1; $attempt <= $this->countAttempts; $attempt++) {
                $riverResponse = $this->fetchNewWordstatResponse($query);

                if ($this->hasWordstatError($riverResponse)) {
                    if ($attempt >= $this->countAttempts) {
                        Log::debug('river request error response', [
                            'query' => $query,
                            'response' => $riverResponse,
                        ]);

                        return $empty;
                    }

                    usleep(400000 * $attempt);
                    continue;
                }

                if (isset($riverResponse['totalValue'])) {
                    break;
                }

                if ($attempt >= $this->countAttempts) {
                    Log::debug('river request missing totalValue', [
                        'query' => $query,
                        'response' => $riverResponse,
                    ]);

                    return $empty;
                }

                usleep(400000 * $attempt);
            }

            $phrase = $query;
            if ($searchInItems) {
                $phrase = $this->resolvePopularPhrase($riverResponse, $query) ?? $query;
            }

            return [
                'number' => (int) $riverResponse['totalValue'],
                'phrase' => $phrase,
            ];
        } catch (\Throwable $e) {
            Log::debug('river request error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'query' => $query,
            ]);

            return $empty;
        }
    }

  /**
     * @return array<string, mixed>|null
     */
    protected function fetchNewWordstatResponse(string $query): ?array
    {
        $url = $this->buildNewWordstatUrl($query);
        $raw = @file_get_contents($url);

        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function buildNewWordstatUrl(string $query): string
    {
        $base = rtrim((string) config('xmlriver.url', 'https://xmlriver.com/wordstat/new/json'), '/');

        if (strpos($base, '/wordstat/new/json') === false) {
            $base = 'https://xmlriver.com/wordstat/new/json';
        }

        return $base . '?' . http_build_query([
            'user' => $this->user,
            'key' => $this->key,
            'regions' => $this->region,
            'pagetype' => 'history',
            'query' => $query,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed>|null $response
     */
    protected function hasWordstatError(?array $response): bool
    {
        if ($response === null) {
            return true;
        }

        if (!empty($response['error'])) {
            return true;
        }

        return isset($response['code']) && (int) $response['code'] !== 0;
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function resolvePopularPhrase(array $response, string $query): ?string
    {
        $popular = $response['table']['tableData']['popular'] ?? null;
        if (!is_array($popular)) {
            return null;
        }

        $queryLower = mb_strtolower($query);
        foreach ($popular as $item) {
            if (!is_array($item) || empty($item['text'])) {
                continue;
            }
            if (mb_strtolower((string) $item['text']) === $queryLower) {
                return (string) $item['text'];
            }
        }

        return null;
    }

    /**
     * @param string $string
     * @return int
     */
    protected function removeExtraSymbols(string $string): int
    {
        $number = preg_replace('/[^0-9]/', '', $string);
        $number = htmlentities($number);

        return (int) str_replace('&nbsp;', '', $number);
    }
}
