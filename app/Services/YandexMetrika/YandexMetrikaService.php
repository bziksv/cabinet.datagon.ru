<?php

namespace App\Services\YandexMetrika;

use App\YandexMetrikaDomainCounter;
use App\YandexMetrikaUserToken;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
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
            'return' => (string) ($payload['return'] ?? route('home')),
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
                'return' => (string) ($data['return'] ?? route('home')),
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

    /**
     * Посетители (ym:s:users): вчера/7д/30д кэшируются до конца суток,
     * «сегодня» — отдельно на visitors_cache_ttl (по умолчанию 1 час).
     *
     * @return array{
     *     today:?int,
     *     yesterday:?int,
     *     sum_7:?int,
     *     avg_7:?int,
     *     sum_30:?int,
     *     avg_30:?int,
     *     meta:array{as_of:?string,as_of_human:?string,next_today_at:?string,next_today_human:?string,history_as_of:?string,today_as_of:?string}
     * }|null
     */
    public function visitorsSummaryForCounter(int $userId, int $counterId): ?array
    {
        if ($userId < 1 || $counterId < 1) {
            return null;
        }

        $tz = config('app.timezone') ?: 'UTC';
        $now = Carbon::now($tz);
        $today = $now->format('Y-m-d');
        $yesterday = $now->copy()->subDay()->format('Y-m-d');
        $todayTtl = max(60, (int) config('cabinet-yandex-metrika.visitors_cache_ttl', 3600));
        $historyTtl = max(60, $now->copy()->endOfDay()->getTimestamp() - $now->getTimestamp());

        $histKey = sprintf('ym:visitors:hist:v3:%d:%d:%s', $userId, $counterId, $today);
        $todayKey = sprintf('ym:visitors:today:v3:%d:%d:%s', $userId, $counterId, $today);

        /** @var array{by_date:array<string,int>,fetched_at:string}|null|false $history */
        $history = Cache::get($histKey);
        /** @var array{users:int,fetched_at:string}|null|false $todayCache */
        $todayCache = Cache::get($todayKey);

        if ($history === false && $todayCache === false) {
            return null;
        }

        $needHistory = !is_array($history);
        $needToday = !is_array($todayCache);

        if ($needHistory || $needToday) {
            $accessToken = $this->validAccessToken($userId);
            if ($accessToken === null) {
                if (!is_array($history) && !is_array($todayCache)) {
                    return null;
                }
                $needHistory = false;
                $needToday = false;
            } else {
                if ($needHistory && $needToday) {
                    $byDate = $this->fetchUsersByDate($accessToken, $counterId, '30daysAgo', 'today', $userId);
                    if ($byDate === null) {
                        Cache::put($histKey, false, 120);
                        Cache::put($todayKey, false, 120);

                        return null;
                    }
                    $todayUsers = (int) ($byDate[$today] ?? 0);
                    unset($byDate[$today]);
                    $fetchedAt = $now->toIso8601String();
                    $history = ['by_date' => $byDate, 'fetched_at' => $fetchedAt];
                    $todayCache = ['users' => $todayUsers, 'fetched_at' => $fetchedAt];
                    Cache::put($histKey, $history, $historyTtl);
                    Cache::put($todayKey, $todayCache, $todayTtl);
                } elseif ($needHistory) {
                    $byDate = $this->fetchUsersByDate($accessToken, $counterId, '30daysAgo', 'yesterday', $userId);
                    if ($byDate === null) {
                        Cache::put($histKey, false, 120);
                        if (!is_array($todayCache)) {
                            return null;
                        }
                    } else {
                        unset($byDate[$today]);
                        $history = ['by_date' => $byDate, 'fetched_at' => $now->toIso8601String()];
                        Cache::put($histKey, $history, $historyTtl);
                    }
                } elseif ($needToday) {
                    $byDate = $this->fetchUsersByDate($accessToken, $counterId, 'today', 'today', $userId);
                    if ($byDate === null) {
                        Cache::put($todayKey, false, 120);
                        if (!is_array($history)) {
                            return null;
                        }
                    } else {
                        $todayCache = [
                            'users' => (int) ($byDate[$today] ?? 0),
                            'fetched_at' => $now->toIso8601String(),
                        ];
                        Cache::put($todayKey, $todayCache, $todayTtl);
                    }
                }
            }
        }

        if (!is_array($history) && !is_array($todayCache)) {
            return null;
        }

        $byDate = is_array($history) ? ($history['by_date'] ?? []) : [];
        if (!is_array($byDate)) {
            $byDate = [];
        }
        $todayUsers = is_array($todayCache) ? (int) ($todayCache['users'] ?? 0) : null;
        if ($todayUsers !== null) {
            $byDate[$today] = $todayUsers;
        }

        $sumDays = static function (int $days) use ($byDate, $tz): array {
            $sum = 0;
            for ($i = 0; $i < $days; $i++) {
                $key = Carbon::now($tz)->subDays($i)->format('Y-m-d');
                $sum += (int) ($byDate[$key] ?? 0);
            }

            return [$sum, $days > 0 ? (int) round($sum / $days) : null];
        };

        [$sum7, $avg7] = $sumDays(7);
        [$sum30, $avg30] = $sumDays(30);

        $historyAsOf = is_array($history) ? (string) ($history['fetched_at'] ?? '') : '';
        $todayAsOf = is_array($todayCache) ? (string) ($todayCache['fetched_at'] ?? '') : '';
        $asOf = $todayAsOf !== '' ? $todayAsOf : $historyAsOf;
        $asOfCarbon = $asOf !== '' ? Carbon::parse($asOf)->timezone($tz) : null;
        $nextToday = $asOfCarbon ? $asOfCarbon->copy()->addSeconds($todayTtl) : null;
        if ($nextToday && $nextToday->lt($now)) {
            $nextToday = $now->copy();
        }

        return [
            'today' => $todayUsers,
            'yesterday' => array_key_exists($yesterday, $byDate) ? (int) $byDate[$yesterday] : (is_array($history) ? 0 : null),
            'sum_7' => is_array($history) || $todayUsers !== null ? $sum7 : null,
            'avg_7' => is_array($history) || $todayUsers !== null ? $avg7 : null,
            'sum_30' => is_array($history) || $todayUsers !== null ? $sum30 : null,
            'avg_30' => is_array($history) || $todayUsers !== null ? $avg30 : null,
            'meta' => [
                'as_of' => $asOf !== '' ? $asOf : null,
                'as_of_human' => $asOfCarbon ? $asOfCarbon->format('d.m.Y H:i') : null,
                'next_today_at' => $nextToday ? $nextToday->toIso8601String() : null,
                'next_today_human' => $nextToday ? $nextToday->format('d.m.Y H:i') : null,
                'history_as_of' => $historyAsOf !== '' ? $historyAsOf : null,
                'today_as_of' => $todayAsOf !== '' ? $todayAsOf : null,
            ],
        ];
    }

    /**
     * @param string $domain Уже нормализованный домен (как в yandex_metrika_domain_counters.domain)
     * @return array{today:?int,yesterday:?int,sum_7:?int,avg_7:?int,sum_30:?int,avg_30:?int,meta:array<string,mixed>}|null
     */
    public function visitorsSummaryForDomain(int $userId, string $domain): ?array
    {
        $domain = strtolower(trim($domain));
        if ($userId < 1 || $domain === '') {
            return null;
        }

        $map = $this->visitorsSummariesForDomains($userId, [$domain]);

        return $map[$domain] ?? null;
    }

    /**
     * Пакетно: все домены с привязкой Метрики, с тем же кэшем (история — сутки, сегодня — 1 час).
     *
     * @param list<string> $domains
     * @return array<string, array{today:?int,yesterday:?int,sum_7:?int,avg_7:?int,sum_30:?int,avg_30:?int,meta:array<string,mixed>}>
     */
    public function visitorsSummariesForDomains(int $userId, array $domains): array
    {
        if ($userId < 1 || $domains === [] || !YandexMetrikaDomainCounter::tableReady()) {
            return [];
        }

        $normalized = [];
        foreach ($domains as $domain) {
            $d = strtolower(trim((string) $domain));
            if ($d !== '') {
                $normalized[$d] = true;
            }
        }
        if ($normalized === []) {
            return [];
        }

        $bindings = YandexMetrikaDomainCounter::query()
            ->where('user_id', $userId)
            ->whereIn('domain', array_keys($normalized))
            ->get(['domain', 'counter_id']);

        if ($bindings->isEmpty()) {
            return [];
        }

        $tz = config('app.timezone') ?: 'UTC';
        $now = Carbon::now($tz);
        $today = $now->format('Y-m-d');
        $todayTtl = max(60, (int) config('cabinet-yandex-metrika.visitors_cache_ttl', 3600));
        $historyTtl = max(60, $now->copy()->endOfDay()->getTimestamp() - $now->getTimestamp());

        /** @var array<int, string> $counterToDomain */
        $counterToDomain = [];
        $needBoth = [];
        $needHist = [];
        $needToday = [];
        /** @var array<int, array{by_date:array<string,int>,fetched_at:string}|false|null> $histCache */
        $histCache = [];
        /** @var array<int, array{users:int,fetched_at:string}|false|null> $todayCache */
        $todayCache = [];

        foreach ($bindings as $binding) {
            $counterId = (int) $binding->counter_id;
            $domain = (string) $binding->domain;
            if ($counterId < 1 || $domain === '') {
                continue;
            }
            $counterToDomain[$counterId] = $domain;
            $histKey = sprintf('ym:visitors:hist:v3:%d:%d:%s', $userId, $counterId, $today);
            $todayKey = sprintf('ym:visitors:today:v3:%d:%d:%s', $userId, $counterId, $today);
            $histCache[$counterId] = Cache::get($histKey);
            $todayCache[$counterId] = Cache::get($todayKey);
            $hasHist = is_array($histCache[$counterId]);
            $hasToday = is_array($todayCache[$counterId]);
            if (!$hasHist && !$hasToday) {
                $needBoth[$counterId] = true;
            } elseif (!$hasHist) {
                $needHist[$counterId] = true;
            } elseif (!$hasToday) {
                $needToday[$counterId] = true;
            }
        }

        if ($needBoth !== [] || $needHist !== [] || $needToday !== []) {
            $accessToken = $this->validAccessToken($userId);
            if ($accessToken !== null) {
                $fetchedAt = $now->toIso8601String();

                if ($needBoth !== []) {
                    $multi = $this->fetchUsersByDateMulti($accessToken, array_keys($needBoth), '30daysAgo', 'today', $userId);
                    foreach (array_keys($needBoth) as $counterId) {
                        $histKey = sprintf('ym:visitors:hist:v3:%d:%d:%s', $userId, $counterId, $today);
                        $todayKey = sprintf('ym:visitors:today:v3:%d:%d:%s', $userId, $counterId, $today);
                        if ($multi === null || !isset($multi[$counterId])) {
                            Cache::put($histKey, false, 120);
                            Cache::put($todayKey, false, 120);
                            $histCache[$counterId] = false;
                            $todayCache[$counterId] = false;
                            continue;
                        }
                        $byDate = $multi[$counterId];
                        $todayUsers = (int) ($byDate[$today] ?? 0);
                        unset($byDate[$today]);
                        $histCache[$counterId] = ['by_date' => $byDate, 'fetched_at' => $fetchedAt];
                        $todayCache[$counterId] = ['users' => $todayUsers, 'fetched_at' => $fetchedAt];
                        Cache::put($histKey, $histCache[$counterId], $historyTtl);
                        Cache::put($todayKey, $todayCache[$counterId], $todayTtl);
                    }
                }

                if ($needHist !== []) {
                    $multi = $this->fetchUsersByDateMulti($accessToken, array_keys($needHist), '30daysAgo', 'yesterday', $userId);
                    foreach (array_keys($needHist) as $counterId) {
                        $histKey = sprintf('ym:visitors:hist:v3:%d:%d:%s', $userId, $counterId, $today);
                        if ($multi === null || !isset($multi[$counterId])) {
                            Cache::put($histKey, false, 120);
                            $histCache[$counterId] = false;
                            continue;
                        }
                        $byDate = $multi[$counterId];
                        unset($byDate[$today]);
                        $histCache[$counterId] = ['by_date' => $byDate, 'fetched_at' => $fetchedAt];
                        Cache::put($histKey, $histCache[$counterId], $historyTtl);
                    }
                }

                if ($needToday !== []) {
                    $multi = $this->fetchUsersByDateMulti($accessToken, array_keys($needToday), 'today', 'today', $userId);
                    foreach (array_keys($needToday) as $counterId) {
                        $todayKey = sprintf('ym:visitors:today:v3:%d:%d:%s', $userId, $counterId, $today);
                        if ($multi === null || !isset($multi[$counterId])) {
                            Cache::put($todayKey, false, 120);
                            $todayCache[$counterId] = false;
                            continue;
                        }
                        $todayCache[$counterId] = [
                            'users' => (int) ($multi[$counterId][$today] ?? 0),
                            'fetched_at' => $fetchedAt,
                        ];
                        Cache::put($todayKey, $todayCache[$counterId], $todayTtl);
                    }
                }
            }
        }

        $out = [];
        foreach ($counterToDomain as $counterId => $domain) {
            $summary = $this->buildVisitorsSummaryFromCaches(
                is_array($histCache[$counterId] ?? null) ? $histCache[$counterId] : null,
                is_array($todayCache[$counterId] ?? null) ? $todayCache[$counterId] : null,
                $tz,
                $todayTtl
            );
            if ($summary !== null) {
                $out[$domain] = $summary;
            }
        }

        return $out;
    }

    /**
     * @param array{by_date:array<string,int>,fetched_at:string}|null $history
     * @param array{users:int,fetched_at:string}|null $todayCache
     * @return array{today:?int,yesterday:?int,sum_7:?int,avg_7:?int,sum_30:?int,avg_30:?int,meta:array<string,mixed>}|null
     */
    private function buildVisitorsSummaryFromCaches(?array $history, ?array $todayCache, string $tz, int $todayTtl): ?array
    {
        if ($history === null && $todayCache === null) {
            return null;
        }

        $now = Carbon::now($tz);
        $today = $now->format('Y-m-d');
        $yesterday = $now->copy()->subDay()->format('Y-m-d');

        $byDate = is_array($history) ? ($history['by_date'] ?? []) : [];
        if (!is_array($byDate)) {
            $byDate = [];
        }
        $todayUsers = is_array($todayCache) ? (int) ($todayCache['users'] ?? 0) : null;
        if ($todayUsers !== null) {
            $byDate[$today] = $todayUsers;
        }

        $sumDays = static function (int $days) use ($byDate, $tz): array {
            $sum = 0;
            for ($i = 0; $i < $days; $i++) {
                $key = Carbon::now($tz)->subDays($i)->format('Y-m-d');
                $sum += (int) ($byDate[$key] ?? 0);
            }

            return [$sum, $days > 0 ? (int) round($sum / $days) : null];
        };

        [$sum7, $avg7] = $sumDays(7);
        [$sum30, $avg30] = $sumDays(30);

        $historyAsOf = is_array($history) ? (string) ($history['fetched_at'] ?? '') : '';
        $todayAsOf = is_array($todayCache) ? (string) ($todayCache['fetched_at'] ?? '') : '';
        $asOf = $todayAsOf !== '' ? $todayAsOf : $historyAsOf;
        $asOfCarbon = $asOf !== '' ? Carbon::parse($asOf)->timezone($tz) : null;
        $nextToday = $asOfCarbon ? $asOfCarbon->copy()->addSeconds($todayTtl) : null;
        if ($nextToday && $nextToday->lt($now)) {
            $nextToday = $now->copy();
        }

        return [
            'today' => $todayUsers,
            'yesterday' => array_key_exists($yesterday, $byDate) ? (int) $byDate[$yesterday] : (is_array($history) ? 0 : null),
            'sum_7' => is_array($history) || $todayUsers !== null ? $sum7 : null,
            'avg_7' => is_array($history) || $todayUsers !== null ? $avg7 : null,
            'sum_30' => is_array($history) || $todayUsers !== null ? $sum30 : null,
            'avg_30' => is_array($history) || $todayUsers !== null ? $avg30 : null,
            'meta' => [
                'as_of' => $asOf !== '' ? $asOf : null,
                'as_of_human' => $asOfCarbon ? $asOfCarbon->format('d.m.Y H:i') : null,
                'next_today_at' => $nextToday ? $nextToday->toIso8601String() : null,
                'next_today_human' => $nextToday ? $nextToday->format('d.m.Y H:i') : null,
                'history_as_of' => $historyAsOf !== '' ? $historyAsOf : null,
                'today_as_of' => $todayAsOf !== '' ? $todayAsOf : null,
            ],
        ];
    }

    /**
     * @return array<string,int>|null
     */
    private function fetchUsersByDate(string $accessToken, int $counterId, string $date1, string $date2, int $userId): ?array
    {
        $multi = $this->fetchUsersByDateMulti($accessToken, [$counterId], $date1, $date2, $userId);
        if ($multi === null) {
            return null;
        }

        return $multi[$counterId] ?? [];
    }

    /**
     * @param list<int> $counterIds
     * @return array<int, array<string,int>>|null counter_id => [Y-m-d => users]
     */
    private function fetchUsersByDateMulti(string $accessToken, array $counterIds, string $date1, string $date2, int $userId): ?array
    {
        $counterIds = array_values(array_unique(array_filter(array_map('intval', $counterIds))));
        if ($counterIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($counterIds, 25) as $chunk) {
            try {
                $client = $this->httpClient();
                $response = $client->get('stat/v1/data', [
                    'headers' => [
                        'Authorization' => 'OAuth ' . $accessToken,
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'ids' => implode(',', $chunk),
                        'metrics' => 'ym:s:users',
                        'dimensions' => 'ym:s:date,ym:s:counterID',
                        'date1' => $date1,
                        'date2' => $date2,
                        'accuracy' => 'full',
                        'limit' => 10000,
                        'sort' => 'ym:s:date',
                    ],
                    'http_errors' => false,
                ]);
            } catch (Throwable $e) {
                Log::warning('yandex metrika visitors multi failed', [
                    'user_id' => $userId,
                    'counters' => count($chunk),
                    'date1' => $date1,
                    'date2' => $date2,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            if ($response->getStatusCode() >= 400) {
                Log::warning('yandex metrika visitors multi http error', [
                    'user_id' => $userId,
                    'counters' => count($chunk),
                    'date1' => $date1,
                    'date2' => $date2,
                    'status' => $response->getStatusCode(),
                    'body' => mb_substr((string) $response->getBody(), 0, 400),
                ]);

                return null;
            }

            $body = json_decode((string) $response->getBody(), true);
            $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
            foreach ($chunk as $id) {
                if (!isset($out[$id])) {
                    $out[$id] = [];
                }
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $date = (string) ($row['dimensions'][0]['name'] ?? '');
                if ($date === '' && isset($row['dimensions'][0]['id'])) {
                    $date = (string) $row['dimensions'][0]['id'];
                }
                $counterId = (int) ($row['dimensions'][1]['id'] ?? 0);
                if ($counterId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }
                $out[$counterId][$date] = (int) round((float) ($row['metrics'][0] ?? 0));
            }
        }

        return $out;
    }

    /**
     * Агрегаты сессий за период (для SEO-отчётов).
     *
     * @return array{
     *   visits:float,
     *   users:float,
     *   pageviews:float,
     *   bounce_rate:float,
     *   page_depth:float,
     *   avg_visit_duration:float
     * }|null
     */
    public function sessionTotalsForPeriod(int $userId, int $counterId, string $date1, string $date2): ?array
    {
        return $this->fetchSessionTotals($userId, $counterId, $date1, $date2, null);
    }

    /**
     * Список целей счётчика.
     *
     * @return list<array{id:int,name:string,type:?string}>|null
     */
    public function listGoals(int $userId, int $counterId): ?array
    {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null || $counterId < 1) {
            return null;
        }

        try {
            $client = $this->httpClient();
            $response = $client->get('management/v1/counter/' . $counterId . '/goals', [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex metrika goals list failed', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $goals = is_array($body['goals'] ?? null) ? $body['goals'] : [];
        $out = [];
        foreach ($goals as $goal) {
            if (!is_array($goal)) {
                continue;
            }
            $id = (int) ($goal['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => (string) ($goal['name'] ?? ('Goal #' . $id)),
                'type' => isset($goal['type']) ? (string) $goal['type'] : null,
            ];
        }

        return $out;
    }

    /**
     * Достижения целей за период.
     *
     * @param list<int> $goalIds
     * @return array<int, array{reaches:float,conversion_rate:float}>|null
     */
    public function goalTotalsForPeriod(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        array $goalIds,
        ?string $filters = null
    ): ?array {
        $goalIds = array_values(array_unique(array_filter(array_map('intval', $goalIds))));
        if ($counterId < 1 || $goalIds === []) {
            return null;
        }
        $ttl = max(0, (int) config('seo-reports.metrika_cache_ttl', 3600));
        $fetcher = function () use ($userId, $counterId, $date1, $date2, $goalIds, $filters) {
            return $this->fetchGoalTotalsForPeriod($userId, $counterId, $date1, $date2, $goalIds, $filters);
        };
        if ($ttl < 1) {
            return $fetcher();
        }
        $key = 'sr.metrika.goals.' . sha1(implode('|', [
            $userId, $counterId, $date1, $date2, implode(',', $goalIds), (string) $filters,
        ]));

        return Cache::remember($key, $ttl, $fetcher);
    }

    /**
     * @param list<int> $goalIds
     * @return array<int, array{reaches:float,conversion_rate:float}>|null
     */
    private function fetchGoalTotalsForPeriod(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        array $goalIds,
        ?string $filters = null
    ): ?array {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null) {
            return null;
        }

        $metrics = [];
        foreach ($goalIds as $goalId) {
            $metrics[] = 'ym:s:goal' . $goalId . 'reaches';
            $metrics[] = 'ym:s:goal' . $goalId . 'conversionRate';
        }

        $query = [
            'ids' => $counterId,
            'metrics' => implode(',', $metrics),
            'date1' => $date1,
            'date2' => $date2,
            'accuracy' => 'full',
            'limit' => 1,
        ];
        if ($filters !== null && $filters !== '') {
            $query['filters'] = $filters;
        }

        try {
            $client = $this->httpClient();
            $response = $client->get('stat/v1/data', [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex metrika goal totals failed', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $values = $body['data'][0]['metrics'] ?? ($body['totals'] ?? null);
        if (!is_array($values)) {
            return null;
        }

        $out = [];
        $i = 0;
        foreach ($goalIds as $goalId) {
            $out[$goalId] = [
                'reaches' => (float) ($values[$i] ?? 0),
                'conversion_rate' => (float) ($values[$i + 1] ?? 0),
            ];
            $i += 2;
        }

        return $out;
    }

    /**
     * Конверсии по каналам для одной цели.
     *
     * @return list<array{name:string,reaches:float,conversion_rate:float}>|null
     */
    public function goalChannelsForPeriod(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        int $goalId,
        ?string $filters = null
    ): ?array {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null || $counterId < 1 || $goalId < 1) {
            return null;
        }

        $query = [
            'ids' => $counterId,
            'metrics' => 'ym:s:goal' . $goalId . 'reaches,ym:s:goal' . $goalId . 'conversionRate',
            'dimensions' => 'ym:s:lastTrafficSource',
            'date1' => $date1,
            'date2' => $date2,
            'accuracy' => 'full',
            'limit' => 15,
            'sort' => '-ym:s:goal' . $goalId . 'reaches',
        ];
        if ($filters !== null && $filters !== '') {
            $query['filters'] = $filters;
        }

        try {
            $client = $this->httpClient();
            $response = $client->get('stat/v1/data', [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['dimensions'][0]['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $metrics = is_array($row['metrics'] ?? null) ? $row['metrics'] : [];
            $out[] = [
                'name' => $name,
                'reaches' => (float) ($metrics[0] ?? 0),
                'conversion_rate' => (float) ($metrics[1] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<string,int>|null Y-m-d => users
     */
    public function usersByDateForPeriod(int $userId, int $counterId, string $date1, string $date2): ?array
    {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null || $counterId < 1) {
            return null;
        }

        return $this->fetchUsersByDate($accessToken, $counterId, $date1, $date2, $userId);
    }

    /**
     * @return array{
     *   visits:float,
     *   users:float,
     *   pageviews:float,
     *   bounce_rate:float,
     *   page_depth:float,
     *   avg_visit_duration:float
     * }|null
     */
    public function sessionTotalsForPeriodFiltered(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        ?string $filters = null
    ): ?array {
        $ttl = max(0, (int) config('seo-reports.metrika_cache_ttl', 3600));
        if ($ttl < 1) {
            return $this->fetchSessionTotals($userId, $counterId, $date1, $date2, $filters);
        }
        $key = 'sr.metrika.totals.' . sha1(implode('|', [
            $userId, $counterId, $date1, $date2, (string) $filters,
        ]));

        return Cache::remember($key, $ttl, function () use ($userId, $counterId, $date1, $date2, $filters) {
            return $this->fetchSessionTotals($userId, $counterId, $date1, $date2, $filters);
        });
    }

    /**
     * Отчёт с одной dimension и стандартным набором метрик сессий.
     *
     * @return list<array{name:string,id:?string,visits:float,users:float,bounce_rate:float,page_depth:float,avg_visit_duration:float}>|null
     */
    public function dimensionRowsForPeriod(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        string $dimension,
        int $limit = 20,
        ?string $filters = null,
        string $sort = '-ym:s:visits'
    ): ?array {
        $ttl = max(0, (int) config('seo-reports.metrika_cache_ttl', 3600));
        $fetcher = function () use ($userId, $counterId, $date1, $date2, $dimension, $limit, $filters, $sort) {
            return $this->fetchDimensionRowsForPeriod(
                $userId, $counterId, $date1, $date2, $dimension, $limit, $filters, $sort
            );
        };
        if ($ttl < 1) {
            return $fetcher();
        }
        $key = 'sr.metrika.dim.' . sha1(implode('|', [
            $userId, $counterId, $date1, $date2, $dimension, $limit, (string) $filters, $sort,
        ]));

        return Cache::remember($key, $ttl, $fetcher);
    }

    /**
     * @return list<array{name:string,id:?string,visits:float,users:float,bounce_rate:float,page_depth:float,avg_visit_duration:float}>|null
     */
    private function fetchDimensionRowsForPeriod(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        string $dimension,
        int $limit = 20,
        ?string $filters = null,
        string $sort = '-ym:s:visits'
    ): ?array {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null || $counterId < 1 || $dimension === '') {
            return null;
        }

        $query = [
            'ids' => $counterId,
            'metrics' => implode(',', [
                'ym:s:visits',
                'ym:s:users',
                'ym:s:bounceRate',
                'ym:s:pageDepth',
                'ym:s:avgVisitDurationSeconds',
            ]),
            'dimensions' => $dimension,
            'date1' => $date1,
            'date2' => $date2,
            'accuracy' => 'full',
            'limit' => max(1, min(100, $limit)),
            'sort' => $sort,
            'lang' => 'ru',
        ];
        if ($filters !== null && $filters !== '') {
            $query['filters'] = $filters;
        }

        try {
            $client = $this->httpClient();
            $response = $client->get('stat/v1/data', [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex metrika dimension rows failed', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'dimension' => $dimension,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() >= 400) {
            Log::warning('yandex metrika dimension rows http error', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'dimension' => $dimension,
                'status' => $response->getStatusCode(),
                'body' => mb_substr((string) $response->getBody(), 0, 400),
            ]);

            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['dimensions'][0]['name'] ?? '');
            $id = isset($row['dimensions'][0]['id']) ? (string) $row['dimensions'][0]['id'] : null;
            if ($name === '' && $id !== null) {
                $name = $id;
            }
            if ($name === '') {
                continue;
            }
            $metrics = is_array($row['metrics'] ?? null) ? $row['metrics'] : [];
            $out[] = [
                'name' => $name,
                'id' => $id,
                'visits' => (float) ($metrics[0] ?? 0),
                'users' => (float) ($metrics[1] ?? 0),
                'bounce_rate' => (float) ($metrics[2] ?? 0),
                'page_depth' => (float) ($metrics[3] ?? 0),
                'avg_visit_duration' => (float) ($metrics[4] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<string,int>|null Y-m-d => visits
     */
    public function visitsByDateForPeriodFiltered(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        ?string $filters = null
    ): ?array {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null || $counterId < 1) {
            return null;
        }

        $query = [
            'ids' => $counterId,
            'metrics' => 'ym:s:visits',
            'dimensions' => 'ym:s:date',
            'date1' => $date1,
            'date2' => $date2,
            'accuracy' => 'full',
            'limit' => 10000,
            'sort' => 'ym:s:date',
        ];
        if ($filters !== null && $filters !== '') {
            $query['filters'] = $filters;
        }

        try {
            $client = $this->httpClient();
            $response = $client->get('stat/v1/data', [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex metrika visits-by-date failed', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = (string) ($row['dimensions'][0]['name'] ?? ($row['dimensions'][0]['id'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $out[$date] = (int) round((float) ($row['metrics'][0] ?? 0));
        }

        return $out;
    }

    /**
     * @return array{
     *   visits:float,
     *   users:float,
     *   pageviews:float,
     *   bounce_rate:float,
     *   page_depth:float,
     *   avg_visit_duration:float
     * }|null
     */
    private function fetchSessionTotals(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        ?string $filters = null
    ): ?array {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null || $counterId < 1) {
            return null;
        }

        $query = [
            'ids' => $counterId,
            'metrics' => implode(',', [
                'ym:s:visits',
                'ym:s:users',
                'ym:s:pageviews',
                'ym:s:bounceRate',
                'ym:s:pageDepth',
                'ym:s:avgVisitDurationSeconds',
            ]),
            'date1' => $date1,
            'date2' => $date2,
            'accuracy' => 'full',
            'limit' => 1,
        ];
        if ($filters !== null && $filters !== '') {
            $query['filters'] = $filters;
        }

        try {
            $client = $this->httpClient();
            $response = $client->get('stat/v1/data', [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex metrika session totals failed', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'date1' => $date1,
                'date2' => $date2,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() >= 400) {
            Log::warning('yandex metrika session totals http error', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'status' => $response->getStatusCode(),
                'body' => mb_substr((string) $response->getBody(), 0, 400),
            ]);

            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $metrics = $body['data'][0]['metrics'] ?? ($body['totals'] ?? null);
        if (!is_array($metrics) || count($metrics) < 6) {
            return null;
        }

        return [
            'visits' => (float) ($metrics[0] ?? 0),
            'users' => (float) ($metrics[1] ?? 0),
            'pageviews' => (float) ($metrics[2] ?? 0),
            'bounce_rate' => (float) ($metrics[3] ?? 0),
            'page_depth' => (float) ($metrics[4] ?? 0),
            'avg_visit_duration' => (float) ($metrics[5] ?? 0),
        ];
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

    /**
     * Произвольный запрос totals (одна строка) к Stat API.
     *
     * @param list<string> $metrics
     * @return list<float>|null
     */
    public function customTotalsForPeriod(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        array $metrics,
        ?string $filters = null
    ): ?array {
        $metrics = array_values(array_filter(array_map('strval', $metrics)));
        if ($counterId < 1 || $metrics === []) {
            return null;
        }
        $ttl = max(0, (int) config('seo-reports.metrika_cache_ttl', 3600));
        $fetcher = function () use ($userId, $counterId, $date1, $date2, $metrics, $filters) {
            return $this->fetchCustomStat($userId, $counterId, $date1, $date2, $metrics, null, 1, null, $filters);
        };
        if ($ttl < 1) {
            $rows = $fetcher();

            return $rows[0]['metrics'] ?? null;
        }
        $key = 'sr.metrika.custom.' . sha1(implode('|', [
            $userId, $counterId, $date1, $date2, implode(',', $metrics), (string) $filters, 'totals',
        ]));
        $cached = Cache::remember($key, $ttl, $fetcher);

        return is_array($cached[0]['metrics'] ?? null) ? $cached[0]['metrics'] : null;
    }

    /**
     * Произвольный dimension-отчёт.
     *
     * @param list<string> $metrics
     * @return list<array{name:string,id:?string,metrics:list<float>}>|null
     */
    public function customDimensionRowsForPeriod(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        string $dimension,
        array $metrics,
        int $limit = 15,
        ?string $filters = null,
        ?string $sort = null
    ): ?array {
        $metrics = array_values(array_filter(array_map('strval', $metrics)));
        if ($counterId < 1 || $dimension === '' || $metrics === []) {
            return null;
        }
        $ttl = max(0, (int) config('seo-reports.metrika_cache_ttl', 3600));
        $fetcher = function () use ($userId, $counterId, $date1, $date2, $metrics, $dimension, $limit, $filters, $sort) {
            return $this->fetchCustomStat(
                $userId, $counterId, $date1, $date2, $metrics, $dimension, $limit, $sort, $filters
            );
        };
        if ($ttl < 1) {
            return $fetcher();
        }
        $key = 'sr.metrika.customdim.' . sha1(implode('|', [
            $userId, $counterId, $date1, $date2, $dimension, implode(',', $metrics), $limit,
            (string) $filters, (string) $sort,
        ]));

        return Cache::remember($key, $ttl, $fetcher);
    }

    /**
     * @param list<string> $metrics
     * @return list<array{name:string,id:?string,metrics:list<float>}>|null
     */
    private function fetchCustomStat(
        int $userId,
        int $counterId,
        string $date1,
        string $date2,
        array $metrics,
        ?string $dimension,
        int $limit,
        ?string $sort,
        ?string $filters
    ): ?array {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null) {
            return null;
        }

        $query = [
            'ids' => $counterId,
            'metrics' => implode(',', $metrics),
            'date1' => $date1,
            'date2' => $date2,
            'accuracy' => 'full',
            'limit' => max(1, min(100, $limit)),
            'lang' => 'ru',
        ];
        if ($dimension !== null && $dimension !== '') {
            $query['dimensions'] = $dimension;
            $query['sort'] = $sort ?: ('-' . $metrics[0]);
        }
        if ($filters !== null && $filters !== '') {
            $query['filters'] = $filters;
        }

        try {
            $client = $this->httpClient();
            $response = $client->get('stat/v1/data', [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex metrika custom stat failed', [
                'user_id' => $userId,
                'counter_id' => $counterId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        if ($dimension === null || $dimension === '') {
            $values = $body['data'][0]['metrics'] ?? ($body['totals'] ?? null);
            if (!is_array($values)) {
                return null;
            }

            return [[
                'name' => 'totals',
                'id' => null,
                'metrics' => array_map('floatval', $values),
            ]];
        }

        $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['dimensions'][0]['name'] ?? '');
            $id = isset($row['dimensions'][0]['id']) ? (string) $row['dimensions'][0]['id'] : null;
            if ($name === '' && $id !== null) {
                $name = $id;
            }
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'id' => $id,
                'metrics' => array_map('floatval', is_array($row['metrics'] ?? null) ? $row['metrics'] : []),
            ];
        }

        return $out;
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
