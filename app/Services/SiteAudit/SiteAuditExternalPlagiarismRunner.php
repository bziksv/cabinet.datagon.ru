<?php

namespace App\Services\SiteAudit;

use App\Services\TextUniquenessService;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use App\Support\TextUniquenessLimits;
use App\User;
use Illuminate\Support\Facades\Cache;

/**
 * Внешний антиплагиат по выбранным URL краула (не в каждом aggregate).
 * Движок: Titlo TextUniqueness (шинглы + SERP), тарификация TextUniqueness.
 */
class SiteAuditExternalPlagiarismRunner
{
    public const PROGRESS_KEY = 'plagiarism_external';

    public const FINDING_CODE = 'landing_plagiarism_external';

    public function start(SiteAuditCrawl $crawl, User $user, array $urls): array
    {
        $urls = $this->normalizeSelectedUrls($crawl, $urls);
        if ($urls === []) {
            throw new \InvalidArgumentException('Выберите хотя бы один URL из этого краула');
        }

        $max = max(1, (int) config('site_audit.plagiarism_external_max_urls', 10));
        if (count($urls) > $max) {
            throw new \InvalidArgumentException('Максимум ' . $max . ' URL за запуск');
        }

        $lockKey = 'site_audit_plagiarism_' . $crawl->id;
        if (! Cache::add($lockKey, 1, 1200)) {
            throw new \RuntimeException('Проверка уже запущена для этого краула');
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $state = is_array($progress[self::PROGRESS_KEY] ?? null) ? $progress[self::PROGRESS_KEY] : [];
        if (in_array(($state['status'] ?? ''), ['queued', 'running'], true)) {
            Cache::forget($lockKey);
            throw new \RuntimeException('Проверка уже выполняется');
        }

        $progress[self::PROGRESS_KEY] = [
            'status' => 'queued',
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'urls' => $urls,
            'done' => 0,
            'total' => count($urls),
            'cost_spent' => 0,
            'rows' => [],
            'error' => null,
            'user_id' => (int) $user->id,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        Cache::forget($lockKey);

        \App\Jobs\SiteAudit\RunSiteAuditExternalPlagiarismJob::dispatch($crawl->id);

        return $progress[self::PROGRESS_KEY];
    }

    public function run(SiteAuditCrawl $crawl): void
    {
        $lockKey = 'site_audit_plagiarism_run_' . $crawl->id;
        if (! Cache::add($lockKey, 1, 1200)) {
            return;
        }

        try {
            $crawl->refresh();
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $state = is_array($progress[self::PROGRESS_KEY] ?? null) ? $progress[self::PROGRESS_KEY] : null;
            if (! $state || ! in_array(($state['status'] ?? ''), ['queued', 'running'], true)) {
                return;
            }

            $userId = (int) ($state['user_id'] ?? 0);
            $user = $userId > 0 ? User::query()->find($userId) : null;
            if (! $user) {
                $this->fail($crawl, 'Нет пользователя для списания лимита уникальности');

                return;
            }

            $urls = array_values(array_filter(array_map('strval', $state['urls'] ?? [])));
            $state['status'] = 'running';
            $state['done'] = 0;
            $state['rows'] = [];
            $state['cost_spent'] = 0;
            $state['error'] = null;
            $this->saveState($crawl, $state);

            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', self::FINDING_CODE)
                ->delete();

            $warnBelow = (float) config('site_audit.plagiarism_external_warn_below', 70);
            $cfg = config('site_audit.findings.' . self::FINDING_CODE, []);
            $severity = $cfg['severity'] ?? 'warning';
            $domain = (string) optional($crawl->project)->domain;
            $excludeHosts = $this->excludeHostsForDomain($domain);
            $fetcher = SiteAuditFetcher::fromCrawlSettings(
                is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : [],
                $crawl->id
            );
            $parser = new SiteAuditHtmlParser();
            $service = new TextUniquenessService();
            $engine = (string) config('site_audit.plagiarism_external_engine', config('cabinet-text-uniqueness.default_engine', 'yandex'));
            $lr = (string) config('site_audit.plagiarism_external_yandex_lr', config('cabinet-text-uniqueness.default_yandex_lr', '213'));

            $pagesByUrl = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('url', $urls)
                ->get(['url', 'url_hash', 'title'])
                ->keyBy('url');

            foreach ($urls as $url) {
                $row = [
                    'url' => $url,
                    'uniqueness_pct' => null,
                    'matched_pct' => null,
                    'cost' => 0,
                    'sources' => [],
                    'error' => null,
                ];

                try {
                    $fetched = $fetcher->fetch($url);
                    $bodyPath = isset($fetched['body_path']) ? (string) $fetched['body_path'] : null;
                    try {
                        $body = SiteAuditBodyTemp::takeBody($fetched);
                        if (empty($fetched['ok']) || $body === null || $body === '') {
                            throw new \RuntimeException($fetched['error'] ?: ('HTTP ' . ($fetched['status_code'] ?? '?')));
                        }
                        $text = $parser->extractVisibleText($body);
                    } finally {
                        SiteAuditBodyTemp::release($bodyPath);
                        unset($body, $fetched);
                    }
                    $params = [
                        'mode' => 'internet',
                        'text' => $text,
                        'engine' => $engine,
                        'yandex_lr' => $lr,
                        'exclude_hosts' => $excludeHosts,
                        'force_compare_urls' => [$url],
                    ];
                    $cost = TextUniquenessService::estimateCost($params);
                    if (! TextUniquenessLimits::canSpend($cost, $user)) {
                        $row['error'] = TextUniquenessLimits::limitMessage($user) ?: 'Лимит уникальности исчерпан';
                        $state['rows'][] = $row;
                        $state['done'] = count($state['rows']);
                        $state['error'] = $row['error'];
                        $this->saveState($crawl, $state);
                        break;
                    }

                    $result = $service->analyze($params);
                    $spent = (int) ($result['cost'] ?? $cost);
                    TextUniquenessLimits::spend($spent, $user);
                    $state['cost_spent'] = (int) ($state['cost_spent'] ?? 0) + $spent;

                    $uniq = (float) ($result['uniqueness_pct'] ?? 100);
                    $matched = (float) ($result['matched_pct'] ?? max(0, 100 - $uniq));
                    $sources = [];
                    foreach (array_slice($result['sources'] ?? [], 0, 5) as $m) {
                        if (! is_array($m) || ! empty($m['is_own'])) {
                            continue;
                        }
                        $sources[] = [
                            'url' => (string) ($m['url'] ?? ''),
                            'overlap_pct' => (float) ($m['overlap_pct'] ?? 0),
                        ];
                    }

                    $row['uniqueness_pct'] = $uniq;
                    $row['matched_pct'] = $matched;
                    $row['cost'] = $spent;
                    $row['sources'] = $sources;

                    if ($uniq < $warnBelow) {
                        $page = $pagesByUrl->get($url);
                        $urlHash = $page ? (string) $page->url_hash : SiteAuditUrlNormalizer::hash($url);
                        SiteAuditFinding::query()->create([
                            'crawl_id' => $crawl->id,
                            'code' => self::FINDING_CODE,
                            'severity' => $severity,
                            'url' => $url,
                            'url_hash' => $urlHash,
                            'meta_json' => [
                                'uniqueness_pct' => $uniq,
                                'matched_pct' => $matched,
                                'warn_below' => $warnBelow,
                                'sources' => $sources,
                                'engine' => $engine,
                                'cost' => $spent,
                                'provider' => 'titlo_text_uniqueness',
                            ],
                        ]);
                    }
                } catch (\Throwable $e) {
                    $row['error'] = mb_substr($e->getMessage(), 0, 300);
                }

                $state['rows'][] = $row;
                $state['done'] = count($state['rows']);
                $this->saveState($crawl, $state);
            }

            $state['status'] = empty($state['error']) ? 'done' : 'done';
            $state['finished_at'] = now()->toDateTimeString();
            $this->saveState($crawl, $state);
            $this->refreshCounts($crawl);
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function state(SiteAuditCrawl $crawl): array
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];

        return is_array($progress[self::PROGRESS_KEY] ?? null)
            ? $progress[self::PROGRESS_KEY]
            : [
                'status' => 'idle',
                'done' => 0,
                'total' => 0,
                'rows' => [],
                'cost_spent' => 0,
            ];
    }

    /**
     * Кандидаты для UI: посадочные + страницы краула с текстом.
     *
     * @return array<int, array{url:string,title:?string,word_count:int,is_landing:bool}>
     */
    public function candidates(SiteAuditCrawl $crawl, int $limit = 80): array
    {
        $landingUrls = [];
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $landings = is_array($progress['landings']['urls'] ?? null) ? $progress['landings']['urls'] : null;
        if ($landings === null) {
            $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
            $landings = is_array($resolved['urls'] ?? null) ? $resolved['urls'] : [];
        }
        foreach ($landings as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $landingUrls[$u] = true;
            }
        }

        $minWords = max(20, (int) config('site_audit.thin_words', 150) / 2);
        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            })
            ->where(function ($q) use ($minWords) {
                $q->where('word_count', '>=', $minWords)
                    ->orWhereIn('url', array_keys($landingUrls) ?: ['__none__']);
            })
            ->orderByDesc('word_count')
            ->limit(max(20, $limit * 2))
            ->get(['url', 'title', 'word_count']);

