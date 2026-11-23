<?php

namespace App\Services\Wordstat;

class WordstatClient
{
    private string $endpoint;
    private string $user;
    private string $key;
    /** @var callable */
    private $transport;

    public function __construct(string $endpoint, string $user, string $key, ?callable $transport = null)
    {
        $this->endpoint = $endpoint;
        $this->user = $user;
        $this->key = $key;
        $this->transport = $transport ?? [$this, 'httpGet'];
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) config('cabinet-wordstat-keywords.endpoint'),
            (string) config('xmlstock.user'),
            (string) config('xmlstock.key')
        );
    }

    public function buildUrl(string $query, array $regionIds, string $device, int $limit): string
    {
        return $this->endpoint . '?' . http_build_query([
            'user' => $this->user,
            'key' => $this->key,
            'query' => $query,
            'pagetype' => 'words',
            'groupby' => max(1, min(2000, $limit)),
            'regions' => implode(',', $regionIds),
            'device' => $device,
        ]);
    }

    public function words(string $query, array $regionIds, string $device, int $limit): WordstatResult
    {
        $body = ($this->transport)($this->buildUrl($query, $regionIds, $device, $limit));
        if ($body === null || $body === '') {
            throw WordstatException::retryable('empty response');
        }

        $json = json_decode($body, true);
        if (! is_array($json)) {
            throw WordstatException::retryable('invalid json');
        }

        if (isset($json['error'])) {
            $code = (int) ($json['error']['code'] ?? 0);
            $message = (string) ($json['error']['message'] ?? 'error');
            if ($code === -34 || preg_match('/ключ|баланс|средств|заблокир/ui', $message)) {
                throw WordstatException::fatal($message);
            }
            throw WordstatException::retryable($message);
        }

        $result = new WordstatResult();
        $result->totalCount = (int) ($json['totalCount'] ?? 0);
        $result->left = self::rows($json['results'] ?? []);
        $result->right = self::rows($json['associations'] ?? []);

        return $result;
    }

    private static function rows($items): array
    {
        $out = [];
        foreach ((array) $items as $item) {
            $phrase = trim((string) ($item['phrase'] ?? ''));
            if ($phrase !== '') {
                $out[] = ['phrase' => $phrase, 'count' => (int) ($item['count'] ?? 0)];
            }
        }

        return $out;
    }

    public function httpGet(string $url): ?string
    {
        $context = stream_context_create([
            'http' => ['timeout' => (int) config('cabinet-wordstat-keywords.http_timeout', 30), 'ignore_errors' => true],
        ]);
        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : $body;
    }
}
