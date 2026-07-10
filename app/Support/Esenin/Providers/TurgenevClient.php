<?php

namespace App\Support\Esenin\Providers;

use App\Support\EseninTextCheckSettingsRegistry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class TurgenevClient
{
    /**
     * @param array<string, mixed> $options
     * @return array{ok: bool, data: array<string, mixed>, error: ?string}
     */
    public static function checkText(string $text, array $options = []): array
    {
        $cfg = EseninTextCheckSettingsRegistry::provider('turgenev');
        if (empty($cfg['enabled']) || trim((string) ($cfg['key'] ?? '')) === '') {
            return ['ok' => false, 'data' => [], 'error' => 'disabled'];
        }

        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'data' => [], 'error' => 'empty_text'];
        }

        $params = [
            'api' => (string) ($options['api'] ?? $cfg['api'] ?? 'risk'),
            'key' => (string) $cfg['key'],
            'more' => (int) ($options['more'] ?? $cfg['more'] ?? 1),
            'text' => $text,
        ];

        if (! empty($options['url'])) {
            unset($params['text']);
            $params['url'] = (string) $options['url'];
            if (! empty($options['tbclass'])) {
                $params['tbclass'] = (string) $options['tbclass'];
            }
        }

        try {
            $response = (new Client([
                'timeout' => (float) ($cfg['timeout'] ?? 30),
                'connect_timeout' => 10,
                'http_errors' => false,
            ]))->post((string) ($cfg['url'] ?? 'https://turgenev.ashmanov.com/'), [
                'form_params' => $params,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            if (! is_array($data)) {
                return ['ok' => false, 'data' => [], 'error' => 'invalid_json'];
            }

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return [
                    'ok' => false,
                    'data' => $data,
                    'error' => (string) ($data['error'] ?? ('http_' . $response->getStatusCode())),
                ];
            }

            if (! empty($data['error'])) {
                return ['ok' => false, 'data' => $data, 'error' => (string) $data['error']];
            }

            return ['ok' => true, 'data' => self::normalizeResponse($data), 'error' => null];
        } catch (GuzzleException $e) {
            return ['ok' => false, 'data' => [], 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, balance: ?float, error: ?string}
     */
    public static function balance(): array
    {
        $cfg = EseninTextCheckSettingsRegistry::provider('turgenev');
        if (empty($cfg['enabled']) || trim((string) ($cfg['key'] ?? '')) === '') {
            return ['ok' => false, 'balance' => null, 'error' => 'disabled'];
        }

        try {
            $response = (new Client(['timeout' => 15, 'http_errors' => false]))
                ->post((string) ($cfg['url'] ?? 'https://turgenev.ashmanov.com/'), [
                    'form_params' => [
                        'api' => 'balance',
                        'key' => (string) $cfg['key'],
                    ],
                ]);

            $data = json_decode((string) $response->getBody(), true);
            if (! is_array($data)) {
                return ['ok' => false, 'balance' => null, 'error' => 'invalid_json'];
            }

            return [
                'ok' => true,
                'balance' => isset($data['balance']) ? (float) $data['balance'] : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'balance' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalizeResponse(array $data): array
    {
        $reportBase = 'https://turgenev.ashmanov.com/?t=';
        if (! empty($data['link']) && empty($data['report_url'])) {
            $data['report_url'] = $reportBase . ltrim((string) $data['link'], '/');
        }

        $details = [];
        foreach ($data['details'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $block = (string) ($row['block'] ?? '');
            if ($block === '' && isset($row[0])) {
                continue;
            }

            $link = (string) ($row['link'] ?? '');
            if ($link !== '' && strpos($link, 'http') !== 0) {
                $link = $reportBase . ltrim($link, '/');
            }

            $details[] = [
                'block' => $block,
                'sum' => (int) ($row['sum'] ?? 0),
                'params' => is_array($row['params'] ?? null) ? $row['params'] : [],
                'link' => $link,
            ];
        }

        $data['details'] = $details;

        return $data;
    }
}