        $out = [];
        $seen = [];
        foreach (array_keys($landingUrls) as $lu) {
            $page = $pages->firstWhere('url', $lu);
            $out[] = [
                'url' => $lu,
                'title' => $page ? $page->title : null,
                'word_count' => $page ? (int) $page->word_count : 0,
                'is_landing' => true,
            ];
            $seen[$lu] = true;
            if (count($out) >= $limit) {
                return $out;
            }
        }
        foreach ($pages as $page) {
            if (isset($seen[$page->url])) {
                continue;
            }
            $out[] = [
                'url' => $page->url,
                'title' => $page->title,
                'word_count' => (int) $page->word_count,
                'is_landing' => false,
            ];
            $seen[$page->url] = true;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<int, string> $urls
     * @return array<int, string>
     */
    private function normalizeSelectedUrls(SiteAuditCrawl $crawl, array $urls): array
    {
        $wanted = [];
        foreach ($urls as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $wanted[$u] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        return SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('url', array_keys($wanted))
            ->pluck('url')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function excludeHostsForDomain(string $domain): array
    {
        $domain = mb_strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0] ?? $domain;
        $domain = preg_replace('/:\d+$/', '', $domain);
        if ($domain === '') {
            return [];
        }
        $hosts = [$domain];
        if (strpos($domain, 'www.') === 0) {
            $hosts[] = substr($domain, 4);
        } else {
            $hosts[] = 'www.' . $domain;
        }

        return array_values(array_unique($hosts));
    }

    private function saveState(SiteAuditCrawl $crawl, array $state): void
    {
        $crawl->refresh();
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress[self::PROGRESS_KEY] = $state;
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    private function fail(SiteAuditCrawl $crawl, string $message): void
    {
        $state = $this->state($crawl);
        $state['status'] = 'failed';
        $state['error'] = $message;
        $state['finished_at'] = now()->toDateTimeString();
        $this->saveState($crawl, $state);
    }

    private function refreshCounts(SiteAuditCrawl $crawl): void
    {
        $crawl->refresh();
        $prev = is_array($crawl->counts_json) ? $crawl->counts_json : [];
        $keep = [];
        foreach (['pages_with_canonical', 'click_depth_max'] as $k) {
            if (isset($prev[$k])) {
                $keep[$k] = $prev[$k];
            }
        }

        $byCode = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->selectRaw('code, count(*) as c')
            ->groupBy('code')
            ->pluck('c', 'code')
            ->all();

        $buckets = ['critical' => 0, 'other' => 0, 'warning' => 0, 'info' => 0];
        $sevCounts = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->selectRaw('severity, count(*) as c')
            ->groupBy('severity')
            ->pluck('c', 'severity')
            ->all();
        foreach ($buckets as $k => $_) {
            $buckets[$k] = (int) ($sevCounts[$k] ?? 0);
        }

        $crawl->counts_json = $byCode + $keep;
        $crawl->buckets_json = $buckets;
        $crawl->save();
    }
}
