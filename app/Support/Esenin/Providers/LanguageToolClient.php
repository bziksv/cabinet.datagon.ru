<?php

namespace App\Support\Esenin\Providers;

use App\Support\EseninTextCheckSettingsRegistry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class LanguageToolClient
{
    /** @var Client|null */
    private static $http;

    /**
     * @return array{ok: bool, marks: array<int, array<string, mixed>>, raw: array<string, mixed>, error: ?string}
     */
    public static function check(string $plain): array
    {
        $cfg = EseninTextCheckSettingsRegistry::provider('languagetool');
        if (empty($cfg['enabled'])) {
            return self::emptyResult('disabled');
        }

        $plain = trim($plain);
        if ($plain === '') {
            return self::emptyResult('empty_text');
        }

        try {
            $response = self::http($cfg)->post('/v2/check', [
                'form_params' => [
                    'text' => $plain,
                    'language' => $cfg['language'] ?? 'ru-RU',
                    'motherTongue' => $cfg['mother_tongue'] ?? 'ru-RU',
                    'enabledRules' => '',
                    'disabledRules' => '',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            if (! is_array($data)) {
                return self::emptyResult('invalid_response');
            }

            $marks = [];
            foreach ($data['matches'] ?? [] as $match) {
                $mark = self::matchToMark($plain, $match);
                if ($mark !== null) {
                    $marks[] = $mark;
                }
            }

            return [
                'ok' => true,
                'marks' => $marks,
                'raw' => $data,
                'error' => null,
            ];
        } catch (GuzzleException $e) {
            return self::emptyResult($e->getMessage());
        } catch (\Throwable $e) {
            return self::emptyResult($e->getMessage());
        }
    }

    public static function isAvailable(): bool
    {
        $cfg = EseninTextCheckSettingsRegistry::provider('languagetool');
        if (empty($cfg['enabled'])) {
            return false;
        }

        try {
            $response = self::http($cfg)->get('/v2/languages', ['http_errors' => false]);
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $match
     * @return array<string, mixed>|null
     */
    private static function matchToMark(string $plain, array $match): ?array
    {
        $offset = (int) ($match['offset'] ?? -1);
        $length = (int) ($match['length'] ?? 0);
        if ($offset < 0 || $length <= 0) {
            return null;
        }

        $plainLength = mb_strlen($plain, 'UTF-8');
        if ($offset + $length > $plainLength) {
            return null;
        }

        $categoryId = strtoupper((string) (($match['rule']['category']['id'] ?? '') ?: 'STYLE'));
        $block = self::mapCategoryToBlock($categoryId, (string) ($match['rule']['id'] ?? ''));
        $message = trim((string) ($match['message'] ?? ''));
        if ($message === '') {
            $message = (string) ($match['rule']['description'] ?? 'LanguageTool');
        }

        return [
            'offset' => $offset,
            'length' => $length,
            'block' => $block,
            'variant' => 'languagetool',
            'hint' => $message,
            'weight' => self::weightForCategory($categoryId),
            'source' => 'languagetool',
            'meta' => [
                'rule_id' => (string) ($match['rule']['id'] ?? ''),
                'category' => $categoryId,
            ],
        ];
    }

    private static function mapCategoryToBlock(string $categoryId, string $ruleId): string
    {
        if (strpos($ruleId, 'READABILITY') !== false || strpos($ruleId, 'TOO_LONG') !== false) {
            return 'readability';
        }

        switch ($categoryId) {
            case 'GRAMMAR':
            case 'TYPOS':
            case 'SPELLING':
            case 'PUNCTUATION':
            case 'TYPOGRAPHY':
                return 'style';
            case 'STYLE':
            case 'REDUNDANCY':
            case 'CASING':
                return 'style';
            default:
                return 'style';
        }
    }

    private static function weightForCategory(string $categoryId): int
    {
        switch ($categoryId) {
            case 'TYPOS':
            case 'SPELLING':
                return 2;
            case 'GRAMMAR':
            case 'PUNCTUATION':
                return 2;
            case 'STYLE':
            case 'REDUNDANCY':
                return 1;
            default:
                return 1;
        }
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function http(array $cfg): Client
    {
        if (self::$http instanceof Client) {
            return self::$http;
        }

        self::$http = new Client([
            'base_uri' => $cfg['url'] ?? 'http://127.0.0.1:8010',
            'timeout' => (float) ($cfg['timeout'] ?? 20),
            'connect_timeout' => 5,
            'http_errors' => true,
        ]);

        return self::$http;
    }

    /**
     * @return array{ok: bool, marks: array<int, array<string, mixed>>, raw: array<string, mixed>, error: ?string}
     */
    private static function emptyResult(?string $error): array
    {
        return [
            'ok' => false,
            'marks' => [],
            'raw' => [],
            'error' => $error,
        ];
    }
}
