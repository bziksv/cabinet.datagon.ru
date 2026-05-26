<?php

namespace App\Services\Demo;

use App\Classes\Curl\CurlFacade;
use App\Support\TextAnalyzerPdfBranding;
use Illuminate\Http\Request;

class HttpHeadersDemoService
{
    public const MODULE = 'http-headers';

    private const PRIORITY_HEADERS = [
        'location',
        'content-type',
        'cache-control',
        'etag',
        'last-modified',
        'expires',
        'content-encoding',
        'content-length',
        'server',
        'strict-transport-security',
        'content-security-policy',
        'x-frame-options',
        'x-content-type-options',
        'referrer-policy',
        'permissions-policy',
    ];

    private const STATUS_LABELS = [
        200 => 'OK',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        404 => 'Not Found',
        403 => 'Forbidden',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-http-headers.demo', []);
    }

    /**
     * @param array{url?: string} $input
     * @return array{ok: true, url: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $url = trim((string) ($input['url'] ?? ''));
        if ($url === '') {
            return self::fail(422, 'validation', 'Укажите URL для проверки заголовков');
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return self::fail(422, 'validation', 'URL должен быть корректным (http или https)');
        }

        return ['ok' => true, 'url' => $url];
    }

    /**
     * @param array{url: string} $validated
     * @return array<string, mixed>
     */
    public static function check(array $validated): array
    {
        $request = Request::create('/', 'GET', ['url' => $validated['url']]);
        app()->instance('request', $request);

        $raw = (new CurlFacade($validated['url']))->run();

        return self::formatResult($validated['url'], is_array($raw) ? $raw : []);
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
                'max_urls_per_run' => 1,
            ],
            'result' => $result,
            'upgrade' => [
                'register_url' => url('/register?module=' . self::MODULE . '&from=demo&guest=' . urlencode($guestId)),
                'login_url' => TextAnalyzerPdfBranding::loginUrl(),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $raw
     * @return array<string, mixed>
     */
    private static function formatResult(string $requestedUrl, array $raw): array
    {
        $hops = [];
        $finalUrl = $requestedUrl;

        foreach ($raw as $index => $hop) {
            if (!is_array($hop)) {
                continue;
            }
            $status = (int) ($hop['status'] ?? 0);
            $headers = self::formatHopHeaders($hop['headers'] ?? []);
            foreach ($headers as $h) {
                if (strtolower($h['name']) === 'location' && $h['value'] !== '') {
                    $finalUrl = $h['value'];
                }
            }
            $hops[] = [
                'index' => $index + 1,
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? ($status > 0 ? (string) $status : '—'),
                'headers' => $headers,
            ];
        }

        $finalHop = $hops[count($hops) - 1] ?? null;
        $summary = self::buildSummary($finalHop);

        return [
            'requested_url' => $requestedUrl,
            'final_url' => $finalUrl,
            'hop_count' => count($hops),
            'hops' => $hops,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string, mixed> $rawHeaders
     * @return array<int, array{name: string, value: string}>
     */
    private static function formatHopHeaders($rawHeaders): array
    {
        if (!is_array($rawHeaders)) {
            return [];
        }

        $flat = [];
        foreach ($rawHeaders as $name => $value) {
            $name = (string) $name;
            if (is_array($value)) {
                foreach ($value as $v) {
                    $flat[] = ['name' => $name, 'value' => self::maskHeaderValue($name, (string) $v)];
                }
            } else {
                $flat[] = ['name' => $name, 'value' => self::maskHeaderValue($name, (string) $value)];
            }
        }

        return self::prioritizeHeaders($flat, 28);
    }

    private static function maskHeaderValue(string $name, string $value): string
    {
        if (strtolower($name) !== 'set-cookie') {
            return $value;
        }

        $count = substr_count($value, '=') > 0 ? 1 : 0;
        if (strpos($value, ',') !== false) {
            $count = max(1, substr_count($value, '='));
        }

        return $count > 1 ? $count . ' cookie(s) в ответе' : 'cookie в ответе (значение скрыто в демо)';
    }

    /**
     * @param array<int, array{name: string, value: string}> $headers
     * @return array<int, array{name: string, value: string}>
     */
    private static function prioritizeHeaders(array $headers, int $max): array
    {
        $byLower = [];
        foreach ($headers as $h) {
            $byLower[strtolower($h['name'])] = $h;
        }

        $picked = [];
        foreach (self::PRIORITY_HEADERS as $key) {
            if (isset($byLower[$key])) {
                $picked[] = $byLower[$key];
                unset($byLower[$key]);
            }
        }

        foreach ($byLower as $h) {
            if (count($picked) >= $max) {
                break;
            }
            $picked[] = $h;
        }

        return $picked;
    }

    /**
     * @param array<string, mixed>|null $finalHop
     * @return array<string, mixed>
     */
    private static function buildSummary($finalHop): array
    {
        if ($finalHop === null) {
            return [
                'final_status' => null,
                'security' => [],
                'hints' => ['Не удалось получить ответ сервера'],
            ];
        }

        $status = (int) ($finalHop['status'] ?? 0);
        $headerMap = [];
        foreach ($finalHop['headers'] ?? [] as $h) {
            $headerMap[strtolower($h['name'])] = $h['value'];
        }

        $security = [
            'hsts' => isset($headerMap['strict-transport-security']),
            'csp' => isset($headerMap['content-security-policy']),
            'x_frame' => isset($headerMap['x-frame-options']),
            'x_content_type' => isset($headerMap['x-content-type-options']),
        ];

        $hints = [];
        if ($status >= 400) {
            $hints[] = 'Код ответа ' . $status . ' — страница недоступна или ошибка на сервере';
        }
        if ($status >= 200 && $status < 300 && !isset($headerMap['cache-control'])) {
            $hints[] = 'Нет Cache-Control — проверьте кэширование статики';
        }
        if ($status >= 200 && $status < 300 && !isset($headerMap['content-encoding'])) {
            $hints[] = 'Нет Content-Encoding — возможно, ответ не сжат (gzip/br)';
        }
        if ($status >= 200 && $status < 300 && !$security['hsts'] && strpos((string) ($headerMap['location'] ?? ''), 'https') !== 0) {
            $hints[] = 'Нет Strict-Transport-Security — усилите HTTPS';
        }

        return [
            'final_status' => $status > 0 ? $status : null,
            'security' => $security,
            'hints' => $hints,
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
