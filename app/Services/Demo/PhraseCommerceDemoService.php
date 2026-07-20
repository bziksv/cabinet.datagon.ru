<?php

namespace App\Services\Demo;

use App\Services\PhraseCommerceService;
use App\Support\TextAnalyzerPdfBranding;

class PhraseCommerceDemoService
{
    public const MODULE = 'geo-lokalizaciya-kommerciya';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-phrase-commerce.demo', []);
    }

    /**
     * @param array{phrase?: string, engine?: string} $input
     * @return array{ok: true, phrase: string, engine: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $cfg = self::config();
        $phrase = trim(preg_replace('/\s+/u', ' ', (string) ($input['phrase'] ?? '')) ?? '');
        $maxChars = (int) ($cfg['max_phrase_chars'] ?? 80);

        if ($phrase === '') {
            return self::fail(422, 'validation', 'Укажите поисковую фразу, например: купить диван москва');
        }

        if (mb_strlen($phrase, 'UTF-8') > $maxChars) {
            return self::fail(422, 'validation', 'Фраза слишком длинная для демо (максимум ' . $maxChars . ' символов)');
        }

        $engine = strtolower(trim((string) ($input['engine'] ?? 'yandex')));
        if (! in_array($engine, ['yandex', 'google'], true)) {
            return self::fail(422, 'validation', 'Выберите Яндекс или Google');
        }

        return [
            'ok' => true,
            'phrase' => $phrase,
            'engine' => $engine,
        ];
    }

    /**
     * @param array{phrase: string, engine: string} $validated
     * @return array<string, mixed>
     */
    public static function analyze(array $validated): array
    {
        $cfg = self::config();
        $depth = max(3, (int) ($cfg['depth'] ?? 10));
        $prevDepth = config('cabinet-phrase-commerce.depth');
        config(['cabinet-phrase-commerce.depth' => $depth]);

        try {
            $service = new PhraseCommerceService();
            $raw = $service->analyze([
                'phrases' => [$validated['phrase']],
                'engines' => [$validated['engine']],
                'yandex_lr' => (string) ($cfg['yandex_lr'] ?? config('cabinet-phrase-commerce.default_yandex_lr', '213')),
                'google_lr' => (string) ($cfg['google_lr'] ?? config('cabinet-phrase-commerce.default_google_lr', '1011969')),
            ]);
        } finally {
            config(['cabinet-phrase-commerce.depth' => $prevDepth]);
        }

        $row = $raw['rows'][0] ?? null;
        if (! is_array($row)) {
            throw new \RuntimeException('empty_result');
        }

        $geo = $row['geo'] ?? [];
        $localization = $row['localization'] ?? [];
        $commerce = $row['commerce'] ?? [];

        return [
            'phrase' => $validated['phrase'],
            'engine' => $validated['engine'],
            'depth' => (int) ($raw['depth'] ?? $depth),
            'region_name' => (string) ($row['region_name'] ?? ''),
            'region_contrast_name' => (string) ($row['region_contrast_name'] ?? ''),
            'geo' => [
                'code' => (string) ($geo['code'] ?? 'unknown'),
                'label' => (string) ($geo['label'] ?? 'Нет данных'),
                'overlap_pct' => (int) ($geo['overlap_pct'] ?? 0),
                'shared' => (int) ($geo['shared'] ?? count($geo['shared_hosts'] ?? [])),
                'incomplete' => ! empty($geo['incomplete']),
            ],
            'localization' => [
                'code' => (string) ($localization['code'] ?? 'low'),
                'label' => (string) ($localization['label'] ?? 'Низкая'),
                'pct' => (float) ($localization['pct'] ?? 0),
                'local' => (int) ($localization['local'] ?? 0),
                'total' => (int) ($localization['total'] ?? 0),
            ],
            'commerce' => [
                'code' => (string) ($commerce['code'] ?? 'mixed'),
                'label' => (string) ($commerce['label'] ?? 'Смешанный'),
                'pct' => (float) ($commerce['pct'] ?? 0),
                'commercial' => (int) ($commerce['commercial'] ?? 0),
                'total' => (int) ($commerce['total'] ?? 0),
            ],
            'positions' => (int) ($row['serp_count'] ?? 0),
            'error' => ! empty($row['error']),
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 2);

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_runs_per_day' => $maxRuns,
                'max_phrases_per_run' => 1,
                'depth' => (int) ($cfg['depth'] ?? 10),
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
