<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditPage;
use Illuminate\Support\Collection;

/**
 * Пакетный поиск URL в инвентаре страниц проверки.
 * Сохраняет порядок ввода; ненайденные тоже попадают в выдачу с пометкой.
 */
class SiteAuditBatchUrlLookup
{
    public const MAX_LINES = 500;

    /**
     * @return array{0:int,1:Collection<int,object>,2:array{input:int,found:int,missing:int,batch:string}}
     */
    public static function paginate(
        SiteAuditCrawl $crawl,
        string $batchText,
        int $page,
        int $perPage,
        ?string $sort = null,
        ?string $dir = null
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        $queries = self::parseLines($batchText);
        $stats = [
            'input' => count($queries),
            'found' => 0,
            'missing' => 0,
            'batch' => implode("\n", $queries),
        ];

        if ($queries === []) {
            return [0, collect(), $stats];
        }

        $crawl->loadMissing('project');
        $baseHost = self::projectHost($crawl);

        $index = self::buildIndex((int) $crawl->id);
        $matched = [];
        foreach ($queries as $query) {
            $pageId = self::matchQuery($query, $baseHost, $index);
            if ($pageId !== null) {
                $matched[] = ['query' => $query, 'page_id' => $pageId, 'missing' => false];
                $stats['found']++;
            } else {
                $matched[] = ['query' => $query, 'page_id' => null, 'missing' => true];
                $stats['missing']++;
            }
        }

        $total = count($matched);
        $slice = array_slice($matched, ($page - 1) * $perPage, $perPage);

        // Если сортировка запрошена — сортируем только найденные в полном списке,
        // ненайденные оставляем в исходном порядке относительно «своих» позиций сложно;
        // при активном пакете сохраняем порядок ввода (сортировка колонок отключена в UI).
        unset($sort, $dir);

        $needIds = [];
        foreach ($slice as $item) {
            if (! empty($item['page_id'])) {
                $needIds[(int) $item['page_id']] = true;
            }
        }
        $pagesById = [];
        if ($needIds !== []) {
            $pages = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('id', array_keys($needIds))
                ->get();
            foreach ($pages as $p) {
                $pagesById[(int) $p->id] = $p;
            }
        }

        $severity = (string) config('site_audit.findings.crawl_pages.severity', 'info');
        $rows = collect();
        foreach ($slice as $item) {
            if (! empty($item['missing']) || empty($item['page_id']) || empty($pagesById[(int) $item['page_id']])) {
                $rows->push(self::missingRow((string) $item['query'], $severity));
                continue;
            }
            /** @var SiteAuditPage $pageModel */
            $pageModel = $pagesById[(int) $item['page_id']];
            $row = SiteAuditInventory::pageToRowPublic($pageModel, SiteAuditInventory::SOURCE_PAGES, 'crawl_pages', $severity);
            $meta = is_array($row->meta_json) ? $row->meta_json : [];
            $meta['batch_query'] = $item['query'];
            $meta['batch_status'] = 'found';
            $row->meta_json = $meta;
            $rows->push($row);
        }

        return [$total, $rows, $stats];
    }

