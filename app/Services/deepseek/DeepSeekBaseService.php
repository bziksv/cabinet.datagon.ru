<?php

namespace App\Services\deepseek;

use GuzzleHttp\Client;

class DeepSeekBaseService
{
    protected $client;
    protected $apiKey;
    protected $lastUsage = [];

    public function __construct()
    {
        $this->apiKey = config('deepseek.token');
        $this->client = new Client([
            'base_uri' => 'https://api.deepseek.com',
            'timeout' => 240,
            'connect_timeout' => 20,
        ]);
    }

    public function chat(array $messages, string $model = 'deepseek-chat', array $options = [])
    {
        $attempts = 2;
        $last = null;
        for ($try = 1; $try <= $attempts; $try++) {
            try {
                $response = $this->client->post('/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => array_merge([
                        'model' => $model,
                        'messages' => $messages,
                    ], $options),
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $this->lastUsage = $data['usage'] ?? [];

                return $data;
            } catch (\Throwable $e) {
                $last = $e;
                if ($try >= $attempts || !$this->isRetryable($e)) {
                    throw $e;
                }
                sleep($try * 3);
            }
        }

        throw $last instanceof \Throwable ? $last : new \RuntimeException('deepseek request failed');
    }

    protected function isRetryable(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'cURL error 28') !== false
            || stripos($msg, 'cURL error 52') !== false
            || stripos($msg, 'cURL error 56') !== false
            || stripos($msg, 'timed out') !== false
        ) {
            return true;
        }
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $code = (int) $e->getResponse()->getStatusCode();

            return in_array($code, [429, 500, 502, 503, 504], true);
        }

        return false;
    }

    public function getLastUsageTokens(): int
    {
        return $this->lastUsage['total_tokens'] ?? 0;
    }

    public function getLastUsageDetails(): array
    {
        return $this->lastUsage;
    }

    public function request(string $prompt): string
    {
        $result = $this->chat([
            [
                'role' => 'user',
                'content' => $prompt,
            ]
        ]);

        return $result['choices'][0]['message']['content'] ?? '';
    }
}