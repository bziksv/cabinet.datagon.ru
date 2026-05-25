<?php

namespace App\Services\Demo;

use App\Classes\Xml\SimplifiedXmlFacade;
use App\Support\YandexLrRegions;

class CompetitorAnalysisDemoService
{
    public const MODULE = 'analiz-konkurentov';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-competitor-analysis.demo', []);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function regionsForUi(): array
    {
        $cfg = self::config();
        $ids = $cfg['allowed_region_ids'] ?? ['213', '2', '193', '65', '54'];
        $out = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            $item = YandexLrRegions::find($id);
            $out[] = [
                'id' => $id,
                'label' => $item['name'] ?? $id,
            ];
        }

        return $out;
    }

    /**
     * @param array{phrase?: string, region_id?: string} $input
     * @return array{ok: true, payload: array<string, mixed>}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $cfg = self::config();
        $phrase = trim((string) ($input['phrase'] ?? ''));
        $minLen = (int) ($cfg['min_phrase_length'] ?? 2);
        $maxLen = (int) ($cfg['max_phrase_length'] ?? 120);

        if ($phrase === '') {
            return self::fail(422, 'validation', 'Введите ключевую фразу');
        }
        if (mb_strlen($phrase) < $minLen) {
            return self::fail(422, 'validation', sprintf('Минимум %d символа в фразе', $minLen));
        }
        if (mb_strlen($phrase) > $maxLen) {
            return self::fail(
                422,
                'validation',
                sprintf('В демо до %d символов в одной фразе. В кабинете — до 40 фраз за запуск.', $maxLen)
            );
        }

        $regionId = trim((string) ($input['region_id'] ?? ''));
        $allowed = array_map('strval', $cfg['allowed_region_ids'] ?? ['213']);
        if ($regionId === '' || !in_array($regionId, $allowed, true)) {
            return self::fail(422, 'validation', 'Выберите регион из списка');
        }

        return ['ok' => true, 'payload' => ['phrase' => $phrase, 'region_id' => $regionId]];
    }

    /**
     * @param array{phrase: string, region_id: string} $input
     * @return array<string, mixed>|array{ok: false, status: int, error: string, message: string}
     */
    public static function analyze(array $input): array
    {
        $cfg = self::config();
        $topCount = (int) ($cfg['top_count'] ?? 10);
        $serpLimit = (int) ($cfg['serp_rows'] ?? 10);
        $phrase = $input['phrase'];
        $regionId = $input['region_id'];

        $prevHybrid = config('cabinet-competitor-analysis.xmlstock_hybrid_retry');
        config(['cabinet-competitor-analysis.xmlstock_hybrid_retry' => false]);

        try {
            $xml = new SimplifiedXmlFacade($regionId, $topCount);
            $xml->setQuery($phrase);
            $urls = $xml->getXMLResponse('yandex');
        } finally {
            config(['cabinet-competitor-analysis.xmlstock_hybrid_retry' => $prevHybrid]);
        }

        if (!is_array($urls) || count($urls) === 0) {
            return self::fail(
                422,
                'empty_serp',
                'По этой фразе не удалось получить выдачу. Попробуйте другую формулировку или позже.'
            );
        }

        $rows = [];
        $position = 1;
        foreach (array_slice($urls, 0, $serpLimit) as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }
            $normalized = self::normalizeUrl($url);
            $host = parse_url($normalized, PHP_URL_HOST) ?: $normalized;
            $path = parse_url($normalized, PHP_URL_PATH) ?: '/';
            $rows[] = [
                'position' => $position,
                'url' => $normalized,
                'host' => $host,
                'path' => $path === '' ? '/' : $path,
            ];
            $position++;
        }

        if (count($rows) === 0) {
            return self::fail(422, 'empty_serp', 'Выдача пуста. Попробуйте другую фразу.');
        }

        $regionItem = YandexLrRegions::find($regionId);

        return [
            'phrase' => $phrase,
            'engine' => 'yandex',
            'region' => [
                'id' => $regionId,
                'label' => $regionItem['name'] ?? $regionId,
            ],
            'top_count' => $topCount,
            'serp' => [
                'rows' => $rows,
                'total' => count($urls),
                'shown' => count($rows),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $moduleSlug = (string) ($cfg['module_slug'] ?? self::MODULE);
        $registerBase = rtrim((string) config('app.url', 'https://lk.redbox.su'), '/');

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_phrase_length' => (int) ($cfg['max_phrase_length'] ?? 120),
                'max_runs_per_day' => (int) ($cfg['max_runs_per_day'] ?? 3),
                'top_count' => (int) ($cfg['top_count'] ?? 10),
                'serp_rows' => (int) ($cfg['serp_rows'] ?? 10),
                'regions' => self::regionsForUi(),
            ],
            'result' => [
                'phrase' => $result['phrase'],
                'engine' => $result['engine'],
                'region' => $result['region'],
                'top_count' => $result['top_count'],
                'serp' => $result['serp'],
                'locked' => self::lockedFeatures(),
            ],
            'upgrade' => [
                'register_url' => $registerBase . '/register?' . http_build_query([
                    'module' => $moduleSlug,
                    'from' => 'demo',
                    'guest' => $guestId,
                ]),
                'login_url' => $registerBase . '/login',
            ],
        ];
    }

    /**
     * @return string[]
     */
    private static function lockedFeatures(): array
    {
        return [
            'meta_tags',
            'geo_dependency',
            'recommendations',
            'multi_phrase',
            'multi_region',
            'google_engine',
            'site_parsing',
            'export_xls',
            'top_20_30',
        ];
    }

    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'error' => $error, 'message' => $message];
    }
}