    /**
     * @return list<string>
     */
    public static function parseLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $out = [];
        $seen = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            // Вытащить URL из строки вида "1. https://…" или со пробелами.
            if (preg_match('~https?://[^\s<>"\']+~i', $line, $m)) {
                $line = rtrim($m[0], '.,);]');
            } elseif (preg_match('~^/+[^\s]*~', $line, $m)) {
                $line = $m[0];
            }
            $key = mb_strtolower($line);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $line;
            if (count($out) >= self::MAX_LINES) {
                break;
            }
        }

        return $out;
    }

    private static function projectHost(SiteAuditCrawl $crawl): string
    {
        $domain = trim((string) optional($crawl->project)->domain);
        $domain = preg_replace('~^https?://~i', '', $domain) ?: '';
        $domain = rtrim($domain, '/');
        if ($domain === '') {
            return '';
        }
        $parts = explode('/', $domain);

        return strtolower($parts[0]);
    }

    /**
     * @return array{by_url:array<string,int>,by_hash:array<string,int>,by_canon:array<string,int>,by_path:array<string,int>}
     */
    private static function buildIndex(int $crawlId): array
    {
        $byUrl = [];
        $byHash = [];
        $byCanon = [];
        $byPath = [];

        SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->orderBy('id')
            ->select(['id', 'url', 'url_hash'])
            ->chunkById(500, function ($chunk) use (&$byUrl, &$byHash, &$byCanon, &$byPath) {
                foreach ($chunk as $p) {
                    $id = (int) $p->id;
                    $url = (string) $p->url;
                    $byUrl[mb_strtolower($url)] = $id;
                    $hash = (string) ($p->url_hash ?? '');
                    if ($hash !== '') {
                        $byHash[$hash] = $id;
                    }
                    $canon = SiteAuditUrlNormalizer::canonicalKey($url);
                    if ($canon !== null && $canon !== '') {
                        if (! isset($byCanon[$canon])) {
                            $byCanon[$canon] = $id;
                        }
                    }
                    $pathKey = self::pathKey($url);
                    if ($pathKey !== null && ! isset($byPath[$pathKey])) {
                        $byPath[$pathKey] = $id;
                    }
                }
            });

        return [
            'by_url' => $byUrl,
            'by_hash' => $byHash,
            'by_canon' => $byCanon,
            'by_path' => $byPath,
        ];
    }

    /**
     * @param  array{by_url:array<string,int>,by_hash:array<string,int>,by_canon:array<string,int>,by_path:array<string,int>}  $index
     */
    private static function matchQuery(string $query, string $baseHost, array $index): ?int
    {
        $candidates = self::expandCandidates($query, $baseHost);
        foreach ($candidates as $cand) {
            $lower = mb_strtolower($cand);
            if (isset($index['by_url'][$lower])) {
                return $index['by_url'][$lower];
            }
            $hash = hash('sha256', $cand);
            if (isset($index['by_hash'][$hash])) {
                return $index['by_hash'][$hash];
            }
            // hash sometimes from normalized form as stored
            $hashLower = hash('sha256', $lower);
            if (isset($index['by_hash'][$hashLower])) {
                return $index['by_hash'][$hashLower];
            }
            $canon = SiteAuditUrlNormalizer::canonicalKey($cand);
            if ($canon !== null && isset($index['by_canon'][$canon])) {
                return $index['by_canon'][$canon];
            }
            $pathKey = self::pathKey($cand);
            if ($pathKey !== null && isset($index['by_path'][$pathKey])) {
                return $index['by_path'][$pathKey];
            }
        }

        // Путь без схемы: /page
        if (strpos($query, '/') === 0) {
            $pathKey = self::pathKey('https://x.invalid' . $query);
            if ($pathKey !== null && isset($index['by_path'][$pathKey])) {
                return $index['by_path'][$pathKey];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function expandCandidates(string $query, string $baseHost): array
    {
        $out = [];
        $add = static function (string $u) use (&$out) {
            $u = trim($u);
            if ($u !== '') {
                $out[$u] = true;
            }
        };

        $add($query);

        $withScheme = $query;
        if (! preg_match('~^https?://~i', $query)) {
            if ($baseHost !== '') {
                $path = $query;
                if (strpos($path, '/') !== 0) {
                    // domain/path or bare path-ish
                    if (stripos($path, $baseHost) === 0) {
                        $withScheme = 'https://' . ltrim($path, '/');
                    } else {
                        $withScheme = 'https://' . $baseHost . '/' . ltrim($path, '/');
                    }
                } else {
                    $withScheme = 'https://' . $baseHost . $path;
                }
                $add($withScheme);
            }
        }

        if ($baseHost !== '') {
            $norm = SiteAuditUrlNormalizer::normalize($withScheme, $baseHost, [
                'prefer_host' => $baseHost,
                'force_https' => true,
            ]);
            if ($norm) {
                $add($norm);
            }
            $normSlash = SiteAuditUrlNormalizer::normalize($withScheme, $baseHost, [
                'prefer_host' => $baseHost,
                'force_https' => true,
                'strip_trailing_slash' => true,
            ]);
            if ($normSlash) {
                $add($normSlash);
            }
            // www ↔ без www
            $bare = preg_replace('/^www\./', '', $baseHost);
            foreach ([$bare, 'www.' . $bare] as $hostVar) {
                $n = SiteAuditUrlNormalizer::normalize($withScheme, $hostVar, [
                    'prefer_host' => $hostVar,
                    'force_https' => true,
                ]);
                if ($n) {
                    $add($n);
                }
            }
        }

        return array_keys($out);
    }

    private static function pathKey(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $path = strtolower($path);
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'yclid'] as $drop) {
                unset($query[$drop]);
            }
            ksort($query);
        }
        if ($query === []) {
            return $path;
        }

        return $path . '?' . http_build_query($query);
    }

    private static function missingRow(string $query, string $severity): object
    {
        $display = $query;
        if (! preg_match('~^https?://~i', $display) && strpos($display, '/') === 0) {
            // keep path as-is for display
        }

        return (object) [
            'id' => null,
            'url' => $display,
            'url_hash' => null,
            'severity' => $severity,
            'code' => 'crawl_pages',
            'meta_json' => [
                'batch_query' => $query,
                'batch_status' => 'missing',
                'status_code' => null,
                'title' => null,
                'description' => null,
                'headings' => null,
                'headings_complete' => false,
            ],
        ];
    }
}
