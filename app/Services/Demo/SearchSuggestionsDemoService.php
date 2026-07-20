<?php

namespace App\Services\Demo;

use App\Services\SearchSuggestionsService;
use App\Support\TextAnalyzerPdfBranding;

class SearchSuggestionsDemoService
{
    public const MODULE = 'sbor-poiskovykh-podskazok';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-search-suggestions.demo', []);
    }

    /**
     * @param array{seed?: string, engine?: string} $input
     * @return array{ok: true, seed: string, engine: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $cfg = self::config();
        $seed = trim(preg_replace('/\s+/u', ' ', (string) ($input['seed'] ?? '')) ?? '');
        $maxChars = (int) ($cfg['max_seed_chars'] ?? 80);

        if ($seed === '') {
            return self::fail(422, 'validation', 'Укажите исходную фразу для сбора подсказок');
        }

        if (mb_strlen($seed, 'UTF-8') > $maxChars) {
            return self::fail(422, 'validation', 'Фраза слишком длинная для демо (максимум ' . $maxChars . ' символов)');
        }

        $engine = strtolower(trim((string) ($input['engine'] ?? 'yandex')));
        if (! in_array($engine, ['yandex', 'google'], true)) {
            return self::fail(422, 'validation', 'Выберите Яндекс или Google');
        }

        return [
            'ok' => true,
            'seed' => $seed,
            'engine' => $engine,
        ];
    }

    /**
     * @param array{seed: string, engine: string} $validated
     * @return array<string, mixed>
     */
    public static function collect(array $validated): array
    {
        $cfg = self::config();
        $maxRows = max(1, (int) ($cfg['max_rows'] ?? 20));

        $service = new SearchSuggestionsService();
        $raw = $service->collect([
            'seeds' => [$validated['seed']],
            'engines' => [$validated['engine']],
            'modes' => [
                'phrase' => true,
                'space' => false,
                'en' => false,
                'ru' => false,
                'digits' => false,
            ],
            'presets' => [],
            'stop_words' => [],
            'depth' => 1,
            'yandex_lr' => (string) ($cfg['yandex_lr'] ?? config('cabinet-search-suggestions.default_yandex_lr', '213')),
            'google_hl' => (string) ($cfg['google_hl'] ?? config('cabinet-search-suggestions.default_google_hl', 'ru')),
            'google_gl' => (string) ($cfg['google_gl'] ?? config('cabinet-search-suggestions.default_google_gl', 'ru')),
        ]);

        $rows = array_slice($raw['results'] ?? [], 0, $maxRows);

        return [
            'seed' => $validated['seed'],
            'engine' => $validated['engine'],
            'rows' => $rows,
            'results_count' => count($rows),
            'total_found' => count($raw['results'] ?? []),
            'cost' => (int) ($raw['cost'] ?? 1),
            'truncated' => ! empty($raw['truncated']) || count($raw['results'] ?? []) > $maxRows,
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
                'max_seeds_per_run' => 1,
                'max_rows' => (int) ($cfg['max_rows'] ?? 20),
                'depth' => 1,
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
