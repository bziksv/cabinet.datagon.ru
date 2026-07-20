<?php

namespace App\Services\Demo;

use App\Services\SiteTypesService;
use App\Support\TextAnalyzerPdfBranding;

class SiteTypesDemoService
{
    public const MODULE = 'tipy-saitov-v-vydache';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-site-types.demo', []);
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
            return self::fail(422, 'validation', 'Укажите поисковую фразу, например: купить диван');
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
        $maxRows = max(1, (int) ($cfg['max_rows'] ?? 10));

        $service = new SiteTypesService();
        $raw = $service->analyze([
            'phrases' => [$validated['phrase']],
            'engines' => [$validated['engine']],
            'depth' => $depth,
            'yandex_lr' => (string) ($cfg['yandex_lr'] ?? config('cabinet-site-types.default_yandex_lr', '213')),
            'google_lr' => (string) ($cfg['google_lr'] ?? config('cabinet-site-types.default_google_lr', '1011969')),
            'custom_domains' => [],
        ]);

        $query = $raw['queries'][0] ?? null;
        $rows = [];
        if (is_array($query)) {
            foreach (array_slice($query['rows'] ?? [], 0, $maxRows) as $row) {
                $rows[] = [
                    'position' => (int) ($row['position'] ?? 0),
                    'domain' => (string) ($row['domain'] ?? ''),
                    'url' => (string) ($row['url'] ?? ''),
                    'type' => (string) ($row['type'] ?? 'unknown'),
                    'in_catalog' => ! empty($row['in_catalog']),
                ];
            }
        }

        $summary = $raw['summary'] ?? [];
        $verdict = $summary['verdict'] ?? ['code' => 'empty', 'label' => 'Нет данных', 'hint' => ''];
        $mix = $summary['mix'] ?? [];
        $counts = $summary['counts'] ?? [];

        $mixTop = [];
        if (is_array($mix)) {
            arsort($mix);
            foreach (array_slice($mix, 0, 6, true) as $type => $share) {
                $mixTop[] = [
                    'type' => (string) $type,
                    'share' => (float) $share,
                    'count' => (int) ($counts[$type] ?? 0),
                ];
            }
        }

        $categories = [];
        foreach ($raw['categories'] ?? [] as $key => $meta) {
            $categories[$key] = [
                'label' => (string) ($meta['label'] ?? $key),
                'short' => (string) ($meta['short'] ?? $key),
                'color' => (string) ($meta['color'] ?? '#64748b'),
            ];
        }

        $hosts = [];
        foreach (array_slice($raw['frequent_hosts'] ?? [], 0, 5) as $host) {
            $hosts[] = [
                'host' => (string) ($host['host'] ?? ''),
                'count' => (int) ($host['count'] ?? 0),
                'type' => (string) ($host['type'] ?? 'unknown'),
            ];
        }

        return [
            'phrase' => $validated['phrase'],
            'engine' => $validated['engine'],
            'depth' => (int) ($raw['depth'] ?? $depth),
            'verdict' => [
                'code' => (string) ($verdict['code'] ?? 'empty'),
                'label' => (string) ($verdict['label'] ?? 'Нет данных'),
                'hint' => self::russianHint((string) ($verdict['hint'] ?? '')),
            ],
            'total_positions' => (int) ($summary['total_positions'] ?? count($rows)),
            'mix' => $mixTop,
            'rows' => $rows,
            'rows_shown' => count($rows),
            'rows_total' => is_array($query) ? (int) ($query['total'] ?? count($query['rows'] ?? [])) : 0,
            'truncated' => is_array($query) && count($query['rows'] ?? []) > $maxRows,
            'frequent_hosts' => $hosts,
            'categories' => $categories,
            'error' => is_array($query) && ! empty($query['error']),
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
                'max_phrases_per_run' => 1,
                'depth' => (int) ($cfg['depth'] ?? 10),
                'max_rows' => (int) ($cfg['max_rows'] ?? 10),
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

    private static function russianHint(string $hint): string
    {
        $hint = str_replace(['бренд-SERP', 'SERP', 'XML'], ['выдачу по бренду', 'выдачу', 'источник'], $hint);

        return $hint;
    }
}
