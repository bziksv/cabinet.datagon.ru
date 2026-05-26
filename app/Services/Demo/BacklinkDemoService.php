<?php

namespace App\Services\Demo;

use App\Services\Backlink\LinkAnalyser;
use App\Support\TextAnalyzerPdfBranding;

class BacklinkDemoService
{
    public const MODULE = 'otslezhivanie-ssylok';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-backlink.demo', []);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, donor: string, link: string, anchor: string, check_nofollow: bool, check_noindex: bool}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $donor = trim((string) ($input['donor'] ?? ''));
        $link = trim((string) ($input['link'] ?? ''));
        $anchor = trim((string) ($input['anchor'] ?? ''));

        if ($donor === '') {
            return self::fail(422, 'validation', 'Укажите страницу сайта-донора');
        }
        if ($link === '') {
            return self::fail(422, 'validation', 'Укажите ссылку, которую нужно найти на странице');
        }
        if ($anchor === '') {
            return self::fail(422, 'validation', 'Укажите текст анкора');
        }

        if (!preg_match('#^https?://#i', $donor)) {
            $donor = 'https://' . $donor;
        }
        $donorNormalized = self::normalizeUrl($donor);
        if ($donorNormalized === null) {
            return self::fail(422, 'validation', 'Адрес страницы донора должен быть корректным URL');
        }

        return [
            'ok' => true,
            'donor' => $donorNormalized,
            'link' => $link,
            'anchor' => $anchor,
            'check_nofollow' => (bool) ($input['check_nofollow'] ?? true),
            'check_noindex' => (bool) ($input['check_noindex'] ?? true),
        ];
    }

    /**
     * @param array{donor: string, link: string, anchor: string, check_nofollow: bool, check_noindex: bool} $validated
     * @return array<string, mixed>
     */
    public static function check(array $validated): array
    {
        $analyser = new LinkAnalyser();
        $raw = $analyser->analyse(
            $validated['donor'],
            $validated['link'],
            $validated['anchor'],
            $validated['check_nofollow'],
            $validated['check_noindex']
        );

        return self::formatResult($validated, $raw);
    }

    /**
     * @param array<string, mixed> $result
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
                'max_links_per_run' => 1,
            ],
            'result' => $result,
            'upgrade' => [
                'register_url' => url('/register?module=' . self::MODULE . '&from=demo&guest=' . urlencode($guestId)),
                'login_url' => TextAnalyzerPdfBranding::loginUrl(),
            ],
        ];
    }

    /**
     * @param array{donor: string, link: string, anchor: string, check_nofollow: bool, check_noindex: bool} $input
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function formatResult(array $input, array $raw): array
    {
        $checks = [];

        foreach ($raw['phrases'] as $phrase) {
            if ($phrase === '') {
                continue;
            }
            $checks[] = [
                'key' => $phrase,
                'label' => __(trim($phrase, '.')),
                'status' => self::phraseStatus($phrase),
            ];
        }

        $issuesCount = count(array_filter($checks, static function ($c) {
            return ($c['status'] ?? '') === 'issue';
        }));

        return [
            'donor_url' => $input['donor'],
            'target_link' => $input['link'],
            'anchor' => $input['anchor'],
            'check_nofollow' => $input['check_nofollow'],
            'check_noindex' => $input['check_noindex'],
            'ok' => (bool) ($raw['ok'] ?? false) && $issuesCount === 0,
            'checks' => $checks,
            'issues_count' => $issuesCount,
            'summary' => $issuesCount > 0
                ? 'Есть расхождения с заданными условиями'
                : 'Условия размещения выполняются',
        ];
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
            'message' => $message,
        ];
    }

    private static function phraseStatus(string $phrase): string
    {
        $issueNeedles = [
            'Link not found',
            'The donor site does not exist',
            'Link have attribute nofollow',
            'Link placed in noindex',
            'link placed in noindex',
        ];

        foreach ($issueNeedles as $needle) {
            if (stripos($phrase, $needle) !== false) {
                return 'issue';
            }
        }

        return 'ok';
    }

    private static function normalizeUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
