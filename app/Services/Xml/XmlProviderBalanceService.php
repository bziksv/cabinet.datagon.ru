<?php

namespace App\Services\Xml;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class XmlProviderBalanceService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(bool $refresh = false): array
    {
        $ttl = (int) config('cabinet-xml-providers.balance_cache_seconds', 90);
        $cacheKey = 'cabinet.xml-providers.balances';

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $ttl, function () {
            $out = [];

            foreach (array_keys(config('cabinet-xml-providers.providers', [])) as $providerId) {
                $out[$providerId] = $this->fetchProvider($providerId);
            }

            return $out;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchProvider(string $providerId): array
    {
        $meta = config("cabinet-xml-providers.providers.{$providerId}");
        if (! is_array($meta)) {
            return $this->errorRow($providerId, 'unknown_provider');
        }

        $user = (string) config($meta['config_user'] ?? '', '');
        $key = (string) config($meta['config_key'] ?? '', '');

        if ($user === '' || $key === '') {
            return $this->errorRow($providerId, 'credentials_missing', [
                'user_masked' => $this->mask($user),
                'key_set' => $key !== '',
            ]);
        }

        $balanceConfig = $meta['balance'] ?? null;
        if (! is_array($balanceConfig) || empty($balanceConfig['url'])) {
            return $this->errorRow($providerId, 'balance_api_not_configured');
        }

        $url = str_replace(
            ['{user}', '{key}'],
            [rawurlencode($user), rawurlencode($key)],
            (string) $balanceConfig['url']
        );

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 12,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);
            if ($body === false || trim($body) === '') {
                return $this->errorRow($providerId, 'empty_response', [
                    'user_masked' => $this->mask($user),
                ]);
            }

            return $this->parseBalanceResponse($providerId, $balanceConfig, trim($body), $user);
        } catch (Throwable $e) {
            Log::debug('xml provider balance failed', [
                'provider' => $providerId,
                'message' => $e->getMessage(),
            ]);

            return $this->errorRow($providerId, 'request_failed', [
                'message' => $e->getMessage(),
                'user_masked' => $this->mask($user),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $balanceConfig
     * @return array<string, mixed>
     */
    protected function parseBalanceResponse(string $providerId, array $balanceConfig, string $body, string $user): array
    {
        $type = $balanceConfig['type'] ?? 'plain';

        if ($type === 'json') {
            $json = json_decode($body, true);
            if (! is_array($json)) {
                return $this->errorRow($providerId, 'invalid_json', [
                    'raw' => mb_substr($body, 0, 200),
                    'user_masked' => $this->mask($user),
                ]);
            }

            if (isset($json['status']) && (int) $json['status'] !== 0) {
                return $this->errorRow($providerId, 'api_error', [
                    'message' => (string) ($json['err_msg'] ?? $body),
                    'user_masked' => $this->mask($user),
                ]);
            }

            $field = (string) ($balanceConfig['balance_field'] ?? 'balance');
            $balance = $json[$field] ?? null;

            $extra = [];
            if ($providerId === 'xmlstock') {
                $extra = [
                    'outgo_day' => $json['outgo-day'] ?? $json['outgo_day'] ?? null,
                    'outgo_month' => $json['outgo-month'] ?? $json['outgo_month'] ?? null,
                    'limits' => $json['limits'] ?? null,
                ];
            }
            if ($providerId === 'xmlproxy') {
                $extra = [
                    'cur_cost' => $json['cur_cost'] ?? null,
                    'max_cost' => $json['max_cost'] ?? null,
                ];
            }

            return [
                'provider' => $providerId,
                'ok' => true,
                'balance' => $balance,
                'balance_formatted' => $this->formatMoney($balance),
                'raw' => $json,
                'extra' => $extra,
                'user_masked' => $this->mask($user),
                'fetched_at' => now()->toDateTimeString(),
            ];
        }

        if (! is_numeric($body)) {
            return $this->errorRow($providerId, 'unexpected_plain', [
                'raw' => mb_substr($body, 0, 200),
                'user_masked' => $this->mask($user),
            ]);
        }

        return [
            'provider' => $providerId,
            'ok' => true,
            'balance' => (float) $body,
            'balance_formatted' => $this->formatMoney((float) $body),
            'raw' => $body,
            'extra' => [],
            'user_masked' => $this->mask($user),
            'fetched_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function errorRow(string $providerId, string $code, array $context = []): array
    {
        return array_merge([
            'provider' => $providerId,
            'ok' => false,
            'code' => $code,
            'balance' => null,
            'balance_formatted' => null,
            'fetched_at' => now()->toDateTimeString(),
        ], $context);
    }

    protected function mask(string $value): string
    {
        if ($value === '') {
            return '—';
        }

        if (strlen($value) <= 4) {
            return '****';
        }

        return '…' . substr($value, -4);
    }

    /**
     * @param mixed $amount
     */
    protected function formatMoney($amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (! is_numeric($amount)) {
            return (string) $amount;
        }

        return number_format((float) $amount, 2, '.', ' ') . ' ₽';
    }
}
