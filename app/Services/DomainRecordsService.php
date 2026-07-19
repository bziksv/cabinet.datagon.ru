<?php

namespace App\Services;

use App\DomainInformation;
use App\Support\DomainInformationDns;

class DomainRecordsService
{
    /**
     * @return array<string, mixed>
     */
    public function lookup(string $raw): array
    {
        $normalized = $this->normalizeHost($raw);
        if ($normalized === '') {
            return [
                'ok' => false,
                'error' => 'invalid',
                'message' => (string) __('Domain records invalid domain'),
                'domain' => '',
            ];
        }

        @set_time_limit(60);

        $whois = DomainInformation::probe($normalized);
        $dns = $this->fetchDnsRecords($normalized);

        // Если локальный резолвер пуст — DoH (Cloudflare).
        if ($this->dnsIsEmpty($dns)) {
            $dns = $this->fetchDnsViaDoh($normalized);
        }

        // NS из WHOIS, если DNS NS пустые.
        if (empty($dns['NS']) && ! empty($whois['dns_servers'])) {
            foreach ($whois['dns_servers'] as $ns) {
                $host = DomainInformationDns::normalizeHost((string) $ns);
                if ($host === '') {
                    continue;
                }
                $dns['NS'][] = [
                    'type' => 'NS',
                    'host' => $normalized,
                    'ttl' => null,
                    'value' => $host,
                    'target' => $host,
                    'pri' => null,
                ];
            }
        }

        $ips = $this->collectIps($dns);
        // Быстрый fallback A через gethostbyname, если A пустой.
        if ($ips === []) {
            $ascii = $this->toAscii($normalized) ?: $normalized;
            $ip = @gethostbyname($ascii);
            if (is_string($ip) && $ip !== '' && $ip !== $ascii && filter_var($ip, FILTER_VALIDATE_IP)) {
                $dns['A'][] = [
                    'type' => 'A',
                    'host' => $normalized,
                    'ttl' => null,
                    'value' => $ip,
                    'target' => $ip,
                    'pri' => null,
                ];
                $ips[] = $ip;
            }
        }

        $ipInfo = [];
        foreach ($ips as $ip) {
            $neighbors = $this->neighborsOnIp($ip, $normalized);
            $ipInfo[] = [
                'ip' => $ip,
                'hostname' => null,
                'neighbors' => $neighbors['domains'] ?? [],
                'neighbors_status' => $neighbors['status'] ?? 'empty',
                'neighbors_message' => $neighbors['message'] ?? null,
                'neighbors_loaded' => true,
            ];
        }

        return [
            'ok' => true,
            'domain' => $normalized,
            'punycode' => $this->toAscii($normalized),
            'whois' => $whois,
            'dns' => $dns,
            'ips' => $ipInfo,
            'summary' => [
                'registered' => empty($whois['broken']),
                'status_key' => $whois['status_key'] ?? null,
                'expires_at' => $whois['expires_at'] ?? null,
                'days_until_expiry' => $whois['days_until_expiry'] ?? null,
                'ns' => $whois['dns_servers'] ?? [],
                'a_count' => count($dns['A'] ?? []),
                'mx_count' => count($dns['MX'] ?? []),
            ],
        ];
    }

