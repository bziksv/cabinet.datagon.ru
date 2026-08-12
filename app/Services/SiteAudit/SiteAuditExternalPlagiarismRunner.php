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

    public function start(SiteAuditCrawl $crawl, User $user, array $urls, array $opts = []): array
    {
        $urls = $this->normalizeSelectedUrls($crawl, $urls);
        if ($urls === []) {
            throw new \InvalidArgumentException('Выберите хотя бы один URL из этой проверки');
        }

        $max = max(1, (int) config('site_audit.plagiarism_external_max_urls', 20));
        if (count($urls) > $max) {
            throw new \InvalidArgumentException('Максимум ' . $max . ' URL за запуск');
        }

        $lockKey = 'site_audit_plagiarism_' . $crawl->id;
        if (! Cache::add($lockKey, 1, 1200)) {
            throw new \RuntimeException('Проверка уже запущена для этой проверки');
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $state = is_array($progress[self::PROGRESS_KEY] ?? null) ? $progress[self::PROGRESS_KEY] : [];
        if (in_array(($state['status'] ?? ''), ['queued', 'running'], true)) {
            Cache::forget($lockKey);
            throw new \RuntimeException('Проверка уже выполняется');
        }

        // Повторный ручной запуск поверх авто: сохраняем прошлые результаты других URL.
        $source = (string) ($opts['source'] ?? 'manual');
        $roles = is_array($opts['roles'] ?? null) ? $opts['roles'] : [];
        $prevRows = [];
        if ($source === 'manual' && ($state['status'] ?? '') === 'done' && ! empty($state['rows']) && is_array($state['rows'])) {
            $checking = array_fill_keys($urls, true);
            foreach ($state['rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $u = (string) ($row['url'] ?? '');
                if ($u !== '' && ! isset($checking[$u])) {
                    $prevRows[] = $row;
                }
            }
        }
        $prevRoles = is_array($state['roles'] ?? null) ? $state['roles'] : [];
        if ($roles === [] && $prevRoles !== []) {
            $roles = $prevRoles;
        } elseif ($prevRoles !== []) {
            $roles = array_merge($prevRoles, $roles);
        }

        $progress[self::PROGRESS_KEY] = [
            'status' => 'queued',
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'urls' => $urls,
            'done' => 0,
            'total' => count($urls),
            'cost_spent' => (int) ($state['cost_spent'] ?? 0),
            'rows' => [],
            'prev_rows' => $prevRows,
            'error' => null,
            'user_id' => (int) $user->id,
            'source' => $source,
            'roles' => $roles,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        Cache::forget($lockKey);

        \App\Jobs\SiteAudit\RunSiteAuditExternalPlagiarismJob::dispatch($crawl->id);

        return $progress[self::PROGRESS_KEY];
    }

    /**
     * После обхода: главная + 1 категория + 1 товар/услуга (до 3 URL).
     * Не блокирует finalize — ставит job в очередь.
     */
    public function queueAutoSample(SiteAuditCrawl $crawl): void
    {
        if (! (bool) config('site_audit.plagiarism_external_auto', true)) {
            return;
        }
        if (\App\Support\DemoCabinet::isCurrentUser()) {
            return;
        }
        $ownerId = (int) ($crawl->user_id ?: optional($crawl->project)->user_id);
        if ($ownerId > 0) {
            $owner = User::query()->find($ownerId);
            if ($owner && \App\Support\DemoCabinet::isDemoUser($owner)) {
                return;
            }
        }

        $crawl->refresh();
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $state = is_array($progress[self::PROGRESS_KEY] ?? null) ? $progress[self::PROGRESS_KEY] : [];
        $st = (string) ($state['status'] ?? '');
        if (in_array($st, ['queued', 'running', 'done'], true)) {
            return;
        }

        $picked = $this->autoSampleUrls($crawl);
        $urls = array_values(array_unique(array_column($picked, 'url')));
        if ($urls === []) {
            $progress[self::PROGRESS_KEY] = [
                'status' => 'idle',
                'skipped' => true,
                'reason' => 'no_urls',
                'source' => 'auto',
                'roles' => [],
                'urls' => [],
                'rows' => [],
                'done' => 0,
                'total' => 0,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $userId = (int) ($crawl->user_id ?: 0);
        if ($userId <= 0 && $crawl->project) {
            $userId = (int) $crawl->project->user_id;
        }
        $user = $userId > 0 ? User::query()->find($userId) : null;
        if (! $user) {
            $progress[self::PROGRESS_KEY] = [
                'status' => 'idle',
                'skipped' => true,
                'reason' => 'no_user',
                'source' => 'auto',
                'roles' => $picked,
                'urls' => $urls,
                'rows' => [],
                'done' => 0,
                'total' => 0,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $roles = [];
        foreach ($picked as $row) {
            $roles[(string) $row['url']] = (string) $row['role'];
        }

        try {
            $this->start($crawl, $user, $urls, [
                'source' => 'auto',
                'roles' => $roles,
            ]);
        } catch (\Throwable $e) {
            $crawl->refresh();
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress[self::PROGRESS_KEY] = [
                'status' => 'idle',
                'skipped' => true,
                'reason' => 'error',
                'error' => mb_substr($e->getMessage(), 0, 300),
                'source' => 'auto',
                'roles' => $roles,
                'urls' => $urls,
                'rows' => [],
                'done' => 0,
                'total' => 0,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();
        }
    }

    /**
     * @return list<array{url:string,role:string}>
     */
    public function autoSampleUrls(SiteAuditCrawl $crawl): array
    {
        $max = max(1, min(
            (int) config('site_audit.plagiarism_external_auto_max', 3),
            (int) config('site_audit.plagiarism_external_max_urls', 20)
        ));

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            })
            ->orderByDesc('word_count')
            ->limit(800)
            ->get(['url', 'title', 'word_count']);

        // Главную добираем отдельно — у неё часто мало слов, в топ-800 по тексту может не попасть.
        $homePages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            })
            ->where(function ($q) {
                $q->where('url', 'like', '%/')
                    ->orWhere('url', 'like', '%/index.html')
                    ->orWhere('url', 'like', '%/index.php');
            })
            ->orderByRaw('LENGTH(url) asc')
            ->limit(30)
            ->get(['url', 'title', 'word_count']);
        $pages = $homePages->concat($pages)->unique('url')->values();

        $byRole = [
            'home' => null,
            'category' => null,
            'product' => null,
            'service' => null,
        ];

        foreach ($pages as $page) {
            $url = trim((string) $page->url);
            if ($url === '') {
                continue;
            }
            $role = $this->classifyUrlRole($url);
            if ($role === null || ! array_key_exists($role, $byRole)) {
                continue;
            }
            if ($byRole[$role] !== null) {
                continue;
            }
            // для авто — хоть немного текста (кроме главной)
            if ($role !== 'home' && (int) $page->word_count < 40) {
                continue;
            }
            $byRole[$role] = [
                'url' => $url,
                'role' => $role,
                'word_count' => (int) $page->word_count,
            ];
        }

        // Главная: если не нашли по path — берём самый «корневой» URL
        if ($byRole['home'] === null) {
            $home = $this->guessHomeUrl($crawl, $pages);
            if ($home !== null) {
                $byRole['home'] = ['url' => $home, 'role' => 'home', 'word_count' => 0];
            }
        }

        $out = [];
        $seen = [];
        foreach (['home', 'category'] as $role) {
            if ($byRole[$role] === null) {
                continue;
            }
            $u = $byRole[$role]['url'];
            if (isset($seen[$u])) {
                continue;
            }
            $out[] = ['url' => $u, 'role' => $role];
            $seen[$u] = true;
            if (count($out) >= $max) {
                return $out;
            }
        }

        // Третья: товар, иначе услуга
        foreach (['product', 'service'] as $role) {
            if ($byRole[$role] === null) {
                continue;
            }
            $u = $byRole[$role]['url'];
            if (isset($seen[$u])) {
                continue;
            }
            $out[] = ['url' => $u, 'role' => $role];
            $seen[$u] = true;
            break;
        }

        // Добор, если категории/товара нет — любая «текстовая» не-редакционная
        if (count($out) < $max) {
            foreach ($pages as $page) {
                $url = trim((string) $page->url);
                if ($url === '' || isset($seen[$url]) || (int) $page->word_count < 80) {
                    continue;
                }
                $path = $this->urlPath($url);
                if ($path === '/' || preg_match('#^/(index\.(html?|php))?$#iu', $path)) {
                    continue;
                }
                // не брать «корни» локалей /en/ /es/ как добор
                if (preg_match('#^/[a-z]{2}/?$#iu', $path)) {
                    continue;
                }
                if ($this->isEditorialPath($path)) {
                    continue;
                }
                $out[] = ['url' => $url, 'role' => 'sample'];
                $seen[$url] = true;
                if (count($out) >= $max) {
                    break;
                }
            }
        }

        return $out;
    }

    private function classifyUrlRole(string $url): ?string
    {
        $path = $this->urlPath($url);
        if ($path === '/' || preg_match('#^/(index\.(html?|php))?$#iu', $path)) {
            return 'home';
        }
        if ($this->isEditorialPath($path)) {
            return null;
        }
        // Услуга раньше «product», т.к. /seo/ и т.п. часто услуги агентства
        if ($this->isServicePath($path)) {
            return 'service';
        }
        if ($this->isProductDetailPath($path)) {
            return 'product';
        }
        if ($this->isCategoryPath($path)) {
            return 'category';
        }

        return null;
    }

    private function urlPath(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private function isEditorialPath(string $path): bool
    {
        return (bool) preg_match(
            '#/(blog|blogs|news|novosti|article|articles|post|posts|statya|stati|wiki|docs|dokumenty|privacy|politika|cookie|oferta|soglashenie)(/|$)#iu',
            $path
        );
    }

    private function isServicePath(string $path): bool
    {
        return (bool) preg_match(
            '#/(uslugi|usluga|uslug|services?|service|tarif|tarify|pricing|audit|seo|kontekst|reklama|razrabotka)(/|$)#iu',
            $path
        );
    }

    private function isCategoryPath(string $path): bool
    {
        // раздел/категория: /catalog/, /catalog/foo/, /category/bar/ — без глубокого «карточного» хвоста
        if (preg_match('#^/(catalog|katalog|category|categories|shop|magazin)/?$#iu', $path)) {
            return true;
        }

        return (bool) preg_match(
            '#^/(catalog|katalog|category|categories|shop|magazin)/[^/]+/?$#iu',
            $path
        );
    }

    private function isProductDetailPath(string $path): bool
    {
        if (preg_match('#/(product|products|tovar|tovary|item|goods|sku)(/|$)#iu', $path)) {
            return true;
        }
        // глубокий каталог: /catalog/cat/item/
        return (bool) preg_match(
            '#^/(catalog|katalog|shop|magazin)/[^/]+/[^/]+#iu',
            $path
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SiteAuditPage>  $pages
     */
    private function guessHomeUrl(SiteAuditCrawl $crawl, $pages): ?string
    {
        $domain = strtolower(trim((string) optional($crawl->project)->domain));
        $domain = preg_replace('#^www\.#', '', $domain) ?: '';
        foreach ($pages as $page) {
            $url = trim((string) $page->url);
            $path = $this->urlPath($url);
            if ($path !== '/' && ! preg_match('#^/(index\.(html?|php))?$#iu', $path)) {
                continue;
            }
            if ($domain !== '') {
                $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
                $host = preg_replace('#^www\.#', '', $host) ?: '';
                if ($host !== '' && $host !== $domain) {
                    continue;
                }
            }

            return $url;
        }

        return null;
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
            // Resume: не сбрасываем уже посчитанные URL (stuck job / повторный dispatch).
            $prevDoneRows = [];
            foreach (is_array($state['rows'] ?? null) ? $state['rows'] : [] as $prevRow) {
                if (! is_array($prevRow)) {
                    continue;
                }
                $prevUrl = (string) ($prevRow['url'] ?? '');
                if ($prevUrl === '') {
                    continue;
                }
                $hasResult = isset($prevRow['uniqueness_pct'])
                    || (! empty($prevRow['error']) && is_string($prevRow['error']));
                if ($hasResult) {
                    $prevDoneRows[$prevUrl] = $prevRow;
                }
            }
            $state['status'] = 'running';
            $state['rows'] = array_values($prevDoneRows);
            $state['done'] = count($state['rows']);
            $state['cost_spent'] = (int) ($state['cost_spent'] ?? 0);
            $state['error'] = null;
            $this->saveState($crawl, $state);

            $pendingUrls = array_values(array_filter($urls, static function ($u) use ($prevDoneRows) {
                return ! isset($prevDoneRows[$u]);
            }));

            if ($pendingUrls !== []) {
                SiteAuditFinding::query()
                    ->where('crawl_id', $crawl->id)
                    ->where('code', self::FINDING_CODE)
                    ->whereIn('url', $pendingUrls)
                    ->delete();
            }
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

            foreach ($pendingUrls as $url) {
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
            $prevRows = is_array($state['prev_rows'] ?? null) ? $state['prev_rows'] : [];
            if ($prevRows !== []) {
                $state['rows'] = array_values(array_merge($prevRows, is_array($state['rows'] ?? null) ? $state['rows'] : []));
                $state['done'] = count($state['rows']);
                $state['total'] = max((int) ($state['total'] ?? 0), count($state['rows']));
            }
            unset($state['prev_rows']);
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
     * Кандидаты для UI: короткий стартовый список (+ поиск по q).
     * Не отдаём десятки тысяч строк в браузер — только выборка.
     *
     * @return array{candidates: list<array{url:string,title:?string,word_count:int,is_landing:bool}>, total:int, truncated:bool, q:string}
     */
    public function candidates(SiteAuditCrawl $crawl, ?int $limit = null, string $q = ''): array
    {
        $q = trim($q);
        $defaultLimit = max(20, (int) config('site_audit.plagiarism_external_candidates_max', 150));
        $searchLimit = max(20, (int) config('site_audit.plagiarism_external_search_max', 100));
        $limit = $limit !== null ? max(1, $limit) : ($q !== '' ? $searchLimit : $defaultLimit);

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

        $base = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($qb) {
                $qb->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            });

        $total = (int) (clone $base)->count();

        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $pages = (clone $base)
                ->where(function ($qb) use ($like) {
                    $qb->where('url', 'like', $like)
                        ->orWhere('title', 'like', $like);
                })
                ->orderByDesc('word_count')
                ->orderBy('url')
                ->limit($limit)
                ->get(['url', 'title', 'word_count']);
            $out = [];
            foreach ($pages as $page) {
                $url = (string) $page->url;
                $out[] = [
                    'url' => $url,
                    'title' => $page->title,
                    'word_count' => (int) $page->word_count,
                    'is_landing' => isset($landingUrls[$url]),
                ];
            }

            return [
                'candidates' => $out,
                'total' => $total,
                'truncated' => true,
                'q' => $q,
            ];
        }

        $out = [];
        $seen = [];
        $landingKeys = array_keys($landingUrls);
        if ($landingKeys !== []) {
            foreach (array_chunk($landingKeys, 500) as $chunk) {
                $landingPages = (clone $base)
                    ->whereIn('url', $chunk)
                    ->get(['url', 'title', 'word_count'])
                    ->keyBy('url');
                foreach ($chunk as $lu) {
                    if (isset($seen[$lu]) || count($out) >= $limit) {
                        continue;
                    }
                    $page = $landingPages->get($lu);
                    $out[] = [
                        'url' => $lu,
                        'title' => $page ? $page->title : null,
                        'word_count' => $page ? (int) $page->word_count : 0,
                        'is_landing' => true,
                    ];
                    $seen[$lu] = true;
                }
                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        if (count($out) < $limit) {
            $exclude = array_keys($seen);
            $restQ = clone $base;
            if ($exclude !== []) {
                foreach (array_chunk($exclude, 500) as $chunk) {
                    $restQ->whereNotIn('url', $chunk);
                }
            }
            $rest = $restQ
                ->orderByDesc('word_count')
                ->orderBy('url')
                ->limit($limit - count($out))
                ->get(['url', 'title', 'word_count']);
            foreach ($rest as $page) {
                $url = (string) $page->url;
                if (isset($seen[$url])) {
                    continue;
                }
                $out[] = [
                    'url' => $url,
                    'title' => $page->title,
                    'word_count' => (int) $page->word_count,
                    'is_landing' => isset($landingUrls[$url]),
                ];
                $seen[$url] = true;
            }
        }

        return [
            'candidates' => $out,
            'total' => $total,
            'truncated' => $total > count($out),
            'q' => '',
        ];
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

        $buckets = ['critical' => 0, 'other' => 0, 'important' => 0, 'warning' => 0, 'info' => 0];
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
