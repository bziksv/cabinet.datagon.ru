<?php

namespace App\Services\SiteAudit;

use App\Services\IndexCheckService;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Support\Facades\Log;

/**
 * Сниппеты Яндекс/Google для посадочных (и добор с краула) через IndexCheckService.
 * По умолчанию выкл.: SITE_AUDIT_SERP_SNIPPETS=1 (XML-бюджет).
 */
class SiteAuditSerpSnippetsProbe
{
    private const CODES = [
        'serp_snippets',
        'serp_title_mismatch',
        'serp_not_indexed',
        'serp_snippet_source',
    ];

    public function run(SiteAuditCrawl $crawl): void
    {
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', self::CODES)
            ->delete();

        $enabled = (bool) config('site_audit.serp_snippets_enabled', false);
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

        $max = max(1, (int) config('site_audit.serp_snippets_max_urls', 12));
        $engines = config('site_audit.serp_snippets_engines', ['yandex', 'google']);
        if (! is_array($engines) || $engines === []) {
            $engines = ['yandex', 'google'];
        }
        $engines = array_values(array_intersect(
            array_map('strtolower', $engines),
            ['yandex', 'google']
        ));
        if ($engines === []) {
            $engines = ['yandex', 'google'];
        }

        $sample = $this->sampleUrls($crawl, $max);
        if ($sample === []) {
            $progress['serp_snippets'] = [
                'skipped' => true,
                'reason' => 'no_urls',
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $checkYandex = in_array('yandex', $engines, true);
        $checkGoogle = in_array('google', $engines, true);
        $unifyWww = true;

        $rows = [];
        $errors = 0;

        foreach ($sample as $item) {
            $url = $item['url'];
            $pageTitle = $item['page_title'];
            $source = $item['source'];

            try {
                $check = IndexCheckService::check($url, [
                    'yandex' => $checkYandex,
                    'google' => $checkGoogle,
                    'unify_www' => $unifyWww,
                ]);
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('SiteAudit serp snippets check failed: ' . $e->getMessage(), [
                    'crawl_id' => $crawl->id,
                    'url' => $url,
                ]);
                continue;
            }

            $engineMeta = [];
            foreach ($engines as $engine) {
                $block = is_array($check[$engine] ?? null) ? $check[$engine] : null;
                if (! $block) {
                    continue;
                }
                $indexed = ! empty($block['indexed']);
                $serpTitle = isset($block['title']) ? (string) $block['title'] : null;
                $snippet = isset($block['snippet']) ? (string) $block['snippet'] : null;
                $engineMeta[$engine] = [
                    'indexed' => $indexed,
                    'matched_url' => $block['matched_url'] ?? null,
                    'title' => $serpTitle !== '' ? $serpTitle : null,
                    'snippet' => $snippet !== '' ? $snippet : null,
                    'error' => $block['error'] ?? null,
                ];

                if (! $indexed && empty($block['error'])) {
                    $cfg = config('site_audit.findings.serp_not_indexed', []);
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawl->id,
                        'code' => 'serp_not_indexed',
                        'severity' => $cfg['severity'] ?? 'warning',
                        'url' => $url,
                        'url_hash' => SiteAuditUrlNormalizer::hash($url),
                        'meta_json' => [
                            'engine' => $engine,
                            'source' => $source,
                            'page_title' => $pageTitle,
                        ],
                    ]);
                }

                if ($indexed && $pageTitle && $serpTitle && $this->titlesDiffer($pageTitle, $serpTitle)) {
                    $cfg = config('site_audit.findings.serp_title_mismatch', []);
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawl->id,
                        'code' => 'serp_title_mismatch',
                        'severity' => $cfg['severity'] ?? 'warning',
                        'url' => $url,
                        'url_hash' => SiteAuditUrlNormalizer::hash($url),
                        'meta_json' => [
                            'engine' => $engine,
                            'source' => $source,
                            'page_title' => $pageTitle,
                            'serp_title' => $serpTitle,
                            'snippet' => $snippet !== '' ? $snippet : null,
                        ],
                    ]);
                }

                if ($indexed && ($serpTitle || $snippet)) {
                    $hint = $this->snippetSourceHint(
                        (string) $serpTitle,
                        (string) $snippet,
                        $pageTitle,
                        $item['page_description'] ?? null,
                        $item['page_h1'] ?? null
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
                                'serp_title' => $serpTitle,
                                'snippet' => $snippet !== '' ? $snippet : null,
                            ],
                        ]);
                    }
                }
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

            $rows[] = [
                'url' => $url,
                'source' => $source,
                'page_title' => $pageTitle,
                'engines' => $engineMeta,
            ];
        }

        $progress['serp_snippets'] = [
            'skipped' => false,
            'max_urls' => $max,
            'sampled' => count($rows),
            'errors' => $errors,
            'engines' => $engines,
            'rows' => $rows,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    /**
     * @return list<array{url: string, page_title: ?string, page_description: ?string, page_h1: ?string, source: string}>
     */
    private function sampleUrls(SiteAuditCrawl $crawl, int $max): array
    {
        $seen = [];
        $out = [];

        $add = function (string $url, ?string $title, string $source, ?string $description = null, ?string $h1 = null) use (&$seen, &$out, $max) {
            if (count($out) >= $max) {
                return;
            }
            $norm = IndexCheckService::normalizeUrl($url) ?: $url;
            $key = SiteAuditUrlNormalizer::hash($norm);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = [
                'url' => $norm,
                'page_title' => $title !== null && trim($title) !== '' ? trim($title) : null,
                'page_description' => $description !== null && trim($description) !== '' ? trim($description) : null,
                'page_h1' => $h1 !== null && trim($h1) !== '' ? trim($h1) : null,
                'source' => $source,
            ];
        };

        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        foreach ($resolved['urls'] as $url) {
            $page = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->where('url_hash', SiteAuditUrlNormalizer::hash($url))
                ->first(['title', 'description', 'h1']);
            $add(
                $url,
                $page ? $page->title : null,
                'landing',
                $page ? $page->description : null,
                $page ? $page->h1 : null
            );
            if (count($out) >= $max) {
                return $out;
            }
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->whereNotNull('url')
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhereBetween('status_code', [200, 399]);
            })
            ->orderByRaw('CASE WHEN click_depth IS NULL THEN 999 ELSE click_depth END')
            ->orderBy('id')
            ->limit($max * 2)
            ->get(['url', 'title', 'description', 'h1']);

        foreach ($pages as $page) {
            $add((string) $page->url, $page->title, 'crawl', $page->description, $page->h1);
            if (count($out) >= $max) {
                break;
            }
        }

        if ($out === [] && optional($crawl->project)->domain) {
            $home = 'https://' . preg_replace('#^https?://#i', '', rtrim($crawl->project->domain, '/')) . '/';
            $add($home, null, 'home');
        }

        return $out;
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

    private function titlesDiffer(string $pageTitle, string $serpTitle): bool
    {
        $a = $this->normTitle($pageTitle);
        $b = $this->normTitle($serpTitle);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return false;
        }
        // короткие — строгое сравнение уже выше
        if (mb_strlen($a) < 12 || mb_strlen($b) < 12) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct < 72.0;
    }

    private function normTitle(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = preg_replace('/\s+/u', ' ', $t) ?: $t;

        return $t;
    }
}
