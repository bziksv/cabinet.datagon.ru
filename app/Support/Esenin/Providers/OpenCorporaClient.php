<?php

namespace App\Support\Esenin\Providers;

use App\Support\EseninTextCheckSettingsRegistry;
use GuzzleHttp\Client;

final class OpenCorporaClient
{
    /**
     * @param array<int, string> $words
     * @return array{ok: bool, unknown: array<int, string>, error: ?string}
     */
    public static function findUnknownWords(array $words): array
    {
        $cfg = EseninTextCheckSettingsRegistry::provider('opencorpora');
        if (empty($cfg['enabled'])) {
            return ['ok' => false, 'unknown' => [], 'error' => 'disabled'];
        }

        $unique = [];
        foreach ($words as $word) {
            $word = mb_strtolower(trim((string) $word), 'UTF-8');
            if ($word === '' || mb_strlen($word, 'UTF-8') < 3) {
                continue;
            }
            if (! preg_match('/^[\p{L}\-]+$/u', $word)) {
                continue;
            }
            $unique[$word] = true;
            if (count($unique) >= 40) {
                break;
            }
        }

        if ($unique === []) {
            return ['ok' => true, 'unknown' => [], 'error' => null];
        }

        try {
            $client = new Client([
                'timeout' => (float) ($cfg['timeout'] ?? 10),
                'connect_timeout' => 5,
                'http_errors' => false,
            ]);

            $response = $client->get((string) ($cfg['url'] ?? 'https://opencorpora.org/api.php'), [
                'query' => [
                    'action' => 'morph',
                    'ignore-dict' => 0,
                    'words' => implode(',', array_keys($unique)),
                ],
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return ['ok' => false, 'unknown' => [], 'error' => 'http_' . $response->getStatusCode()];
            }

            $data = json_decode((string) $response->getBody(), true);
            if (! is_array($data)) {
                return ['ok' => false, 'unknown' => [], 'error' => 'invalid_json'];
            }

            $unknown = [];
            foreach ($data as $word => $parses) {
                if (! is_array($parses) || $parses === []) {
                    $unknown[] = (string) $word;
                }
            }

            return ['ok' => true, 'unknown' => $unknown, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'unknown' => [], 'error' => $e->getMessage()];
        }
    }
}
