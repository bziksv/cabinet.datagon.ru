<?php

namespace App\Services\Demo;

use App\Services\IndexCheckService;
use App\Support\TextAnalyzerPdfBranding;

class IndexCheckDemoService
{
    public const MODULE = 'proverka-indeksacii';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-index-check.demo', []);
    }

    /**
     * @param array{url?: string, yandex?: bool, google?: bool, unify_www?: bool, google_domain?: string} $input
     * @return array{ok: true, url: string, yandex: bool, google: bool, unify_www: bool, google_domain: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $url = trim((string) ($input['url'] ?? ''));
        if ($url === '') {
            return self::fail(422, 'validation', 'Укажите URL страницы для проверки индексации');
        }

        $normalized = IndexCheckService::normalizeUrl($url);
        if ($normalized === null) {
            return self::fail(422, 'validation', 'URL должен быть корректным (http или https)');
        }

        $yandex = (bool) ($input['yandex'] ?? true);
        $google = (bool) ($input['google'] ?? true);
        if (! $yandex && ! $google) {
            return self::fail(422, 'validation', 'Выберите хотя бы одну поисковую систему');
        }

        $googleDomain = (string) ($input['google_domain'] ?? 'google.ru');
        $allowed = array_keys(config('cabinet-index-check.google_domains', []));
        if ($allowed !== [] && ! in_array($googleDomain, $allowed, true)) {
            $googleDomain = 'google.ru';
        }

        return [
            'ok' => true,
            'url' => $normalized,
            'yandex' => $yandex,
            'google' => $google,
            'unify_www' => (bool) ($input['unify_www'] ?? false),
            'google_domain' => $googleDomain,
        ];
    }

    /**
     * @param array{url: string, yandex: bool, google: bool, unify_www: bool, google_domain: string} $validated
     * @return array<string, mixed>
     */
    public static function check(array $validated): array
    {
        $googleDomains = config('cabinet-index-check.google_domains', []);
        $googleLr = $googleDomains[$validated['google_domain']] ?? config('cabinet-index-check.default_google_lr', '213');

        return IndexCheckService::check($validated['url'], [
            'yandex' => $validated['yandex'],
            'google' => $validated['google'],
            'unify_www' => $validated['unify_www'],
            'google_lr' => $googleLr,
            'yandex_lr' => config('cabinet-index-check.default_yandex_lr', '213'),
        ]);
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
                'max_urls_per_run' => (int) ($cfg['max_urls_per_run'] ?? 1),
                'cost_per_engine' => IndexCheckService::costPerEngine(),
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