    /**
     * Другие домены на том же IP (reverse IP via HackerTarget + RapidDNS fallback).
     *
     * @return array{
     *   ok: bool,
     *   ip: string,
     *   domains: string[],
     *   status: string,
     *   found_total?: int,
     *   truncated?: bool,
     *   message?: string
     * }
     */
    public function neighborsOnIp(string $ip, string $excludeDomain = ''): array
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return [
                'ok' => false,
                'ip' => $ip,
                'domains' => [],
                'status' => 'invalid',
                'message' => (string) __('Domain records invalid ip'),
            ];
        }

        $exclude = strtolower(rtrim($excludeDomain, '.'));
        $fetch = $this->fetchReverseIpDomains($ip);
        $domains = $fetch['domains'] ?? [];
        $apiError = $fetch['error'] ?? null;

        if ($apiError !== null && $domains === []) {
            return [
                'ok' => true,
                'ip' => $ip,
                'domains' => [],
                'status' => 'api_error',
                'found_total' => 0,
                'message' => (string) __('Domain records ip neighbors api error'),
            ];
        }

        $filtered = [];
        foreach ($domains as $d) {
            $d = strtolower(rtrim((string) $d, '.'));
            if ($d === '' || $d === $exclude) {
                continue;
            }
            if (strpos($d, 'www.') === 0 && substr($d, 4) === $exclude) {
                continue;
            }
            // www.X при exclude=X уже отфильтрован; также отбросим сам exclude с www-
            if ($exclude !== '' && $d === 'www.' . $exclude) {
                continue;
            }
            $filtered[$d] = $d;
        }
        $list = array_values($filtered);
        sort($list);

        $foundTotal = count($domains);
        if ($list === []) {
            return [
                'ok' => true,
                'ip' => $ip,
                'domains' => [],
                'status' => $foundTotal > 0 ? 'self_only' : 'empty',
                'found_total' => $foundTotal,
                'message' => $foundTotal > 0
                    ? (string) __('Domain records ip neighbors self only')
                    : (string) __('Domain records ip neighbors empty'),
            ];
        }

        return [
            'ok' => true,
            'ip' => $ip,
            'domains' => array_slice($list, 0, 200),
            'status' => 'ok',
            'found_total' => $foundTotal,
            'truncated' => count($list) > 200,
        ];
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array<string, mixed>
     */
    public function diffSnapshots(array $old, array $new): array
    {
        $whoisFields = ['registered_at', 'expires_at', 'status_key', 'status'];
        $whois = [];
        foreach ($whoisFields as $field) {
            $a = (string) ($old['whois'][$field] ?? '');
            $b = (string) ($new['whois'][$field] ?? '');
            $whois[$field] = [
                'old' => $a !== '' ? $a : null,
                'new' => $b !== '' ? $b : null,
                'changed' => $a !== $b,
            ];
        }

        $oldNs = $this->flatList($old['whois']['dns_servers'] ?? []);
        $newNs = $this->flatList($new['whois']['dns_servers'] ?? []);
        $whois['dns_servers'] = [
            'added' => array_values(array_diff($newNs, $oldNs)),
            'removed' => array_values(array_diff($oldNs, $newNs)),
            'unchanged' => array_values(array_intersect($newNs, $oldNs)),
        ];

        $dnsDiff = [];
        $types = array_unique(array_merge(
            array_keys($old['dns'] ?? []),
            array_keys($new['dns'] ?? [])
        ));
        foreach ($types as $type) {
            $oldSet = $this->dnsValueSet($old['dns'][$type] ?? []);
            $newSet = $this->dnsValueSet($new['dns'][$type] ?? []);
            $dnsDiff[$type] = [
                'added' => array_values(array_diff($newSet, $oldSet)),
                'removed' => array_values(array_diff($oldSet, $newSet)),
                'unchanged' => array_values(array_intersect($newSet, $oldSet)),
            ];
        }

        $oldIps = $this->flatList(array_column($old['ips'] ?? [], 'ip'));
        $newIps = $this->flatList(array_column($new['ips'] ?? [], 'ip'));

        return [
            'whois' => $whois,
            'dns' => $dnsDiff,
            'ips' => [
                'added' => array_values(array_diff($newIps, $oldIps)),
                'removed' => array_values(array_diff($oldIps, $newIps)),
                'unchanged' => array_values(array_intersect($newIps, $oldIps)),
            ],
        ];
    }

    /**
     * @return array{domains: string[], error: ?string}
     */
    private function fetchReverseIpDomains(string $ip): array
    {
        $fromHt = $this->fetchReverseIpHackerTarget($ip);
        if ($fromHt['domains'] !== []) {
            return $fromHt;
        }

        $fromRapid = $this->fetchReverseIpRapidDns($ip);
        if ($fromRapid['domains'] !== []) {
            return $fromRapid;
        }

        // Оба источника пусты: если HT явно ошибся — отдаём ошибку, иначе пустой ok.
        if (($fromHt['error'] ?? null) !== null) {
            return $fromHt;
        }

        return ['domains' => [], 'error' => $fromRapid['error'] ?? null];
    }

    /**
     * @return array{domains: string[], error: ?string}
     */
    private function fetchReverseIpHackerTarget(string $ip): array
    {
        $url = 'https://api.hackertarget.com/reverseiplookup/?q=' . rawurlencode($ip);
        $timeout = (int) config('cabinet-domain-records.reverse_ip_timeout', 12);
        $body = $this->httpGetBody($url, $timeout);

        if ($body === null || $body === '') {
            return ['domains' => [], 'error' => 'empty'];
        }

        $body = trim($body);
        if (stripos($body, 'error look') === 0
            || stripos($body, 'api count exceeded') !== false
            || stripos($body, 'error invalid') === 0
        ) {
            return ['domains' => [], 'error' => 'provider'];
        }

        return ['domains' => $this->parseDomainLines($body), 'error' => null];
    }

    /**
     * @return array{domains: string[], error: ?string}
     */
    private function fetchReverseIpRapidDns(string $ip): array
    {
        $url = 'https://rapiddns.io/sameip/' . rawurlencode($ip) . '?full=1';
        $timeout = (int) config('cabinet-domain-records.reverse_ip_timeout', 12);
        $html = $this->httpGetBody($url, $timeout, 'Mozilla/5.0 (compatible; TitloDomainRecords/1.0)');

        if ($html === null || $html === '') {
            return ['domains' => [], 'error' => 'empty'];
        }

        $domains = [];
        if (preg_match_all('#<td>\s*([a-z0-9._-]+\.[a-z0-9.-]+)\s*</td>#i', $html, $m)) {
            foreach ($m[1] as $host) {
                $host = strtolower(trim($host));
                if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) {
                    $domains[$host] = $host;
                }
            }
        }

        return ['domains' => array_values($domains), 'error' => null];
    }

    /**
     * @return string[]
     */
    private function parseDomainLines(string $body): array
    {
        $out = [];
        foreach (preg_split('/\R+/', $body) ?: [] as $line) {
            $line = strtolower(trim($line));
            if ($line === '' || ! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $line)) {
                continue;
            }
            $out[] = $line;
        }

        return $out;
    }

    private function httpGetBody(string $url, int $timeout, ?string $userAgent = null): ?string
    {
        $ua = $userAgent ?: 'Mozilla/5.0 (compatible; TitloDomainRecords/1.0)';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $ua,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);

            return is_string($body) ? $body : null;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => "User-Agent: {$ua}\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return is_string($body) ? $body : null;
    }

    /**
     * @param mixed $list
     * @return string[]
     */
    private function flatList($list): array
    {
        if (! is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $item) {
            $v = strtolower(trim((string) $item));
            if ($v !== '') {
                $out[$v] = $v;
            }
        }

        return array_values($out);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return string[]
     */
    private function dnsValueSet(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $v = trim((string) ($row['value'] ?? ''));
            if ($v !== '') {
                $out[$v] = $v;
            }
        }

        return array_values($out);
    }

    public function normalizeHost(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Не через parse_url: для кириллицы http://тест.рф часто ломается.
        $raw = preg_replace('#^https?://#i', '', $raw) ?? $raw;
        $parts = preg_split('~[/?#]~', $raw, 2);
        $host = (string) ($parts[0] ?? '');
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;
        $host = $this->lowerHost(rtrim($host, '.'));
        $host = preg_replace('#^www\.#u', '', $host) ?? $host;

        if (! $this->isPublicDomainHost($host)) {
            return '';
        }

        return $host;
    }

    /**
     * Домен с зоной (example.ru). Без TLD вроде gorexpert — не принимаем.
     */
    public function isPublicDomainHost(string $host): bool
    {
        $host = $this->lowerHost(trim(rtrim($host, '.')));
        if ($host === '' || strpos($host, '.') === false) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ascii = $this->toAscii($host);
        $check = $ascii !== '' ? $ascii : $host;

        if (! DomainInformation::isValidDomain($check)) {
            return false;
        }

        $labels = explode('.', $check);
        if (count($labels) < 2) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }
        }

        $tld = (string) end($labels);
        // зона минимум 2 символа; punycode xn--… тоже ок
        if (strlen($tld) < 2 || ! preg_match('/^[a-z0-9-]+$/i', $tld)) {
            return false;
        }

        return true;
    }

    private function lowerHost(string $host): string
    {
        if ($host === '') {
            return '';
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($host, 'UTF-8')
            : strtolower($host);
    }

    private function toAscii(string $host): string
    {
        if ($host === '') {
            return '';
        }
        if (function_exists('idn_to_ascii')) {
            $converted = @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($converted) && $converted !== '') {
                return strtolower($converted);
            }
        }

        return $host;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $dns
     */
    private function dnsIsEmpty(array $dns): bool
    {
        foreach ($dns as $rows) {
            if (! empty($rows)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function fetchDnsRecords(string $domain): array
    {
        $types = config('cabinet-domain-records.dns_types', ['A', 'AAAA', 'MX', 'NS', 'TXT', 'CNAME', 'SOA', 'SRV']);
        $lookupHost = $this->toAscii($domain) ?: $domain;
        $grouped = $this->emptyDnsGroups($types);

        foreach ($types as $type) {
            $const = $this->dnsTypeConstant($type);
            if ($const === null) {
                continue;
            }

            $records = @dns_get_record($lookupHost, $const);
            if (! is_array($records)) {
                continue;
            }

            foreach ($records as $rec) {
                $grouped[$type][] = $this->normalizeDnsRow($type, $rec);
            }
        }

        if (! empty($grouped['NS'])) {
            usort($grouped['NS'], function ($a, $b) {
                return strcmp((string) ($a['target'] ?? ''), (string) ($b['target'] ?? ''));
            });
        }

        return $grouped;
    }

    /**
     * DNS over HTTPS (Cloudflare) — когда системный резолвер PHP пустой/ломается.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function fetchDnsViaDoh(string $domain): array
    {
        $types = config('cabinet-domain-records.dns_types', ['A', 'AAAA', 'MX', 'NS', 'TXT', 'CNAME', 'SOA', 'SRV']);
        $lookupHost = $this->toAscii($domain) ?: $domain;
        $grouped = $this->emptyDnsGroups($types);
        $typeCodes = [
            'A' => 1,
            'NS' => 2,
            'CNAME' => 5,
            'SOA' => 6,
            'MX' => 15,
            'TXT' => 16,
            'AAAA' => 28,
            'SRV' => 33,
        ];

        foreach ($types as $type) {
            $code = $typeCodes[$type] ?? null;
            if ($code === null) {
                continue;
            }
            $payload = $this->dohQuery($lookupHost, $code);
            if ($payload === null) {
                continue;
            }
            foreach ($payload['Answer'] ?? [] as $ans) {
                if (! is_array($ans)) {
                    continue;
                }
                $ansType = (int) ($ans['type'] ?? 0);
                if ($ansType !== $code) {
                    continue;
                }
                $grouped[$type][] = $this->normalizeDohAnswer($type, $lookupHost, $ans);
            }
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dohQuery(string $name, int $type): ?array
    {
        $url = 'https://cloudflare-dns.com/dns-query?' . http_build_query([
            'name' => $name,
            'type' => $type,
        ]);
        $timeout = (int) config('cabinet-domain-records.doh_timeout', 8);

        if (! function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Accept: application/dns-json',
            ],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TitloDomainRecords/1.0)',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($body) || $body === '' || $status >= 400) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $ans
     * @return array<string, mixed>
     */
    private function normalizeDohAnswer(string $type, string $host, array $ans): array
    {
        $data = trim((string) ($ans['data'] ?? ''));
        $row = [
            'type' => $type,
            'host' => $host,
            'ttl' => $ans['TTL'] ?? null,
            'value' => $data,
            'target' => null,
            'pri' => null,
        ];

        switch ($type) {
            case 'A':
            case 'AAAA':
                $row['target'] = $data;
                break;
            case 'MX':
                if (preg_match('/^(\d+)\s+(.+)$/', $data, $m)) {
                    $row['pri'] = (int) $m[1];
                    $row['target'] = DomainInformationDns::normalizeHost(rtrim($m[2], '.'));
                    $row['value'] = $row['pri'] . ' ' . $row['target'];
                }
                break;
            case 'NS':
            case 'CNAME':
                $row['target'] = DomainInformationDns::normalizeHost(rtrim($data, '.'));
                $row['value'] = (string) $row['target'];
                break;
            case 'TXT':
                $row['value'] = trim($data, '"');
                break;
            default:
                $row['value'] = $data;
        }

        return $row;
    }

    /**
     * @param string[] $types
     * @return array<string, array>
     */
    private function emptyDnsGroups(array $types): array
    {
        $grouped = [];
        foreach ($types as $type) {
            $grouped[$type] = [];
        }

        return $grouped;
    }

    private function dnsTypeConstant(string $type): ?int
    {
        $map = [
            'A' => defined('DNS_A') ? DNS_A : null,
            'AAAA' => defined('DNS_AAAA') ? DNS_AAAA : null,
            'MX' => defined('DNS_MX') ? DNS_MX : null,
            'NS' => defined('DNS_NS') ? DNS_NS : null,
            'TXT' => defined('DNS_TXT') ? DNS_TXT : null,
            'CNAME' => defined('DNS_CNAME') ? DNS_CNAME : null,
            'SOA' => defined('DNS_SOA') ? DNS_SOA : null,
            'SRV' => defined('DNS_SRV') ? DNS_SRV : null,
        ];

        return $map[$type] ?? null;
    }

    /**
     * @param array<string, mixed> $rec
     * @return array<string, mixed>
     */
    private function normalizeDnsRow(string $type, array $rec): array
    {
        $row = [
            'type' => $type,
            'host' => $rec['host'] ?? '',
            'ttl' => $rec['ttl'] ?? null,
            'value' => '',
            'target' => null,
            'pri' => null,
        ];

        switch ($type) {
            case 'A':
                $row['value'] = (string) ($rec['ip'] ?? '');
                $row['target'] = $row['value'];
                break;
            case 'AAAA':
                $row['value'] = (string) ($rec['ipv6'] ?? '');
                $row['target'] = $row['value'];
                break;
            case 'MX':
                $row['pri'] = $rec['pri'] ?? null;
                $row['target'] = DomainInformationDns::normalizeHost((string) ($rec['target'] ?? ''));
                $row['value'] = ($row['pri'] !== null ? $row['pri'] . ' ' : '') . $row['target'];
                break;
            case 'NS':
            case 'CNAME':
                $row['target'] = DomainInformationDns::normalizeHost((string) ($rec['target'] ?? ''));
                $row['value'] = (string) $row['target'];
                break;
            case 'TXT':
                $row['value'] = (string) ($rec['txt'] ?? '');
                break;
            case 'SOA':
                $row['value'] = trim(implode(' ', array_filter([
                    $rec['mname'] ?? null,
                    $rec['rname'] ?? null,
                    isset($rec['serial']) ? 'serial=' . $rec['serial'] : null,
                    isset($rec['refresh']) ? 'refresh=' . $rec['refresh'] : null,
                    isset($rec['retry']) ? 'retry=' . $rec['retry'] : null,
                    isset($rec['expire']) ? 'expire=' . $rec['expire'] : null,
                    isset($rec['minimum-ttl']) ? 'min-ttl=' . $rec['minimum-ttl'] : null,
                ])));
                break;
            case 'SRV':
                $row['pri'] = $rec['pri'] ?? null;
                $row['target'] = (string) ($rec['target'] ?? '');
                $row['value'] = trim(($rec['pri'] ?? '') . ' ' . ($rec['weight'] ?? '') . ' ' . ($rec['port'] ?? '') . ' ' . ($rec['target'] ?? ''));
                break;
            default:
                $row['value'] = json_encode($rec, JSON_UNESCAPED_UNICODE);
        }

        return $row;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $dns
     * @return string[]
     */
    private function collectIps(array $dns): array
    {
        $ips = [];
        foreach (array_merge($dns['A'] ?? [], $dns['AAAA'] ?? []) as $row) {
            $ip = (string) ($row['value'] ?? '');
            if ($ip !== '') {
                $ips[$ip] = $ip;
            }
        }

        return array_values($ips);
    }
}
