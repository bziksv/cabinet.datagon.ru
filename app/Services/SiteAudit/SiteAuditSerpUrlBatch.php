<?php

namespace App\Services\SiteAudit;

use App\Services\IndexCheckService;
use App\SiteAuditCrawl;
use App\SiteAuditPage;
use Illuminate\Support\Facades\Log;

/**
 * Один XML-съём по URL на проверку: выборка страниц + IndexCheckService::check() один раз.
 * Отчёты «Сниппеты / TITLE ≠ выдаче / источник» только разбирают этот пакет.
 *
 * Каннибализация по запросам — другой тип XML (searchQuery), сюда не входит.
 */
class SiteAuditSerpUrlBatch
{
    public const PROGRESS_KEY = 'serp_url_batch';

    /**
     * @return array{
     *   skipped?: bool,
     *   reason?: string,
     *   max_urls: int,
     *   engines: list<string>,
     *   errors: int,
     *   sampled: int,
     *   rows: list<array<string,mixed>>
     * }
     */
    public function ensure(SiteAuditCrawl $crawl, bool $force = false): array
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $existing = is_array($progress[self::PROGRESS_KEY] ?? null) ? $progress[self::PROGRESS_KEY] : null;

        if (! $force && is_array($existing) && ! empty($existing['rows']) && empty($existing['skipped'])) {
            return $existing;
        }

        return $this->collect($crawl);
    }

    /**
     * @return array<string,mixed>
     */
    public function collect(SiteAuditCrawl $crawl): array
    {
        $max = max(1, (int) config('site_audit.serp_snippets_max_urls', 30));
        $engines = $this->engines();

        $sample = $this->sampleUrls($crawl, $max);
        if ($sample === []) {
            $batch = [
                'skipped' => true,
                'reason' => 'no_urls',
                'max_urls' => $max,
                'engines' => $engines,
                'errors' => 0,
                'sampled' => 0,
                'rows' => [],
            ];
            $this->store($crawl, $batch);

            return $batch;
        }

        $checkYandex = in_array('yandex', $engines, true);
        $checkGoogle = in_array('google', $engines, true);
        $rows = [];
        $errors = 0;

        foreach ($sample as $item) {
            $url = (string) $item['url'];
            try {
                $check = IndexCheckService::check($url, [
                    'yandex' => $checkYandex,
                    'google' => $checkGoogle,
                    'unify_www' => true,
                ]);
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('SiteAudit SERP URL batch check failed: ' . $e->getMessage(), [
                    'crawl_id' => $crawl->id,
                    'url' => $url,
                ]);
                continue;
            }

            $pageTitle = $item['page_title'];
            if (($pageTitle === null || $pageTitle === '') && ! empty($check['page_title'])) {
                $pageTitle = (string) $check['page_title'];
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
                $titleDiffers = $indexed && $pageTitle && $serpTitle
                    && $this->titlesDiffer((string) $pageTitle, $serpTitle);

                $engineMeta[$engine] = [
                    'indexed' => $indexed,
                    'matched_url' => $block['matched_url'] ?? null,
                    'title' => $serpTitle !== '' ? $serpTitle : null,
                    'snippet' => $snippet !== '' ? $snippet : null,
                    'error' => $block['error'] ?? null,
                    'title_match' => $indexed && $pageTitle && $serpTitle && ! $titleDiffers,
                    'title_mismatch' => $titleDiffers,
                ];
            }

            $rows[] = [
                'url' => $url,
                'url_hash' => SiteAuditUrlNormalizer::hash($url),
                'source' => (string) ($item['source'] ?? 'crawl'),
                'page_title' => $pageTitle,
                'page_description' => $item['page_description'] ?? null,
                'page_h1' => $item['page_h1'] ?? null,
                'engines' => $engineMeta,
            ];
        }

        $batch = [
            'skipped' => false,
            'max_urls' => $max,
            'engines' => $engines,
            'errors' => $errors,
            'sampled' => count($rows),
            'rows' => $rows,
            'collected_at' => now()->toDateTimeString(),
        ];
        $this->store($crawl, $batch);

        return $batch;
    }

    /**
     * @param array<string,mixed> $batch
     */
    private function store(SiteAuditCrawl $crawl, array $batch): void
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress[self::PROGRESS_KEY] = $batch;
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    /**
     * @return list<string>
     */
    private function engines(): array
    {
        $engines = config('site_audit.serp_snippets_engines', ['yandex', 'google']);
        if (! is_array($engines) || $engines === []) {
            $engines = ['yandex', 'google'];
        }
        $engines = array_values(array_intersect(
            array_map('strtolower', $engines),
            ['yandex', 'google']
        ));

        return $engines !== [] ? $engines : ['yandex', 'google'];
    }

    /**
     * @return list<array{url: string, page_title: ?string, page_description: ?string, page_h1: ?string, source: string}>
     */
    public function sampleUrls(SiteAuditCrawl $crawl, int $max): array
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
