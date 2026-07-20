<?php

namespace App\Services\Demo;

use App\Services\DomainRecordsService;
use App\Support\TextAnalyzerPdfBranding;

class DomainRecordsDemoService
{
    public const MODULE = 'zapisi-domena';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-domain-records.demo', []);
    }

    /**
     * @param array{domain?: string} $input
     * @return array{ok: true, domain: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $domain = trim((string) ($input['domain'] ?? ''));
        if ($domain === '') {
            return self::fail(422, 'validation', 'Укажите домен для проверки, например example.ru');
        }

        $service = new DomainRecordsService();
        $normalized = $service->normalizeHost($domain);
        if ($normalized === '' || ! $service->isPublicDomainHost($normalized)) {
            return self::fail(422, 'validation', 'Укажите корректный домен с зоной, например example.ru');
        }

        return ['ok' => true, 'domain' => $normalized];
    }

    /**
     * @param array{domain: string} $validated
     * @return array<string, mixed>
     */
    public static function lookup(array $validated): array
    {
        $cfg = self::config();
        $maxNeighbors = max(0, (int) ($cfg['max_neighbors_per_ip'] ?? 8));

        $service = new DomainRecordsService();
        $raw = $service->lookup($validated['domain']);
        if (empty($raw['ok'])) {
            throw new \RuntimeException((string) ($raw['message'] ?? 'lookup_failed'));
        }

        $ips = [];
        foreach ($raw['ips'] ?? [] as $row) {
            $neighbors = array_values(array_slice($row['neighbors'] ?? [], 0, $maxNeighbors));
            $ips[] = [
                'ip' => $row['ip'] ?? null,
                'neighbors' => $neighbors,
                'neighbors_count' => count($row['neighbors'] ?? []),
                'neighbors_truncated' => count($row['neighbors'] ?? []) > $maxNeighbors,
            ];
        }

        $dns = $raw['dns'] ?? [];
        $dnsCounts = [];
        foreach (['A', 'AAAA', 'MX', 'NS', 'TXT', 'CNAME', 'SOA'] as $type) {
            $dnsCounts[$type] = count($dns[$type] ?? []);
        }

        $whois = $raw['whois'] ?? [];

        return [
            'domain' => $raw['domain'],
            'punycode' => $raw['punycode'] ?? null,
            'summary' => $raw['summary'] ?? [],
            'whois' => [
                'status_key' => $whois['status_key'] ?? null,
                'registered_at' => $whois['registered_at'] ?? null,
                'expires_at' => $whois['expires_at'] ?? null,
                'days_until_expiry' => $whois['days_until_expiry'] ?? null,
                'dns_servers' => array_values(array_slice($whois['dns_servers'] ?? [], 0, 8)),
                'broken' => ! empty($whois['broken']),
            ],
            'dns_counts' => $dnsCounts,
            'ips' => $ips,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 5);

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_runs_per_day' => $maxRuns,
                'max_domains_per_run' => 1,
            ],
            'result' => $result,
            'upgrade' => [
                'register_url' => url('/register?module=' . self::MODULE . '&from=demo&guest=' . urlencode($guestId)),
                'login_url' => TextAnalyzerPdfBranding::loginUrl(),
            ],
        ];
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'error' => $error, 'message' => $message];
    }
}
