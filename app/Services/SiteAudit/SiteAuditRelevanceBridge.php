<?php

namespace App\Services\SiteAudit;

use App\RelevanceHistory;
use App\SiteAuditCrawl;
use Illuminate\Support\Facades\Route;

/**
 * Склейка Site Audit ↔ анализатор релевантности:
 * для посадочных из мониторинга ищем последний расчёт TF, иначе deep-link с prefill.
 */
class SiteAuditRelevanceBridge
{
    /**
     * @return list<array{
     *   url: string,
     *   query: string,
     *   monitoring_keyword_id: int,
     *   history_id: ?int,
     *   last_check: ?string,
     *   points: ?int,
     *   coverage: ?int,
     *   coverage_tf: ?int,
     *   position: ?int,
     *   history_url: ?string,
     *   analyze_url: string
     * }>
     */
    public function rowsForCrawl(SiteAuditCrawl $crawl, int $limit = 80): array
    {
        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        $byKeyword = is_array($resolved['by_keyword'] ?? null) ? $resolved['by_keyword'] : [];
        if ($byKeyword === [] || ! $crawl->user_id) {
            return [];
        }

        $items = [];
        $seen = [];
        foreach ($byKeyword as $kid => $info) {
            $url = trim((string) ($info['url'] ?? ''));
            $query = trim((string) ($info['query'] ?? ''));
            if ($url === '' || $query === '') {
                continue;
            }
            $key = mb_strtolower($query) . '|' . self::canonicalUrlKey($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                'url' => $url,
                'query' => $query,
                'monitoring_keyword_id' => (int) $kid,
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        if ($items === []) {
            return [];
        }

        $phrases = array_values(array_unique(array_map(static function ($i) {
            return $i['query'];
        }, $items)));

        $histories = RelevanceHistory::query()
            ->where('user_id', (int) $crawl->user_id)
            ->whereIn('phrase', $phrases)
            ->where(function ($q) {
                $q->where('calculate', 1)->orWhere('calculate', true);
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get([
                'id', 'phrase', 'main_link', 'last_check',
                'points', 'coverage', 'coverage_tf', 'position',
            ]);

        // phrase|urlKey → latest history
        $index = [];
        foreach ($histories as $h) {
            $pKey = mb_strtolower(trim((string) $h->phrase));
            $uKey = self::canonicalUrlKey((string) $h->main_link);
            $idx = $pKey . '|' . $uKey;
            if (! isset($index[$idx])) {
                $index[$idx] = $h;
            }
        }

        $canHistory = Route::has('show.history');
        $canAnalyze = Route::has('relevance-analysis');
        $out = [];
        foreach ($items as $item) {
            $idx = mb_strtolower($item['query']) . '|' . self::canonicalUrlKey($item['url']);
            $hit = $index[$idx] ?? null;
            // fallback: same phrase + host+path loose match
            if (! $hit) {
                $hit = $this->fuzzyFind($histories, $item['query'], $item['url']);
            }

            $analyzeUrl = $canAnalyze
                ? route('relevance-analysis', [
                    'link' => $item['url'],
                    'phrase' => $item['query'],
                ])
                : '#';

            $row = [
                'url' => $item['url'],
                'query' => $item['query'],
                'monitoring_keyword_id' => $item['monitoring_keyword_id'],
                'history_id' => null,
                'last_check' => null,
                'points' => null,
                'coverage' => null,
                'coverage_tf' => null,
                'position' => null,
                'history_url' => null,
                'analyze_url' => $analyzeUrl,
            ];

            if ($hit) {
                $row['history_id'] = (int) $hit->id;
                $row['last_check'] = (string) $hit->last_check;
                $row['points'] = $hit->points !== null ? (int) $hit->points : null;
                $row['coverage'] = $hit->coverage !== null ? (int) $hit->coverage : null;
                $row['coverage_tf'] = $hit->coverage_tf !== null ? (int) $hit->coverage_tf : null;
                $row['position'] = $hit->position !== null ? (int) $hit->position : null;
                $row['history_url'] = $canHistory ? route('show.history', $hit->id) : null;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Ключ для сравнения URL: без схемы/www/хвостового слэша, lowercase.
     */
    public static function canonicalUrlKey(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return mb_strtolower(rtrim($url, '/'));
        }
        $host = mb_strtolower((string) $parts['host']);
        $host = preg_replace('/^www\./', '', $host);
        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';

        return $host . $path . $query;
    }

    /**
     * @param \Illuminate\Support\Collection|iterable $histories
     */
    private function fuzzyFind($histories, string $phrase, string $url)
    {
        $pKey = mb_strtolower(trim($phrase));
        $want = self::canonicalUrlKey($url);
        foreach ($histories as $h) {
            if (mb_strtolower(trim((string) $h->phrase)) !== $pKey) {
                continue;
            }
            $got = self::canonicalUrlKey((string) $h->main_link);
            if ($got === $want) {
                return $h;
            }
            // path prefix soft: same host, landing contained
            if ($got !== '' && $want !== '' && (
                strpos($got, $want) === 0 || strpos($want, $got) === 0
            )) {
                return $h;
            }
        }

        return null;
    }
}
