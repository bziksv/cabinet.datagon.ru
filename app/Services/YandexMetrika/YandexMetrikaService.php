<?php

namespace App\Services\YandexMetrika;

use App\YandexMetrikaDomainCounter;
use App\YandexMetrikaUserToken;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class YandexMetrikaService
{
    public function isConfigured(): bool
    {
        return (string) config('cabinet-yandex-metrika.client_id') !== ''
            && (string) config('cabinet-yandex-metrika.client_secret') !== '';
    }

    public function redirectUri(): string
    {
        $configured = config('cabinet-yandex-metrika.redirect_uri');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route('yandex-metrika.callback');
    }

    public function isConnected(int $userId): bool
    {
        if ($userId < 1 || !YandexMetrikaUserToken::tableReady()) {
            return false;
        }

        $row = YandexMetrikaUserToken::query()->find($userId);

        return $row && (string) $row->access_token !== '';
    }

    /**
     * @param array{domain?:string,return?:string} $payload
     */
    public function buildAuthorizeUrl(int $userId, array $payload = []): string
    {
        $state = $this->encodeState([
            'uid' => $userId,
            'domain' => (string) ($payload['domain'] ?? ''),
            'return' => (string) ($payload['return'] ?? route('home.variant4')),
            'ts' => time(),
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('cabinet-yandex-metrika.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => config('cabinet-yandex-metrika.scope', 'metrika:read'),
            'force_confirm' => 'yes',
            'state' => $state,
        ]);

        return rtrim((string) config('cabinet-yandex-metrika.authorize_url'), '?') . '?' . $query;
    }

    /**
     * @return array{uid:int,domain:string,return:string,ts:int}|null
     */
    public function decodeState(string $state): ?array
    {
        try {
            $raw = base64_decode(strtr($state, '-_', '+/'), true);
            if ($raw === false) {
                return null;
            }
            $payload = json_decode($raw, true);
            if (!is_array($payload) || empty($payload['sig']) || empty($payload['data'])) {
                return null;
            }
            $expected = hash_hmac('sha256', $payload['data'], (string) config('app.key'));
            if (!hash_equals($expected, (string) $payload['sig'])) {
                return null;
            }
            $data = json_decode($payload['data'], true);
            if (!is_array($data) || (int) ($data['uid'] ?? 0) < 1) {
                return null;
            }
            if (!empty($data['ts']) && (time() - (int) $data['ts']) > 3600) {
                return null;
            }

            return [
                'uid' => (int) $data['uid'],
                'domain' => (string) ($data['domain'] ?? ''),
                'return' => (string) ($data['return'] ?? route('home.variant4')),
                'ts' => (int) ($data['ts'] ?? 0),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeState(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', (string) $json, (string) config('app.key'));
        $packed = json_encode(['data' => $json, 'sig' => $sig], JSON_UNESCAPED_UNICODE);

        return rtrim(strtr(base64_encode((string) $packed), '+/', '-_'), '=');
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function handleCallback(int $userId, string $code): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => __('Yandex Metrika is not configured')];
        }

        try {
            $token = $this->requestToken([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('cabinet-yandex-metrika.client_id'),
                'client_secret' => config('cabinet-yandex-metrika.client_secret'),
                'redirect_uri' => $this->redirectUri(),
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex metrika token exchange failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => __('Yandex Metrika authorization failed')];
        }

        $this->storeToken($userId, $token);

        return ['ok' => true];
    }

    public function disconnect(int $userId): void
    {
        if (!YandexMetrikaUserToken::tableReady()) {
            return;
        }
        YandexMetrikaUserToken::query()->where('user_id', $userId)->delete();
    }

    /**
     * @return array<int, array{id:int,name:string,site:string,code_status:string}>
     */
    public function listCounters(int $userId): array
    {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null) {
            return [];
        }

        $client = $this->httpClient();
        $response = $client->get('management/v1/counters', [
            'headers' => [
                'Authorization' => 'OAuth ' . $accessToken,
                'Accept' => 'application/json',
            ],
            'query' => [
                'per_page' => 1000,
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $rows = is_array($body['counters'] ?? null) ? $body['counters'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $site = '';
            if (!empty($row['site'])) {
                $site = (string) $row['site'];
            } elseif (!empty($row['site2']['site'])) {
                $site = (string) $row['site2']['site'];
            }
            $out[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ('#' . $id)),
                'site' => $site,
                'code_status' => (string) ($row['code_status'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{ok:bool,message?:string,binding?:array<string,mixed>}
     */
    public function bindCounter(int $userId, string $domain, int $counterId): array
    {
        if (!$this->isConnected($userId)) {
            return ['ok' => false, 'message' => __('Connect Yandex Metrika first')];
        }

        $counters = $this->listCounters($userId);
        $found = null;
        foreach ($counters as $counter) {
            if ((int) $counter['id'] === $counterId) {
                $found = $counter;
                break;
            }
        }
        if ($found === null) {
            // Разрешаем привязку по id даже если список временно недоступен.
            $found = ['id' => $counterId, 'name' => '#' . $counterId, 'site' => ''];
        }

        $row = YandexMetrikaDomainCounter::bind(
            $userId,
            $domain,
            (int) $found['id'],
            (string) ($found['name'] ?? null),
            (string) ($found['site'] ?? null)
        );
        if ($row === null) {
            return ['ok' => false, 'message' => __('Invalid domain')];
        }

        try {
            app(\App\Services\SeoChecklist\SeoChecklistService::class)
                ->syncMetrikaForDomain($userId, $domain);
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'ok' => true,
            'binding' => [
                'domain' => $row->domain,
                'counter_id' => (int) $row->counter_id,
                'counter_name' => (string) $row->counter_name,
                'counter_site' => (string) $row->counter_site,
            ],
        ];
    }

    public function unbindCounter(int $userId, string $domain): bool
    {
        return YandexMetrikaDomainCounter::unbind($userId, $domain);
    }

    private function validAccessToken(int $userId): ?string
    {
        if (!YandexMetrikaUserToken::tableReady()) {
            return null;
        }

        /** @var YandexMetrikaUserToken|null $row */
        $row = YandexMetrikaUserToken::query()->find($userId);
        if (!$row || (string) $row->access_token === '') {
            return null;
        }

        if ($row->isExpired() && (string) $row->refresh_token !== '') {
            try {
                $token = $this->requestToken([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $row->refresh_token,
                    'client_id' => config('cabinet-yandex-metrika.client_id'),
                    'client_secret' => config('cabinet-yandex-metrika.client_secret'),
                ]);
                $this->storeToken($userId, $token);
                $row = YandexMetrikaUserToken::query()->find($userId);
            } catch (Throwable $e) {
                Log::warning('yandex metrika refresh failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

                return null;
            }
        }

        return $row ? (string) $row->access_token : null;
    }

    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    private function requestToken(array $form): array
    {
        $client = new Client([
            'timeout' => (int) config('cabinet-yandex-metrika.timeout', 20),
            'http_errors' => false,
        ]);
        $response = $client->post((string) config('cabinet-yandex-metrika.token_url'), [
            'form_params' => $form,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if ($status >= 400 || !is_array($body) || empty($body['access_token'])) {
            throw new \RuntimeException('Token response invalid: HTTP ' . $status);
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $token
     */
    private function storeToken(int $userId, array $token): void
    {
        if (!YandexMetrikaUserToken::tableReady()) {
            throw new \RuntimeException('yandex_metrika_user_tokens table missing');
        }

        $expiresIn = (int) ($token['expires_in'] ?? 0);
        YandexMetrikaUserToken::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token' => (string) $token['access_token'],
                'refresh_token' => isset($token['refresh_token']) ? (string) $token['refresh_token'] : null,
                'expires_at' => $expiresIn > 0 ? Carbon::now()->addSeconds($expiresIn - 60) : null,
                'yandex_login' => isset($token['login']) ? (string) $token['login'] : null,
            ]
        );
    }

    private function httpClient(): Client
    {
        return new Client([
            'base_uri' => rtrim((string) config('cabinet-yandex-metrika.api_base'), '/') . '/',
            'timeout' => (int) config('cabinet-yandex-metrika.timeout', 20),
            'http_errors' => true,
        ]);
    }
}
