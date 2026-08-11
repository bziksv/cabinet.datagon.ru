<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;

/**
 * Сниппеты / TITLE ≠ выдаче / источник — только разбор общего XML-пакета (SiteAuditSerpUrlBatch).
 * Сами XML-запросы здесь не шлём.
 */
class SiteAuditSerpSnippetsProbe
{
    private const CODES = [
        'serp_snippets',
        'serp_title_mismatch',
        'serp_snippet_source',
        // legacy: дубль Вебмастера — больше не пишем, только чистим при пересъёме
        'serp_not_indexed',
    ];

    public function run(SiteAuditCrawl $crawl, bool $force = false): void
    {
        $enabled = $force || (bool) config('site_audit.serp_snippets_enabled', false);
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];

        if (! $enabled) {
            $progress['serp_snippets'] = [
                'skipped' => true,
                'reason' => 'disabled',
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', self::CODES)
            ->delete();

        $batch = (new SiteAuditSerpUrlBatch())->ensure($crawl, $force);
        if (! empty($batch['skipped']) || empty($batch['rows'])) {
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['serp_snippets'] = [
                'skipped' => true,
                'reason' => $batch['reason'] ?? 'no_urls',
                'max_urls' => (int) ($batch['max_urls'] ?? 0),
                'sampled' => 0,
                'errors' => (int) ($batch['errors'] ?? 0),
                'engines' => $batch['engines'] ?? [],
                'from_batch' => true,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $engines = is_array($batch['engines'] ?? null) ? $batch['engines'] : ['yandex', 'google'];
        $rowsOut = [];

        foreach ($batch['rows'] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = (string) ($item['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $pageTitle = isset($item['page_title']) ? (string) $item['page_title'] : null;
            if ($pageTitle === '') {
                $pageTitle = null;
            }
            $source = (string) ($item['source'] ?? 'crawl');
            $engineMeta = is_array($item['engines'] ?? null) ? $item['engines'] : [];

            $titleMismatches = [];
            foreach ($engines as $engine) {
                $block = is_array($engineMeta[$engine] ?? null) ? $engineMeta[$engine] : null;
                if (! $block) {
                    continue;
                }
                if (! empty($block['title_mismatch'])) {
                    $titleMismatches[$engine] = [
                        'serp_title' => $block['title'] ?? null,
                        'snippet' => $block['snippet'] ?? null,
                    ];
                }

                $indexed = ! empty($block['indexed']);
                $serpTitle = isset($block['title']) ? (string) $block['title'] : '';
                $snippet = isset($block['snippet']) ? (string) $block['snippet'] : '';
                if ($indexed && ($serpTitle !== '' || $snippet !== '')) {
                    $hint = $this->snippetSourceHint(
                        $serpTitle,
                        $snippet,
                        $pageTitle,
                        isset($item['page_description']) ? (string) $item['page_description'] : null,
                        isset($item['page_h1']) ? (string) $item['page_h1'] : null
                    );
                    if ($hint !== null) {
                        $cfg = config('site_audit.findings.serp_snippet_source', []);
                        SiteAuditFinding::query()->create([
                            'crawl_id' => $crawl->id,
                            'code' => 'serp_snippet_source',
                            'severity' => $cfg['severity'] ?? 'info',
                            'url' => $url,
                            'url_hash' => SiteAuditUrlNormalizer::hash($url),
                            'meta_json' => [
                                'engine' => $engine,
                                'title_source' => $hint['title_source'],
                                'snippet_source' => $hint['snippet_source'],
                                'serp_title' => $serpTitle !== '' ? $serpTitle : null,
                                'snippet' => $snippet !== '' ? $snippet : null,
                            ],
                        ]);
                    }
                }
            }

            if ($titleMismatches !== []) {
                $primaryEngine = array_key_first($titleMismatches);
                $primary = $titleMismatches[$primaryEngine];
                $cfg = config('site_audit.findings.serp_title_mismatch', []);
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => 'serp_title_mismatch',
                    'severity' => $cfg['severity'] ?? 'warning',
                    'url' => $url,
                    'url_hash' => SiteAuditUrlNormalizer::hash($url),
                    'meta_json' => [
                        'engine' => $primaryEngine,
                        'engines_mismatch' => array_keys($titleMismatches),
                        'source' => $source,
                        'page_title' => $pageTitle,
                        'serp_title' => $primary['serp_title'],
                        'snippet' => $primary['snippet'],
                        'engines' => $engineMeta,
                    ],
                ]);
            }

            $cfg = config('site_audit.findings.serp_snippets', []);
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'serp_snippets',
                'severity' => $cfg['severity'] ?? 'info',
                'url' => $url,
                'url_hash' => SiteAuditUrlNormalizer::hash($url),
                'meta_json' => [
                    'source' => $source,
                    'page_title' => $pageTitle,
                    'engines' => $engineMeta,
                ],
            ]);

            $rowsOut[] = [
                'url' => $url,
                'source' => $source,
                'page_title' => $pageTitle,
                'engines' => $engineMeta,
            ];
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['serp_snippets'] = [
            'skipped' => false,
            'max_urls' => (int) ($batch['max_urls'] ?? 0),
            'sampled' => count($rowsOut),
            'errors' => (int) ($batch['errors'] ?? 0),
            'engines' => $engines,
            'rows' => $rowsOut,
            'from_batch' => true,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    /**
     * @return array{title_source:string,snippet_source:string}|null
     */
    private function snippetSourceHint(
        string $serpTitle,
        string $snippet,
        ?string $pageTitle,
        ?string $pageDescription,
        ?string $pageH1
    ): ?array {
        $titleSource = $this->matchField($serpTitle, [
            'title' => $pageTitle,
            'h1' => $pageH1,
            'description' => $pageDescription,
        ]);
        $snippetSource = $this->matchField($snippet, [
            'description' => $pageDescription,
            'title' => $pageTitle,
            'h1' => $pageH1,
        ]);

        if ($titleSource === 'unknown' && $snippetSource === 'unknown') {
            return null;
        }

        return [
            'title_source' => $titleSource,
            'snippet_source' => $snippetSource,
        ];
    }

    /**
     * @param array<string,?string> $fields
     */
    private function matchField(string $serp, array $fields): string
    {
        $serpN = $this->normTitle($serp);
        if ($serpN === '') {
            return 'unknown';
        }
        foreach ($fields as $name => $val) {
            if ($val === null || trim($val) === '') {
                continue;
            }
            $pageN = $this->normTitle($val);
            if ($pageN === '') {
                continue;
            }
            if ($serpN === $pageN || mb_strpos($pageN, $serpN) !== false || mb_strpos($serpN, $pageN) !== false) {
                return (string) $name;
            }
            if (mb_strlen($serpN) >= 12 && mb_strlen($pageN) >= 12) {
                similar_text($serpN, $pageN, $pct);
                if ($pct >= 78.0) {
                    return (string) $name;
                }
            }
        }

        return 'unknown';
    }

    private function normTitle(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = preg_replace('/\s+/u', ' ', $t) ?: $t;

        return $t;
    }
}
