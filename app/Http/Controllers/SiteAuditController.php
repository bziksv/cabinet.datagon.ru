<?php

namespace App\Http\Controllers;

use App\Exports\SiteAuditCanonicalSheet;
use App\Exports\SiteAuditCrawlSummaryExport;
use App\Exports\SiteAuditFindingsExport;
use App\Services\SiteAudit\SiteAuditActionPlanBuilder;
use App\Services\SiteAudit\SiteAuditCrawlStarter;
use App\Services\SiteAudit\SiteAuditCrawlStorage;
use App\Services\SiteAudit\SiteAuditDuplicateGrouper;
use App\Services\SiteAudit\SiteAuditFindingNoteService;
use App\Services\SiteAudit\SiteAuditIgnoreService;
use App\Services\SiteAudit\SiteAuditPruner;
use App\Services\SiteAudit\SiteAuditReportFilter;
use App\Services\SiteAudit\SiteAuditRelevanceBridge;
use App\Services\SiteAudit\SiteAuditExternalPlagiarismRunner;
use App\Services\SiteAudit\SiteAuditGlobalCap;
use App\Services\SiteAudit\SiteAuditLinkReferrers;
use App\Services\SiteAudit\SiteAuditProbeRunner;
use App\Services\SiteAudit\SiteAuditProbeStatus;
use App\Services\SiteAudit\SiteAuditSerpIndexProbe;
use App\Services\SiteAudit\SiteAuditUserAgentSession;
use App\Services\SeoChecklist\SeoChecklistService;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditFindingNote;
use App\SiteAuditIgnore;
use App\SiteAuditPage;
use App\SiteAuditProject;
use App\SiteAuditSchedule;
use App\Support\DemoCabinet;
use App\Support\SiteAuditLimits;
use App\Support\TextUniquenessLimits;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteAuditController extends Controller
{
    private const BUCKET_LABELS = [
        'critical' => 'Грубые',
        'other' => 'Прочие',
        'warning' => 'Предупреждения',
        'info' => 'Инфо',
    ];

    public function index(Request $request): View
    {
        $user = Auth::user();
        $projects = collect();
        $crawls = collect();

        $checklistTeams = collect();
        $teamCandidates = collect();
        $teamRoleLabels = \App\SeoChecklist\SeoChecklistTeam::roleLabels();
        $teamAccessReady = SiteAuditProject::teamColumnReady()
            && class_exists(SeoChecklistService::class)
            && \App\SeoChecklist\SeoChecklistTeam::tableReady();

        if ($user && ! DemoCabinet::isCurrentUser()) {
            $teamIds = SiteAuditProject::teamIdsForMember((int) $user->id);

            $projectsQuery = SiteAuditProject::query()
                ->where(function ($q) use ($user, $teamIds) {
                    $q->where('user_id', $user->id);
                    if ($teamIds !== [] && SiteAuditProject::teamColumnReady()) {
                        $q->orWhereIn('team_id', $teamIds);
                    }
                })
                ->withCount('crawls')
                ->with(['crawls' => function ($q) {
                    $q->orderByDesc('id')->limit(1)
                        ->select([
                            'id', 'project_id', 'user_id', 'status',
                            'pages_total', 'pages_fetched', 'finished_at', 'created_at',
                        ]);
                }]);
            if (SiteAuditProject::teamColumnReady()) {
                $projectsQuery->with('team:id,title');
            }
            $projects = $projectsQuery
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $historyDomain = trim((string) $request->input('domain', ''));

            $crawlsQuery = SiteAuditCrawl::query()
                ->where(function ($q) use ($user, $teamIds) {
                    $q->where('user_id', $user->id);
                    if ($teamIds !== [] && SiteAuditProject::teamColumnReady()) {
                        $q->orWhereHas('project', function ($pq) use ($teamIds) {
                            $pq->whereIn('team_id', $teamIds);
                        });
                    }
                })
                ->with(['project' => function ($q) {
                    if (SiteAuditProject::teamColumnReady()) {
                        $q->with('team:id,title');
                    }
                }])
                ->orderByDesc('id')
                ->select([
                    'id', 'project_id', 'user_id', 'status',
                    'pages_total', 'pages_fetched', 'pages_limit', 'buckets_json', 'counts_json',
                    'started_at', 'finished_at', 'created_at', 'error',
                ])
                ->selectRaw("JSON_EXTRACT(COALESCE(progress_json, '{}'), '$.settings') as settings_json_raw")
                ->selectRaw("JSON_EXTRACT(COALESCE(progress_json, '{}'), '$.engine_resume') as engine_resume_raw");

            if ($historyDomain !== '') {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $historyDomain) . '%';
                $crawlsQuery->whereHas('project', function ($q) use ($like) {
                    $q->where('domain', 'like', $like);
                });
            }

            $crawls = $crawlsQuery
                ->paginate(20)
                ->appends($request->only('domain'))
                ->fragment('sa-history');

            $crawlSizes = SiteAuditCrawlStorage::payloadBytesByCrawlIds($crawls->pluck('id')->all());

            $schedules = SiteAuditSchedule::query()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('project_id');

            if ($teamAccessReady) {
                $svc = app(SeoChecklistService::class);
                $checklistTeams = $svc->teamsForUser((int) $user->id);
                $teamCandidates = $svc->teamCandidates((int) $user->id);
            }
        } else {
            $schedules = collect();
            $crawlSizes = [];
            $historyDomain = '';
            $crawls = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        $canSchedule = $user && ! DemoCabinet::isCurrentUser() && SiteAuditSchedule::allowedForUser($user);

        if ($user && ! DemoCabinet::isCurrentUser()) {
            SiteAuditLimits::touchDowngradeState($user);
        }

        return view('pages.site-audit', [
            'projects' => $projects,
            'crawls' => $crawls,
            'crawlSizes' => $crawlSizes,
            'historyDomain' => $historyDomain ?? '',
            'schedules' => $schedules,
            'canSchedule' => $canSchedule,
            'scheduleFrequencies' => SiteAuditSchedule::frequencyLabels(),
            'scheduleWeekdays' => SiteAuditSchedule::weekdayLabels(),
            'schedulesLimit' => SiteAuditLimits::schedulesLimit(),
            'schedulesUsed' => SiteAuditLimits::schedulesUsed(),
            'pagesLimit' => SiteAuditLimits::pagesPerCrawlLimit(),
            'concurrencyLimit' => SiteAuditLimits::concurrencyLimit(),
            'projectsLimit' => SiteAuditLimits::projectsLimit(),
            'projectsUsed' => SiteAuditLimits::projectsUsed(),
            'crawlsLimit' => SiteAuditLimits::crawlsPerMonthLimit(),
            'crawlsUsed' => SiteAuditLimits::crawlsUsedThisMonth(),
            'historyPurgeNotice' => SiteAuditLimits::historyPurgeNotice($user),
            'findingsCatalog' => config('site_audit.findings', []),
            'bucketLabels' => self::BUCKET_LABELS,
            'checklistTeams' => $checklistTeams,
            'teamAccessReady' => $teamAccessReady,
            'teamCandidates' => $teamCandidates,
            'teamRoleLabels' => $teamRoleLabels,
        ]);
    }

    public function assignProjectTeam(Request $request, int $id): RedirectResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            abort(403);
        }

        $user = Auth::user();
        abort_unless($user, 401);

        $project = SiteAuditProject::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (! SiteAuditProject::teamColumnReady()) {
            return redirect()
                ->route('pages.site-audit')
                ->with('error', 'Команды пока недоступны (нужна миграция)');
        }

        $teamId = (int) $request->input('team_id', 0);
        $returnTo = (string) $request->input('return_to', '');
        if ($returnTo === 'profile') {
            $back = redirect()->to(route('profile.index') . '#team');
        } elseif ($returnTo === 'history') {
            $back = redirect()->to(route('pages.site-audit') . '#sa-history');
        } else {
            $back = redirect()->route('pages.site-audit');
        }

        if ($teamId < 1) {
            $project->team_id = null;
            $project->save();

            return $back->with('status', 'Команда отключена от проекта ' . $project->domain);
        }

        $team = app(SeoChecklistService::class)->findOwnedTeam((int) $user->id, $teamId);
        if (! $team) {
            return $back->with('error', 'Команда не найдена');
        }

        $project->team_id = $team->id;
        $project->save();

        return $back->with('status', 'Команда «' . $team->title . '» подключена к ' . $project->domain);
    }

    public function showCrawl(int $id): View
    {
        $crawl = $this->ownedCrawl($id, true, true);
        $crawl->load('project');

        $counts = $this->countsForCrawlDisplay($crawl);
        $counts = (new SiteAuditIgnoreService())->applyToCounts($counts, $crawl);
        $counts = (new SiteAuditFindingNoteService())->applyFixedToCounts($counts, $crawl);

        $tree = $this->buildReportTree($counts, 'tech', $crawl);
        $treeSeo = $this->buildReportTree($counts, 'seo', $crawl);
        $treeAll = $this->buildReportTree($counts, null, $crawl);
        $bucketsTech = $this->bucketsFromTree($tree);
        $bucketsSeo = $this->bucketsFromTree($treeSeo);
        $bucketsAll = $this->bucketsFromTree($treeAll);

        $history = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->where('status', SiteAuditCrawl::STATUS_DONE)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'buckets_json', 'counts_json', 'pages_total', 'finished_at', 'created_at']);

        $historyRows = [];
        foreach ($history as $h) {
            $hCounts = is_array($h->counts_json) ? $h->counts_json : [];
            if ($h->id === $crawl->id && $hCounts === []) {
                $hCounts = $counts;
            }
            $historyRows[] = [
                'crawl' => $h,
                'tech' => $this->bucketsFromTree($this->buildReportTree($hCounts, 'tech')),
                'seo' => $this->bucketsFromTree($this->buildReportTree($hCounts, 'seo')),
            ];
        }

        // Архив в модалке — без тяжёлых JSON; лимит небольшой (полный список не нужен на каждый просмотр).
        $archiveLimit = 25;
        $archiveCrawls = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->whereIn('status', [SiteAuditCrawl::STATUS_DONE, SiteAuditCrawl::STATUS_FAILED])
            ->orderByDesc('id')
            ->limit($archiveLimit)
            ->get(['id', 'status', 'buckets_json', 'pages_total', 'pages_fetched', 'finished_at', 'created_at', 'error']);

        $plagiarismRunner = new SiteAuditExternalPlagiarismRunner();

        $project = $crawl->project;
        if ($project && SiteAuditProject::teamColumnReady()) {
            $project->loadMissing('team:id,title');
        }

        return view('pages.site-audit-crawl', [
            'crawl' => $crawl,
            'project' => $project,
            'canManageCrawl' => Auth::user() && (int) $crawl->user_id === (int) Auth::id(),
            'buckets' => $bucketsTech,
            'bucketsAll' => $bucketsAll,
            'bucketsSeo' => $bucketsSeo,
            'bucketLabels' => self::BUCKET_LABELS,
            'tree' => $tree,
            'treeSeo' => $treeSeo,
            'treeAll' => $treeAll,
            'counts' => $counts,
            'findingsCatalog' => config('site_audit.findings', []),
            'history' => $history,
            'historyRows' => $historyRows,
            'archiveCrawls' => $archiveCrawls,
            'compareCandidates' => $history->where('id', '!=', $crawl->id)->values(),
            'shareUrl' => $crawl->publicShareUrl(),
            'shareWhiteLabel' => $crawl->whiteLabelMeta(),
            'canWhiteLabel' => Auth::user() && ! DemoCabinet::isCurrentUser()
                && SiteAuditSchedule::allowedForUser(Auth::user()),
            'actionPlan' => is_array($crawl->progress_json['action_plan'] ?? null)
                ? $crawl->progress_json['action_plan']
                : null,
            'canActionPlanAi' => (bool) config('deepseek.token')
                && (bool) config('site_audit.action_plan_ai_enabled', true),
            // Кандидаты и релевантность — lazy AJAX (LandingResolver + pages тяжелы на remote DB).
            'plagiarismCandidates' => [],
            'plagiarismCandidatesLazy' => $crawl->status === SiteAuditCrawl::STATUS_DONE,
            'plagiarismCandidatesUrl' => route('pages.site-audit.plagiarism.candidates', $crawl->id),
            'plagiarismState' => $plagiarismRunner->state($crawl),
            'plagiarismMaxUrls' => max(1, (int) config('site_audit.plagiarism_external_max_urls', 10)),
            'plagiarismWarnBelow' => (float) config('site_audit.plagiarism_external_warn_below', 70),
            // Лимиты уникальности — через status AJAX (tariff/getAsArray на remote DB дорого).
            'plagiarismRemaining' => null,
            'plagiarismLimit' => null,
            'relevanceRows' => [],
            'relevanceRowsLazy' => $crawl->status === SiteAuditCrawl::STATUS_DONE,
            'relevanceRowsUrl' => route('pages.site-audit.relevance.rows', $crawl->id),
        ]);
    }

    public function plagiarismCandidates(int $id): JsonResponse
    {
        $crawl = $this->ownedCrawl($id);
        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return response()->json(['ok' => true, 'candidates' => []]);
        }

        return response()->json([
            'ok' => true,
            'candidates' => (new SiteAuditExternalPlagiarismRunner())->candidates($crawl),
        ]);
    }

    public function relevanceRows(int $id): JsonResponse
    {
        $crawl = $this->ownedCrawl($id);
        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        return response()->json([
            'ok' => true,
            'rows' => (new SiteAuditRelevanceBridge())->rowsForCrawl($crawl),
        ]);
    }

    public function startExternalPlagiarism(Request $request, int $id): JsonResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo', 'message' => 'В демо недоступно'], 403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return response()->json(['error' => 'status', 'message' => 'Только для готовой проверки'], 422);
        }

        $urls = $request->input('urls', []);
        if (! is_array($urls)) {
            $urls = preg_split('/\R+/', (string) $urls) ?: [];
        }

        try {
            $state = (new SiteAuditExternalPlagiarismRunner())->start($crawl, Auth::user(), $urls);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'busy', 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true,
            'state' => $state,
            'remaining' => TextUniquenessLimits::remainingForUser(Auth::user()),
            'limit' => TextUniquenessLimits::limitForUser(Auth::user()),
        ]);
    }

    public function externalPlagiarismStatus(int $id): JsonResponse
    {
        $crawl = $this->ownedCrawl($id);
        $runner = new SiteAuditExternalPlagiarismRunner();

                        return response()->json([
            'ok' => true,
            'state' => $runner->state($crawl),
            'finding_count' => (int) SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', SiteAuditExternalPlagiarismRunner::FINDING_CODE)
                ->count(),
            'remaining' => Auth::user()
                ? TextUniquenessLimits::remainingForUser(Auth::user())
                : null,
            'limit' => Auth::user()
                ? TextUniquenessLimits::limitForUser(Auth::user())
                : null,
            'report_url' => route('pages.site-audit.report.show', [
                'id' => $crawl->id,
                'code' => SiteAuditExternalPlagiarismRunner::FINDING_CODE,
            ]),
        ]);
    }

    public function showReport(Request $request, int $id, string $code)
    {
        $crawl = $this->ownedCrawl($id, false);
        $crawl->load('project');

        $meta = config('site_audit.findings.' . $code);
        if (! $meta) {
            abort(404);
        }

        // Внешний модуль: не редиректим — сначала объясняем, зачем он, и даём кнопку перехода.
        $isExternalModule = ! empty($meta['external']);
        $externalHref = null;
        if ($isExternalModule) {
            $routeName = $meta['route'] ?? null;
            abort_unless(is_string($routeName) && $routeName !== '', 404);
            try {
                $externalHref = route($routeName, $meta['route_params'] ?? []);
            } catch (\Throwable $e) {
                abort(404);
            }
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $filterFields = $isExternalModule ? [] : SiteAuditReportFilter::fieldsForCode($code);
        $filterValues = $isExternalModule ? [] : SiteAuditReportFilter::valuesFromRequest($request, $code);
        $groupable = ! $isExternalModule && SiteAuditDuplicateGrouper::isGroupable($code);
        $viewMode = $request->input('view', $groupable ? 'groups' : 'list');
        if (! in_array($viewMode, ['groups', 'list'], true) || ! $groupable) {
            $viewMode = $groupable ? 'groups' : 'list';
        }

        $showIgnored = in_array((string) $request->input('ignored', ''), ['1', 'true', 'yes'], true);
        $showFixed = in_array((string) $request->input('fixed', ''), ['1', 'true', 'yes'], true);
        $ignoreSvc = new SiteAuditIgnoreService();
        $noteSvc = new SiteAuditFindingNoteService();
        $projectId = (int) $crawl->project_id;
        $ignoredMap = [];
        $notesMap = [];
        $codeWideIgnored = false;
        if (! $isExternalModule) {
            $codeWideIgnored = SiteAuditIgnore::query()
                ->where('project_id', $projectId)
                ->where('code', $code)
                ->where('url_hash', '')
                ->exists();
        }

        $groups = [];
        $groupTotal = 0;
        $htmlSitewide = null;
        $rows = collect();
        $total = 0;
        $pages = 1;

        if ($isExternalModule) {
            // пустой отчёт — тело заменит карточка модуля
        } elseif (($meta['source'] ?? '') === 'pages_canonical') {
            $query = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->whereNotNull('canonical')
                ->where('canonical', '!=', '')
                ->orderBy('id');
            SiteAuditReportFilter::applyToPages($query, $filterValues);
            $total = (clone $query)->count();
            $rows = $query->forPage($page, $perPage)->get()->map(function (SiteAuditPage $p) use ($meta) {
                return (object) [
                    'id' => null,
                    'url' => $p->url,
                    'url_hash' => null,
                    'severity' => $meta['severity'] ?? 'info',
                    'code' => 'pages_with_canonical',
                    'meta_json' => ['canonical' => $p->canonical],
                ];
            });
            $pages = max(1, (int) ceil($total / $perPage));
        } else {
            $codes = $this->reportCodes($code, $meta);
            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes)
                ->orderBy('id');
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            if (! $showIgnored) {
                $ignoreSvc->excludeIgnored($query, $projectId);
            }
            if (! $showFixed) {
                $noteSvc->excludeFixed($query, $projectId);
            }

            $total = (clone $query)->count();

            // Groups тянут findings в память — на больших отчётах уходим в list.
            // Для HTML-паттернов лимит выше: сквозные шаблоны как раз на сотнях URL.
            $groupsMax = SiteAuditDuplicateGrouper::isHtmlErrors($code) ? 2500 : 400;
            if ($viewMode === 'groups' && $total > $groupsMax) {
                $viewMode = 'list';
            }

            $allGroupsForSummary = [];

            if ($viewMode === 'groups') {
                $allRows = $query->get();
                $allGroups = SiteAuditDuplicateGrouper::group($allRows, $code);
                $allGroupsForSummary = $allGroups;
                $groupTotal = count($allGroups);
                $perPage = 20;
                $pages = max(1, (int) ceil(max(1, $groupTotal) / $perPage));
                $page = min($page, $pages);
                $groups = array_slice($allGroups, ($page - 1) * $perPage, $perPage);
                $rows = collect();
                if ($showIgnored) {
                    $ignoredMap = $ignoreSvc->ignoredMapForFindings($projectId, $allRows);
                }
                $notesMap = $noteSvc->mapForFindings($projectId, $allRows);
            } else {
                $rows = $query->forPage($page, $perPage)->get();
                $pages = max(1, (int) ceil($total / $perPage));
                $ignoredMap = $ignoreSvc->ignoredMapForFindings($projectId, $rows);
                $notesMap = $noteSvc->mapForFindings($projectId, $rows);

                // В списке страниц всё равно ловим доминантный HTML-паттерн (сквозной шаблон).
                if (SiteAuditDuplicateGrouper::isHtmlErrors($code) && $total >= 3 && $total <= $groupsMax) {
                    $allGroupsForSummary = SiteAuditDuplicateGrouper::group((clone $query)->get(), $code);
                }
            }

            if (SiteAuditDuplicateGrouper::isHtmlErrors($code) && $allGroupsForSummary !== []) {
                $htmlSitewide = SiteAuditDuplicateGrouper::sitewideSummary($allGroupsForSummary, $total);
            }
        }

        $filterParams = SiteAuditReportFilter::queryParams($filterValues);
        if ($groupable) {
            $filterParams['view'] = $viewMode;
        }
        if ($showIgnored) {
            $filterParams['ignored'] = 1;
        }
        if ($showFixed) {
            $filterParams['fixed'] = 1;
        }

        $sideCounts = $this->countsForCrawlDisplay($crawl);
        $sideCounts = $ignoreSvc->applyToCounts($sideCounts, $crawl);
        $sideCounts = $noteSvc->applyFixedToCounts($sideCounts, $crawl);

        $tree = $this->buildReportTree($sideCounts, 'tech', $crawl);
        $treeSeo = $this->buildReportTree($sideCounts, 'seo', $crawl);
        $treeAll = $this->buildReportTree($sideCounts, null, $crawl);
        $buckets = $this->bucketsFromTree($tree);
        $bucketsSeo = $this->bucketsFromTree($treeSeo);
        $bucketsAll = $this->bucketsFromTree($treeAll);

        $seoCodes = config('site_audit.seo_codes', []);
        $itemGroup = $meta['group'] ?? (in_array($code, $seoCodes, true) ? 'seo' : 'tech');
        // На отчёте по умолчанию открываем сводку со всеми замечаниями.
        $activeGroup = 'all';

        $showReferrers = in_array($code, SiteAuditLinkReferrers::targetCodes(), true);
        if ($showReferrers && $rows instanceof \Illuminate\Support\Collection && $rows->isNotEmpty()) {
            $targetUrls = $rows->pluck('url')->filter()->unique()->values()->all();
            $refMap = SiteAuditLinkReferrers::forCrawl((int) $crawl->id, $targetUrls);
            $pageOrigins = SiteAuditLinkReferrers::pageDiscoveryMap((int) $crawl->id, $targetUrls);
            $originFallback = SiteAuditLinkReferrers::originMeta($crawl, $targetUrls);
            $sitemapHref = SiteAuditLinkReferrers::sitemapViewUrl($crawl);
            $rows = $rows->map(function ($row) use ($refMap, $pageOrigins, $originFallback, $sitemapHref, $code) {
                $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                $url = (string) $row->url;

                // Источник постановки в очередь (discovered_* на странице) или страница со ссылкой.
                $via = trim((string) ($meta['discovered_via'] ?? ''));
                $from = trim((string) ($meta['discovered_from'] ?? ''));
                if ($via === '' && isset($pageOrigins[$url])) {
                    $via = (string) ($pageOrigins[$url]['via'] ?? '');
                    $from = (string) ($pageOrigins[$url]['from'] ?? '');
                }

                $refs = $refMap[$url] ?? [];
                $slashAlt = SiteAuditLinkReferrers::slashVariantPublic($url);
                if ($slashAlt !== null && ! empty($refMap[$slashAlt])) {
                    foreach ($refMap[$slashAlt] as $ref) {
                        if (! in_array($ref, $refs, true)) {
                            $refs[] = $ref;
                        }
                    }
                }
                if (! empty($meta['from'])) {
                    $metaFrom = trim((string) $meta['from']);
                    if ($metaFrom !== '' && ! in_array($metaFrom, $refs, true)) {
                        array_unshift($refs, $metaFrom);
                    }
                }

                // Битая ссылка / HEAD-цель: самой страницы в крауле нет — «откуда» = кто ссылается.
                if ($via === '' && $refs !== []) {
                    $via = 'link';
                    $from = (string) $refs[0];
                }

                $source = SiteAuditLinkReferrers::formatDiscoverySource($via, $from !== '' ? $from : null);
                $meta['origin_label'] = $source['label'];
                $meta['origin_hint'] = '';
                // Нет discovered_* (старый воркер / HEAD-only) — эвристика sitemap/посев.
                if ($meta['origin_label'] === '' && $via === '' && isset($originFallback[$url])) {
                    $fb = $originFallback[$url];
                    $meta['origin_label'] = (string) ($fb['label'] ?? '');
                    $meta['from_sitemap'] = ! empty($fb['from_sitemap']);
                    if (! empty($fb['from_sitemap']) && $sitemapHref) {
                        $meta['sitemap_href'] = $sitemapHref;
                    }
                }
                $meta['discovered_via'] = $via;
                $meta['discovered_from'] = $from !== '' ? $from : null;
                $meta['from_sitemap'] = $via === 'sitemap' || ! empty($meta['from_sitemap']);
                if ($via === 'sitemap') {
                    $meta['sitemap_href'] = $source['from'] ?: $sitemapHref;
                }

                // Старые проверки срезали слэш: в sitemap URL/, а в отчёте без / и 404.
                if (($row->code ?? '') === 'http_4xx' || ($code ?? '') === 'http_4xx') {
                    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
                    if ($path !== '' && $path !== '/' && substr($path, -1) !== '/') {
                        $slashAltHint = SiteAuditLinkReferrers::slashVariantPublic($url);
                        if ($slashAltHint) {
                            $meta['slash_hint'] = true;
                            $meta['slash_url'] = $slashAltHint;
                            if ($via === 'sitemap' || ! empty($meta['from_sitemap'])) {
                                $meta['false_404_slash'] = true;
                            }
                        }
                    }
                }

                // via=link — страница-источник всегда первая в списке
                if ($via === 'link' && $from !== '' && ! in_array($from, $refs, true)) {
                    array_unshift($refs, $from);
                }
                $meta['referrers'] = array_slice($refs, 0, 12);
                $meta['referrer_count'] = count($refs);
                $row->meta_json = $meta;

                return $row;
            });
        }

        return view('pages.site-audit-report', [
            'crawl' => $crawl,
            'project' => $crawl->project,
            'code' => $code,
            'meta' => $meta,
            'rows' => $rows,
            'groups' => $groups,
            'groupable' => $groupable,
            'viewMode' => $viewMode,
            'groupTotal' => $groupTotal,
            'htmlSitewide' => $htmlSitewide,
            'isHtmlErrorReport' => SiteAuditDuplicateGrouper::isHtmlErrors($code),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
            'bucketLabels' => self::BUCKET_LABELS,
            'filterFields' => $filterFields,
            'filterValues' => $filterValues,
            'filtersActive' => SiteAuditReportFilter::hasActive($filterValues),
            'filterAction' => route('pages.site-audit.report.show', [$crawl->id, $code]),
            'filterClearUrl' => route('pages.site-audit.report.show', [$crawl->id, $code]),
            'filterParams' => $filterParams,
            'showIgnored' => $showIgnored,
            'showFixed' => $showFixed,
            'ignoredMap' => $ignoredMap,
            'probeStatus' => SiteAuditProbeStatus::forCode($crawl, $code),
            'serpIndexDeep' => $code === 'index_count_mismatch'
                ? (is_array($crawl->progress_json['serp_index']['deep'] ?? null)
                    ? $crawl->progress_json['serp_index']['deep']
                    : null)
                : null,
            'serpIndexWebmaster' => $code === 'index_count_mismatch'
                ? (new SiteAuditSerpIndexProbe())->webmasterStatusPayload($crawl)
                : null,
            'notesMap' => $notesMap,
            'codeWideIgnored' => $codeWideIgnored,
            'sideCounts' => $sideCounts,
            'tree' => $tree,
            'treeSeo' => $treeSeo,
            'treeAll' => $treeAll,
            'buckets' => $buckets,
            'bucketsSeo' => $bucketsSeo,
            'bucketsAll' => $bucketsAll,
            'activeGroup' => $activeGroup,
            'itemGroup' => $itemGroup,
            'showReferrers' => $showReferrers,
            'isExternalModule' => $isExternalModule,
            'externalHref' => $externalHref,
            'externalRelated' => $isExternalModule
                ? $this->externalModuleRelated($crawl, $code, $meta, $sideCounts)
                : [],
            'canIgnore' => ! $isExternalModule
                && ! DemoCabinet::isCurrentUser()
                && ($meta['source'] ?? '') !== 'pages_canonical',
            'canNote' => ! $isExternalModule
                && ! DemoCabinet::isCurrentUser()
                && ($meta['source'] ?? '') !== 'pages_canonical',
        ]);
    }

    /**
     * Связанные отчёты аудита для внешнего модуля (lite-перекрытие).
     *
     * @return list<array{code:string,title:string,count:int,href:string}>
     */
    private function externalModuleRelated(
        SiteAuditCrawl $crawl,
        string $code,
        array $meta,
        array $sideCounts
    ): array {
        $related = $meta['related_codes'] ?? [];
        if (! is_array($related) || $related === []) {
            return [];
        }

        $catalog = config('site_audit.findings', []);
        $out = [];
        foreach ($related as $relCode) {
            $relCode = (string) $relCode;
            if ($relCode === '' || ! isset($catalog[$relCode])) {
                continue;
            }
            $relMeta = $catalog[$relCode];
            $count = 0;
            if (! empty($relMeta['virtual']) && is_array($relMeta['codes'] ?? null)) {
                foreach ($relMeta['codes'] as $child) {
                    $count += (int) ($sideCounts[(string) $child] ?? 0);
                }
            } else {
                $count = (int) ($sideCounts[$relCode] ?? 0);
            }
            $out[] = [
                'code' => $relCode,
                'title' => (string) ($relMeta['title'] ?? $relCode),
                'count' => $count,
                'href' => route('pages.site-audit.report.show', [$crawl->id, $relCode]),
            ];
        }

        return $out;
    }

    /**
     * Точечный запуск опциональной пробы (PSI / SERP…) по уже скачанной проверке.
     */
    public function runProbe(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'demo'], 403);
            }
            abort(403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE
            && $crawl->status !== SiteAuditCrawl::STATUS_AGGREGATING) {
            return redirect()
                ->back(302, [], route('pages.site-audit.crawl.show', $crawl->id))
                ->with('status', 'Сначала дождитесь завершения проверки');
        }

        $probe = trim((string) $request->input('probe', ''));
        if ($probe === '' || ! isset(SiteAuditProbeStatus::catalog()[$probe])) {
            return redirect()->back()->with('status', 'Неизвестная проверка');
        }

        $mode = trim((string) $request->input('mode', ''));
        $engine = trim((string) $request->input('engine', ''));
        $result = (new SiteAuditProbeRunner())->run(
            $crawl,
            $probe,
            true,
            $mode,
            $engine !== '' ? $engine : null
        );
        $code = trim((string) $request->input('code', ''));
        $back = $code !== ''
            ? route('pages.site-audit.report.show', [$crawl->id, $code])
            : route('pages.site-audit.crawl.show', $crawl->id);

        return redirect($back)->with('status', $result['message'] ?? 'Готово');
    }

    public function cancelCrawl(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'demo'], 403);
            }
            abort(403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        if ($crawl->isFinished()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'status' => $crawl->status,
                    'status_label' => $crawl->statusLabelRu(),
                    'finished' => true,
                ]);
            }

            return redirect()
                ->back(302, [], route('pages.site-audit') . '#sa-history')
                ->with('status', 'Проверка уже завершена');
        }

        $crawl->status = SiteAuditCrawl::STATUS_CANCELLED;
        $crawl->error = 'Остановлен пользователем';
        $crawl->finished_at = now();
        $crawl->save();

        SiteAuditUserAgentSession::clear($crawl->id);
        SiteAuditGlobalCap::promoteWaiting();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $crawl->status,
                'status_label' => $crawl->statusLabelRu(),
                'finished' => true,
                'can_resume' => (new \App\Services\SiteAudit\SiteAuditCrawlEngine())->canResume($crawl),
                'pages_fetched' => (int) $crawl->pages_fetched,
                'pages_total' => (int) $crawl->pages_total,
                'id' => (int) $crawl->id,
            ]);
        }

        return redirect()
            ->back(302, [], route('pages.site-audit') . '#sa-history')
            ->with('status', 'Проверка остановлена');
    }

    public function destroyCrawl(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'demo'], 403);
            }
            abort(403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        if (! $crawl->isFinished()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'active',
                    'message' => 'Нельзя удалить незавершённую проверку — сначала остановите',
                ], 422);
            }

            return redirect()
                ->route('pages.site-audit.crawl.show', $crawl->id)
                ->with('status', 'Нельзя удалить незавершённую проверку — сначала остановите');
        }

        (new SiteAuditPruner())->deleteCrawl($crawl);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => route('pages.site-audit'),
            ]);
        }

        return redirect()->route('pages.site-audit')->with('status', 'Проверка удалена');
    }

    public function destroyProject(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            abort(403);
        }

        $user = Auth::user();
        abort_unless($user, 401);

        $project = SiteAuditProject::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $active = SiteAuditCrawl::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', [
                SiteAuditCrawl::STATUS_DONE,
                SiteAuditCrawl::STATUS_FAILED,
            ])
            ->exists();

        if ($active) {
            return redirect()
                ->route('pages.site-audit')
                ->with('status', 'Сначала дождитесь завершения активной проверки');
        }

        $pruner = new SiteAuditPruner();
        foreach ($project->crawls()->orderBy('id')->get() as $crawl) {
            $pruner->deleteCrawl($crawl);
        }
        $project->delete();

        return redirect()->route('pages.site-audit')->with('status', 'Проект удалён');
    }

    public function saveSchedule(Request $request, int $projectId)
    {
        if (DemoCabinet::isCurrentUser()) {
            abort(403);
        }

        $user = Auth::user();
        abort_unless($user, 401);

        $project = SiteAuditProject::query()
            ->where('id', $projectId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $enabled = $request->boolean('enabled', false);
        $frequency = SiteAuditSchedule::normalizeFrequency($request->input('frequency', SiteAuditSchedule::FREQ_WEEKLY));
        $weekday = SiteAuditSchedule::normalizeWeekday($request->input('weekday'));
        $hour = SiteAuditSchedule::normalizeHour($request->input('hour', 4));

        if (! $enabled) {
            SiteAuditSchedule::query()
                ->where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->delete();

            return redirect()->route('pages.site-audit')->with('status', 'Расписание отключено');
        }

        if (! SiteAuditSchedule::allowedForUser($user)) {
            return redirect()->route('pages.site-audit')
                ->with('error', 'Авторасписание недоступно на вашем тарифе (на Free — 0 слотов)');
        }

        $existing = SiteAuditSchedule::query()
            ->where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->where('enabled', true)
            ->first();

        if (! $existing && ! SiteAuditLimits::canEnableSchedule($user, $project->id)) {
            $lim = SiteAuditLimits::schedulesLimit($user);

            return redirect()->route('pages.site-audit')
                ->with('error', "Лимит авторасписаний исчерпан ({$lim}). Отключите другое или увеличьте тариф.");
        }

        $concurrency = SiteAuditLimits::resolveConcurrency($user, $request->input('concurrency', 1));
        $pagesLimit = SiteAuditLimits::resolvePagesLimit($user, $request->input('pages_limit'));
        $speed = (string) $request->input('crawl_speed', 'normal');
        $presets = config('site_audit.speed_presets', []);
        if (! isset($presets[$speed])) {
            $speed = 'normal';
        }

        $schedule = SiteAuditSchedule::query()->firstOrNew([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);
        $schedule->domain = $project->domain;
        $schedule->enabled = true;
        $schedule->frequency = $frequency;
        $schedule->settings_json = [
            'crawl_speed' => $speed,
            'concurrency' => $concurrency,
            'pages_limit' => $pagesLimit,
            'weekday' => $weekday,
            'hour' => $hour,
            'save_html' => 'off',
        ];
        $schedule->next_run_at = $schedule->computeNextRun(Carbon::now());
        $schedule->save();

        $when = $schedule->next_run_at
            ? $schedule->next_run_at->format('d.m.Y H:i')
            : '—';

        return redirect()->route('pages.site-audit')
            ->with('status', 'Расписание сохранено. Следующий запуск: ' . $when);
    }

    public function createShare(Request $request, int $id): JsonResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo'], 403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return response()->json(['error' => 'status', 'message' => 'Шаринг только для готовой проверки'], 422);
        }

        $wantWhite = $request->boolean('white_label');
        $brandName = trim((string) $request->input('brand_name', ''));
        $brandUrl = trim((string) $request->input('brand_url', ''));
        $clearLogo = $request->boolean('clear_logo');

        if ($wantWhite) {
            $user = Auth::user();
            if (! SiteAuditSchedule::allowedForUser($user)) {
                return response()->json([
                    'error' => 'tariff',
                    'message' => 'White-label доступен только на платных тарифах',
                ], 422);
            }
            $crawl->share_white_label = true;
            $crawl->share_brand_name = $brandName !== '' ? mb_substr($brandName, 0, 120) : null;
            $crawl->share_brand_url = $brandUrl !== '' ? mb_substr($brandUrl, 0, 255) : null;

            if ($clearLogo) {
                $crawl->clearWhiteLabelLogo();
            } elseif ($request->hasFile('brand_logo')) {
                $request->validate([
                    'brand_logo' => 'file|mimes:png,jpg,jpeg,webp,gif,svg|max:1024',
                ]);
                $file = $request->file('brand_logo');
                $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
                if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
                    $ext = 'png';
                }
                $crawl->clearWhiteLabelLogo();
                $path = $file->storeAs(
                    'site-audit-wl',
                    $crawl->id . '.' . $ext,
                    'public'
                );
                $crawl->share_brand_logo = $path ?: null;
            }
        } else {
            $crawl->share_white_label = false;
            $crawl->share_brand_name = null;
            $crawl->share_brand_url = null;
            $crawl->clearWhiteLabelLogo();
        }

        if (! $crawl->share_token) {
            $crawl->share_token = bin2hex(random_bytes(24));
        }
        $crawl->share_enabled_at = now();
        $crawl->save();

        $wl = $crawl->whiteLabelMeta();

        return response()->json([
            'ok' => true,
            'url' => $crawl->publicShareUrl(),
            'token' => $crawl->share_token,
            'white_label' => $wl['enabled'],
            'brand_name' => $wl['brand_name'],
            'brand_url' => $wl['brand_url'],
            'brand_logo_url' => $wl['brand_logo_url'],
        ]);
    }

    public function revokeShare(int $id): JsonResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo'], 403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        $crawl->share_enabled_at = null;
        // token и white-label настройки оставляем — можно снова включить ту же ссылку
        $crawl->save();

        return response()->json(['ok' => true]);
    }

    public function generateActionPlan(Request $request, int $id): JsonResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo', 'message' => 'В демо недоступно'], 403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return response()->json(['error' => 'status', 'message' => 'План только для готовой проверки'], 422);
        }

        $withAi = $request->boolean('ai');
        if ($withAi && ! SiteAuditSchedule::allowedForUser(Auth::user())) {
            return response()->json([
                'error' => 'tariff',
                'message' => 'ИИ-резюме плана доступно на платных тарифах',
            ], 422);
        }

        $builder = new SiteAuditActionPlanBuilder();
        $plan = $builder->build($crawl, $withAi);

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['action_plan'] = $plan;
        $crawl->progress_json = $progress;
        $crawl->save();

        return response()->json([
            'ok' => true,
            'plan' => $plan,
        ]);
    }

    public function toggleActionPlanItem(Request $request, int $id): JsonResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo'], 403);
        }

        $crawl = $this->ownedCrawl($id);
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            return response()->json(['error' => 'code'], 422);
        }
        $done = $request->boolean('done');

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $plan = is_array($progress['action_plan'] ?? null) ? $progress['action_plan'] : null;
        if (! $plan) {
            return response()->json(['error' => 'no_plan', 'message' => 'Сначала сформируйте план'], 422);
        }

        $builder = new SiteAuditActionPlanBuilder();
        $plan = $builder->toggleDone($plan, $code, $done);
        $progress['action_plan'] = $plan;
        $crawl->progress_json = $progress;
        $crawl->save();

        return response()->json(['ok' => true, 'plan' => $plan]);
    }

    public function start(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'auth'], 401);
        }

        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo', 'message' => 'В демо кабинете запуск аудита недоступен'], 403);
        }

        $domains = $this->parseSiteAuditDomains($request->input('domain', ''));

        $seed = trim((string) $request->input('seed_urls', ''));
        $seedUrls = [];
        if ($seed !== '') {
            foreach (preg_split('/\R+/', $seed) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $seedUrls[] = $line;
                }
            }
        }

        $pagesOnly = in_array((string) $request->input('pages_only', ''), ['1', 'true', 'yes', 'on'], true)
            || ($domains === [] && $seedUrls !== []);

        if ($pagesOnly && $seedUrls === []) {
            return response()->json([
                'error' => 'seeds',
                'message' => 'Режим «только страницы»: укажите URL (по одному на строку) в поле «Страницы / доп. URL»',
            ], 422);
        }
        if (! $pagesOnly && $domains === []) {
            return response()->json([
                'error' => 'domain',
                'message' => 'Укажите домен(ы) или включите «только эти страницы» и перечислите URL',
            ], 422);
        }

        $starter = new SiteAuditCrawlStarter();
        $user = Auth::user();
        $started = [];
        $errors = [];

        /** @var array<int, array{domain:string,settings:array}> $jobs */
        $jobs = [];

        if ($pagesOnly) {
            $groups = \App\Services\SiteAudit\SiteAuditSeedGroups::groupByHost($seedUrls);
            if ($groups === []) {
                return response()->json([
                    'error' => 'seeds',
                    'message' => 'Не удалось разобрать URL. Нужны полные адреса вида https://site.ru/page',
                ], 422);
            }
            // если в «Доменах» что-то указано — ограничиваем группы этими хостами
            if ($domains !== []) {
                $allowed = [];
                foreach ($domains as $d) {
                    $allowed[preg_replace('/^www\./', '', strtolower($d))] = true;
                }
                $groups = array_filter($groups, function ($urls, $host) use ($allowed) {
                    return isset($allowed[$host]);
                }, ARRAY_FILTER_USE_BOTH);
                if ($groups === []) {
                    return response()->json([
                        'error' => 'seeds',
                        'message' => 'URL не совпадают с указанными доменами',
                    ], 422);
                }
            }
            $tariffPages = \App\Support\SiteAuditLimits::pagesPerCrawlLimit($user);
            foreach ($groups as $host => $urls) {
                $urls = array_values(array_slice($urls, 0, max(1, $tariffPages)));
                $jobs[] = [
                    'domain' => $host,
                    'settings' => [
                        'seed_urls' => $urls,
                        'pages_only' => true,
                        'pages_limit' => SiteAuditLimits::resolvePagesLimit(
                            $user,
                            $request->input('pages_limit', count($urls))
                        ),
                        'save_html' => 'off',
                        'crawl_speed' => (string) $request->input('crawl_speed', 'normal'),
                        'concurrency' => SiteAuditLimits::resolveConcurrency($user, $request->input('concurrency', 1)),
                        'exclude_patterns' => '',
                        'virtual_robots' => (string) $request->input('virtual_robots', ''),
                        'extra_hosts' => [],
                    ],
                ];
            }
        } else {
            foreach ($domains as $domain) {
                $settings = [
                    'seed_urls' => $seedUrls,
                    'pages_only' => false,
                    'save_html' => 'off',
                    'crawl_speed' => (string) $request->input('crawl_speed', 'normal'),
                    'concurrency' => SiteAuditLimits::resolveConcurrency($user, $request->input('concurrency', 1)),
                    'exclude_patterns' => '',
                    'virtual_robots' => (string) $request->input('virtual_robots', ''),
                    'extra_hosts' => count($domains) === 1
                        ? \App\Services\SiteAudit\SiteAuditUrlNormalizer::parseExtraHosts($request->input('extra_hosts', ''))
                        : [],
                ];
                if ($request->filled('pages_limit')) {
                    $settings['pages_limit'] = SiteAuditLimits::resolvePagesLimit($user, $request->input('pages_limit'));
                }
                $jobs[] = ['domain' => $domain, 'settings' => $settings];
            }
        }

        foreach ($jobs as $index => $job) {
            $domain = $job['domain'];
            $settings = $job['settings'];
            try {
                $crawl = $starter->start(
                    $user,
                    $domain,
                    $settings,
                    true,
                    false,
                    $index > 0
                );
                $started[] = [
                    'crawl_id' => $crawl->id,
                    'domain' => $domain,
                    'status' => $crawl->status,
                    'pages_only' => ! empty($settings['pages_only']),
                ];
            } catch (\Throwable $e) {
                $errors[] = $domain . ': ' . $e->getMessage();
                if ($index === 0 || strpos($e->getMessage(), 'лимит') !== false) {
                    break;
                }
            }
        }

        if ($started === []) {
            return response()->json([
                'error' => 'limit',
                'message' => $errors[0] ?? 'Не удалось запустить проверку',
            ], 422);
        }

        $first = $started[0];
        $n = count($started);
        $waiting = ($first['status'] ?? '') === SiteAuditCrawl::STATUS_QUEUED_WAIT;
        if ($pagesOnly) {
            $msg = $n === 1
                ? ($waiting
                    ? 'Проверка страниц поставлена в очередь — сейчас идёт другая проверка.'
                    : 'Запущена проверка только указанных страниц.')
                : "Запущено проверок: {$n} только по страницам (разные домены → разные проекты).";
        } else {
            $msg = $n === 1
                ? ($waiting
                    ? 'Проверка в очереди — дождётся окончания текущего (или свободного слота на сервере).'
                    : 'Проверка запущена. Прогресс — в истории проверок.')
                : "Запущено проверок: {$n}. Прогресс — в истории проверок.";
        }
        if ($waiting) {
            $block = SiteAuditGlobalCap::blockingActiveSummary((int) $user->id, (int) $first['crawl_id']);
            if ($block) {
                $msg .= ' Сейчас: ' . $block;
            }
        }
        if ($errors !== []) {
            $msg .= ' Не запущено: ' . implode('; ', $errors);
        }

        return response()->json([
            'ok' => true,
            'crawl_id' => $first['crawl_id'],
            'crawl_ids' => array_column($started, 'crawl_id'),
            'started' => $started,
            'errors' => $errors,
            'status' => $first['status'],
            'status_url' => route('pages.site-audit.crawl.status', $first['crawl_id']),
            'redirect' => route('pages.site-audit'),
            'message' => $msg,
        ]);
    }

    /**
     * Домены из textarea: один на строку, без дублей; допускаются URL с протоколом/путём.
     *
     * @param  mixed  $raw
     * @return string[]
     */
    private function parseSiteAuditDomains($raw): array
    {
        $out = [];
        $seen = [];
        foreach (preg_split('/\R+/', (string) $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('#^https?://#i', '', $line);
            $line = preg_replace('#/.*$#', '', $line);
            $line = strtolower(rtrim((string) $line, '/'));
            $line = preg_replace('/:\d+$/', '', $line);
            if ($line === '' || isset($seen[$line])) {
                continue;
            }
            $seen[$line] = true;
            $out[] = $line;
        }

        return $out;
    }

    /**
     * Продолжить failed/cancelled проверка с сохранённой очередью (тот же id).
     */
    public function continueCrawl(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'demo', 'message' => 'В демо недоступно'], 403);
            }
            abort(403);
        }

        $crawl = $this->ownedCrawl($id);
        $this->assertCrawlOwner($crawl);
        $engine = new \App\Services\SiteAudit\SiteAuditCrawlEngine();

        try {
            $crawl = $engine->resume($crawl);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'resume', 'message' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('pages.site-audit.crawl.show', $id)
                ->with('status', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'crawl_id' => $crawl->id,
                'status' => $crawl->status,
                'status_label' => $crawl->statusLabelRu(),
                'finished' => $crawl->isFinished(),
                'can_resume' => false,
                'status_url' => route('pages.site-audit.crawl.status', $crawl->id),
                'redirect' => route('pages.site-audit'),
                'message' => 'Сканирование продолжено с сохранённого места.',
            ]);
        }

        return redirect(
            route('pages.site-audit') . '?highlight=' . $crawl->id . '#sa-history'
        )->with('status', 'Проверка #' . $crawl->id . ' продолжен с ' . (int) $crawl->pages_fetched . ' URL');
    }

    /**
     * Повторная проверка проекта с настройками исходного (скорость, exclude, seed, лимит).
     */
    public function repeatCrawl(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'demo', 'message' => 'В демо кабинете запуск аудита недоступен'], 403);
            }
            abort(403);
        }

        $source = $this->ownedCrawl($id);
        $this->assertCrawlOwner($source);
        $project = $source->project;
        if (! $project || ! $project->domain) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'project', 'message' => 'У проверки нет проекта'], 422);
            }

            return redirect()
                ->route('pages.site-audit')
                ->with('status', 'У проверки нет проекта');
        }

        $settings = array_merge(
            is_array($project->settings_json) ? $project->settings_json : [],
            is_array($source->progress_json['settings'] ?? null) ? $source->progress_json['settings'] : []
        );
        $settings['pages_limit'] = max(1, (int) $source->pages_limit);
        if (! empty($source->save_html)) {
            $settings['save_html'] = $source->save_html;
        }

        try {
            $crawl = (new SiteAuditCrawlStarter())->start(
                Auth::user(),
                $project->domain,
                $settings,
                true
            );
            if (! empty($settings['pages_limit'])) {
                $crawl->pages_limit = SiteAuditLimits::resolvePagesLimit(Auth::user(), $settings['pages_limit']);
                $crawl->save();
            }
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'limit', 'message' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('pages.site-audit')
                ->with('status', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'crawl_id' => $crawl->id,
                'status' => $crawl->status,
                'status_label' => $crawl->statusLabelRu(),
                'finished' => $crawl->isFinished(),
                'status_url' => route('pages.site-audit.crawl.status', $crawl->id),
                'redirect' => route('pages.site-audit'),
                'message' => 'Повтор запущен. Прогресс — в истории проверок.',
            ]);
        }

        return redirect(
            route('pages.site-audit') . '?highlight=' . $crawl->id . '#sa-history'
        )->with('status', 'Повторная проверка #' . $crawl->id . ' запущенаа');
    }

    public function crawlStatus(int $id): JsonResponse
    {
        $crawl = $this->ownedCrawl($id);

        $counts = $this->countsForCrawlDisplay($crawl);
        $counts = (new SiteAuditIgnoreService())->applyToCounts($counts, $crawl);
        $counts = (new SiteAuditFindingNoteService())->applyFixedToCounts($counts, $crawl);

        $buckets = is_array($crawl->buckets_json) ? $crawl->buckets_json : [];
        if ($buckets === [] || ! $crawl->isFinished()) {
            $buckets = $this->bucketsFromTree($this->buildReportTree($counts, null));
        }

        return response()->json([
            'id' => $crawl->id,
            'status' => $crawl->status,
            'status_label' => $crawl->statusLabelRu(),
            'pages_fetched' => (int) $crawl->pages_fetched,
            'pages_total' => (int) $crawl->pages_total,
            'pages_unchanged' => (int) (($crawl->progress_json['pages_unchanged'] ?? 0)),
            'buckets' => $buckets,
            'counts' => $counts,
            'error' => $crawl->error,
            'finished' => $crawl->isFinished(),
            'can_resume' => (new \App\Services\SiteAudit\SiteAuditCrawlEngine())->canResume($crawl),
            'progress_pct' => $crawl->pages_total > 0
                ? (int) round(100 * $crawl->pages_fetched / $crawl->pages_total)
                : 0,
            'started_at' => optional($crawl->started_at)->format('d.m H:i'),
            'finished_at' => optional($crawl->finished_at)->format('d.m H:i'),
            'eta_at' => $crawl->estimateFinishedAtFormatted(),
            'eta_title' => $crawl->estimateFinishedAtTitle(),
        ]);
    }

    public function exportReportCsv(Request $request, int $id, string $code): StreamedResponse
    {
        $crawl = $this->ownedCrawl($id);
        $meta = config('site_audit.findings.' . $code, []);
        if (! $meta) {
            abort(404);
        }
        $filename = 'site-audit-' . $crawl->id . '-' . $code . '.csv';
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);

        if (($meta['source'] ?? '') === 'pages_canonical') {
            return response()->streamDownload(function () use ($crawl, $filterValues) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($out, ['url', 'canonical'], ';');

                $query = SiteAuditPage::query()
                    ->where('crawl_id', $crawl->id)
                    ->whereNotNull('canonical')
                    ->where('canonical', '!=', '')
                    ->orderBy('id');
                SiteAuditReportFilter::applyToPages($query, $filterValues);
                $query->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [$row->url, $row->canonical], ';');
                    }
                });

                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $codes = $this->reportCodes($code, $meta);
        $includeIgnored = in_array((string) $request->input('ignored', ''), ['1', 'true', 'yes'], true);
        $includeFixed = in_array((string) $request->input('fixed', ''), ['1', 'true', 'yes'], true);
        $projectId = (int) $crawl->project_id;

        return response()->streamDownload(function () use ($crawl, $codes, $filterValues, $includeIgnored, $includeFixed, $projectId) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['url', 'code', 'severity', 'meta'], ';');

            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes)
                ->orderBy('id');
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            if (! $includeIgnored) {
                (new SiteAuditIgnoreService())->excludeIgnored($query, $projectId);
            }
            if (! $includeFixed) {
                (new SiteAuditFindingNoteService())->excludeFixed($query, $projectId);
            }
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->url,
                        $row->code,
                        $row->severity,
                        $row->meta_json ? json_encode($row->meta_json, JSON_UNESCAPED_UNICODE) : '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportReportXlsx(Request $request, int $id, string $code): BinaryFileResponse
    {
        $crawl = $this->ownedCrawl($id);
        $meta = config('site_audit.findings.' . $code, []);
        if (! $meta) {
            abort(404);
        }

        $filename = 'site-audit-' . $crawl->id . '-' . $code . '.xlsx';
        $title = (string) ($meta['title'] ?? $code);
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);

        if (($meta['source'] ?? '') === 'pages_canonical') {
            return Excel::download(new SiteAuditCanonicalSheet($crawl->id, $filterValues), $filename);
        }

        $codes = $this->reportCodes($code, $meta);
        $includeIgnored = in_array((string) $request->input('ignored', ''), ['1', 'true', 'yes'], true);
        $includeFixed = in_array((string) $request->input('fixed', ''), ['1', 'true', 'yes'], true);

        return Excel::download(
            new SiteAuditFindingsExport($crawl->id, $codes, $title, $filterValues, $includeIgnored, $includeFixed),
            $filename
        );
    }

    public function exportCrawlXlsx(int $id): BinaryFileResponse
    {
        $crawl = $this->ownedCrawl($id);

        return Excel::download(
            new SiteAuditCrawlSummaryExport($crawl),
            'site-audit-' . $crawl->id . '-summary.xlsx'
        );
    }

    public function exportCrawlDocx(int $id)
    {
        $crawl = $this->ownedCrawl($id);
        $path = (new \App\Services\SiteAudit\SiteAuditDocxBuilder())->buildToTemp($crawl);

        return response()->download(
            $path,
            'site-audit-' . $crawl->id . '-summary.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    public function showDiff(Request $request, int $id): View
    {
        $crawl = $this->ownedCrawl($id);
        $crawl->load('project');

        abort_unless($crawl->status === SiteAuditCrawl::STATUS_DONE, 404);

        $withId = (int) $request->input('with', 0);
        $candidates = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->where('status', SiteAuditCrawl::STATUS_DONE)
            ->where('id', '!=', $crawl->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'pages_total', 'finished_at', 'created_at', 'buckets_json']);

        abort_unless($candidates->isNotEmpty(), 404, 'Нет другой завершённой проверки для сравнения');

        if ($withId < 1) {
            $baseline = $candidates->first();
        } else {
            $baseline = $candidates->firstWhere('id', $withId);
            abort_unless($baseline, 404);
        }

        // baseline = более старый по умолчанию; если выбрали новее текущего — всё равно diff current vs with
        $diff = (new \App\Services\SiteAudit\SiteAuditCrawlDiff())->compare($crawl, $baseline);

        return view('pages.site-audit-diff', [
            'crawl' => $crawl,
            'project' => $crawl->project,
            'baseline' => $baseline,
            'candidates' => $candidates,
            'diff' => $diff,
            'bucketLabels' => self::BUCKET_LABELS,
            'findingsCatalog' => config('site_audit.findings', []),
        ]);
    }

    public function ignoreFinding(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            return $this->ignoreJsonOrRedirect($request, 403, 'demo');
        }

        $crawl = $this->ownedCrawl($id);
        $findingId = (int) $request->input('finding_id', 0);
        $code = (string) $request->input('code', '');
        $scope = (string) $request->input('scope', 'url'); // url|code
        $note = $request->input('note');
        $note = is_string($note) ? mb_substr(trim($note), 0, 500) : null;

        $svc = new SiteAuditIgnoreService();
        $projectId = (int) $crawl->project_id;

        if ($scope === 'code') {
            if ($code === '' || ! config('site_audit.findings.' . $code)) {
                return $this->ignoreJsonOrRedirect($request, 422, 'bad_code');
            }
            $svc->ignore($projectId, (int) Auth::id(), $code, '', null, $note);
        } else {
            $finding = SiteAuditFinding::query()
                ->where('id', $findingId)
                ->where('crawl_id', $crawl->id)
                ->first();
            if (! $finding) {
                return $this->ignoreJsonOrRedirect($request, 404, 'not_found');
            }
            $svc->ignoreFinding($finding, $projectId, (int) Auth::id(), $note);
            $code = $finding->code;
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'code' => $code]);
        }

        return redirect()
            ->route('pages.site-audit.report.show', [$crawl->id, $code])
            ->with('status', 'Находка добавлена в игнор (для следующих проверок тоже)');
    }

    public function restoreIgnore(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            return $this->ignoreJsonOrRedirect($request, 403, 'demo');
        }

        $crawl = $this->ownedCrawl($id);
        $findingId = (int) $request->input('finding_id', 0);
        $code = (string) $request->input('code', '');
        $scope = (string) $request->input('scope', 'url');
        $projectId = (int) $crawl->project_id;
        $svc = new SiteAuditIgnoreService();

        if ($scope === 'code') {
            if ($code === '') {
                return $this->ignoreJsonOrRedirect($request, 422, 'bad_code');
            }
            $svc->restore($projectId, $code, '');
        } else {
            $finding = SiteAuditFinding::query()
                ->where('id', $findingId)
                ->where('crawl_id', $crawl->id)
                ->first();
            if (! $finding) {
                return $this->ignoreJsonOrRedirect($request, 404, 'not_found');
            }
            $svc->restoreFinding($finding, $projectId);
            $code = $finding->code;
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'code' => $code]);
        }

        return redirect()
            ->to(route('pages.site-audit.report.show', [$crawl->id, $code]) . '?ignored=1')
            ->with('status', 'Игнор снят');
    }

    public function saveFindingNote(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            return $this->ignoreJsonOrRedirect($request, 403, 'demo');
        }

        $crawl = $this->ownedCrawl($id);
        $findingId = (int) $request->input('finding_id', 0);
        $finding = SiteAuditFinding::query()
            ->where('id', $findingId)
            ->where('crawl_id', $crawl->id)
            ->first();
        if (! $finding) {
            return $this->ignoreJsonOrRedirect($request, 404, 'not_found');
        }

        $status = (string) $request->input('status', SiteAuditFindingNote::STATUS_OPEN);
        $comment = $request->input('comment');
        $comment = is_string($comment) ? $comment : null;

        (new SiteAuditFindingNoteService())->upsertForFinding(
            $finding,
            (int) $crawl->project_id,
            (int) Auth::id(),
            $status,
            $comment
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'code' => $finding->code, 'status' => $status]);
        }

        $msg = $status === SiteAuditFindingNote::STATUS_FIXED
            ? 'Помечено как исправлено'
            : 'Комментарий сохранён';

        return redirect()
            ->route('pages.site-audit.report.show', [$crawl->id, $finding->code])
            ->with('status', $msg);
    }

    public function clearFindingNote(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            return $this->ignoreJsonOrRedirect($request, 403, 'demo');
        }

        $crawl = $this->ownedCrawl($id);
        $findingId = (int) $request->input('finding_id', 0);
        $finding = SiteAuditFinding::query()
            ->where('id', $findingId)
            ->where('crawl_id', $crawl->id)
            ->first();
        if (! $finding) {
            return $this->ignoreJsonOrRedirect($request, 404, 'not_found');
        }

        (new SiteAuditFindingNoteService())->delete(
            (int) $crawl->project_id,
            $finding->code,
            (string) ($finding->url_hash ?: '')
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'code' => $finding->code]);
        }

        return redirect()
            ->to(route('pages.site-audit.report.show', [$crawl->id, $finding->code]) . '?fixed=1')
            ->with('status', 'Статус/комментарий сброшен');
    }

    private function ignoreJsonOrRedirect(Request $request, int $status, string $error)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $error], $status);
        }
        abort($status);
    }

    private function ownedCrawl(int $id, bool $withProgress = true, bool $slimProgress = false): SiteAuditCrawl
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $base = [
            'id', 'project_id', 'user_id', 'status',
            'pages_total', 'pages_fetched', 'pages_limit',
            'counts_json', 'buckets_json',
            'finished_at', 'created_at', 'started_at', 'error',
            'save_html',
            'share_token', 'share_enabled_at',
            'share_white_label', 'share_brand_name', 'share_brand_url', 'share_brand_logo',
        ];

        $query = SiteAuditCrawl::query()->where('id', $id);

        // Полный progress_json тянет landings/sitemap (100+ KB) — на remote DB это секунды.
        if ($withProgress && $slimProgress) {
            $cols = implode(', ', array_map(static function ($c) {
                return 'site_audit_crawls.' . $c;
            }, $base));

            $crawl = $query
                ->selectRaw("{$cols}, JSON_REMOVE(site_audit_crawls.progress_json, '\$.landings', '\$.sitemap', '\$.robots') as progress_json")
                ->firstOrFail();
        } elseif (! $withProgress) {
            $crawl = $query->firstOrFail($base);
        } else {
            $crawl = $query->firstOrFail();
        }

        $this->assertCrawlAccessible($crawl, (int) $user->id);

        return $crawl;
    }

    private function assertCrawlAccessible(SiteAuditCrawl $crawl, int $userId): void
    {
        if ((int) $crawl->user_id === $userId) {
            return;
        }

        $project = $crawl->relationLoaded('project')
            ? $crawl->project
            : SiteAuditProject::query()->find($crawl->project_id);

        abort_unless($project && $project->isAccessibleBy($userId), 403);
    }

    private function assertCrawlOwner(SiteAuditCrawl $crawl): void
    {
        $user = Auth::user();
        abort_unless($user, 401);

        if ((int) $crawl->user_id === (int) $user->id) {
            return;
        }

        $project = $crawl->relationLoaded('project')
            ? $crawl->project
            : SiteAuditProject::query()->find($crawl->project_id);

        abort_unless($project && $project->canManageBy((int) $user->id), 403);
    }

    /**
     * @param array $meta
     * @return string[]
     */
    private function reportCodes(string $code, array $meta): array
    {
        if (! empty($meta['virtual']) && ! empty($meta['codes']) && is_array($meta['codes'])) {
            return array_values($meta['codes']);
        }

        return [$code];
    }

    /**
     * @param array|object $counts
     * @param string|null $group tech|seo|null(=all)
     */
    private function buildReportTree($counts, ?string $group = null, ?SiteAuditCrawl $crawl = null): array
    {
        $counts = (array) $counts;
        $catalog = config('site_audit.findings', []);
        $seoCodes = config('site_audit.seo_codes', []);
        $bySeverity = [
            'critical' => [],
            'other' => [],
            'warning' => [],
            'info' => [],
        ];

        foreach ($catalog as $code => $meta) {
            $phase = $meta['phase'] ?? '';
            // Волна 5: в меню все фазы A–D (раньше только A/B — C/D-находки пропадали из дерева и корзин UI).
            if (! in_array($phase, ['A', 'B', 'C', 'D'], true)) {
                continue;
            }

            $itemGroup = $meta['group'] ?? (in_array($code, $seoCodes, true) ? 'seo' : 'tech');
            if ($group !== null && $itemGroup !== $group) {
                continue;
            }

            $severity = $meta['severity'] ?? 'info';
            if (! isset($bySeverity[$severity])) {
                $severity = 'info';
            }

            $isExternal = ! empty($meta['external']);
            if ($isExternal) {
                $count = 0;
            } elseif (! empty($meta['virtual']) && ! empty($meta['codes']) && is_array($meta['codes'])) {
                $count = 0;
                foreach ($meta['codes'] as $c) {
                    $count += (int) ($counts[$c] ?? 0);
                }
            } elseif (($meta['source'] ?? '') === 'pages_canonical') {
                $count = (int) ($counts['pages_with_canonical'] ?? 0);
            } else {
                $count = (int) ($counts[$code] ?? 0);
            }

            $href = null;
            if ($isExternal && ! empty($meta['route'])) {
                try {
                    $href = route($meta['route'], $meta['route_params'] ?? []);
                } catch (\Throwable $e) {
                    $href = null;
                }
            }
            // Без рабочего URL не показываем пункт (на public share external тоже отфильтруем отдельно).
            if ($isExternal && $href === null) {
                continue;
            }

            $probe = null;
            if (! $isExternal && $crawl !== null) {
                $probe = SiteAuditProbeStatus::forCode($crawl, $code);
            }

            $bySeverity[$severity][] = [
                'code' => $code,
                'title' => $meta['title'] ?? $code,
                'description' => $meta['description'] ?? '',
                'count' => $count,
                'phase' => $phase,
                'group' => $itemGroup,
                'external' => $isExternal,
                'href' => $href,
                'probe' => $probe,
            ];
        }

        foreach ($bySeverity as $sev => $items) {
            usort($items, function ($a, $b) {
                if ($a['count'] === $b['count']) {
                    return strcmp($a['title'], $b['title']);
                }

                return $b['count'] <=> $a['count'];
            });
            $bySeverity[$sev] = $items;
        }

        return $bySeverity;
    }

    /**
     * Счётчики по кодам: после агрегации — counts_json, во время проверки — live из findings.
     * Иначе на отчётах нули, хотя строки findings уже есть.
     *
     * @return array<string,int>
     */
    private function countsForCrawlDisplay(SiteAuditCrawl $crawl): array
    {
        $stored = is_array($crawl->counts_json) ? $crawl->counts_json : [];
        $live = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->selectRaw('code, count(*) as c')
            ->groupBy('code')
            ->pluck('c', 'code')
            ->all();

        // После точечных проб counts_json может отставать — для probe-кодов берём live.
        $probeCodes = [];
        foreach (SiteAuditProbeStatus::catalog() as $meta) {
            foreach ($meta['codes'] as $code) {
                $probeCodes[$code] = true;
            }
        }

        if ($crawl->isFinished() && $stored !== []) {
            foreach ($probeCodes as $code => $_) {
                $stored[$code] = (int) ($live[$code] ?? 0);
            }

            return $stored;
        }

        return $live;
    }

    private function bucketsFromTree(array $tree): array
    {
        $catalog = config('site_audit.findings', []);
        $out = ['critical' => 0, 'other' => 0, 'warning' => 0, 'info' => 0];
        foreach ($tree as $sev => $items) {
            foreach ($items as $item) {
                if (! empty($item['external'])) {
                    continue;
                }
                // Virtual-паки (security_headers и т.п.) — сумма children; не дублируем в корзине.
                $code = (string) ($item['code'] ?? '');
                if (! empty($catalog[$code]['virtual'])) {
                    continue;
                }
                $out[$sev] = ($out[$sev] ?? 0) + (int) ($item['count'] ?? 0);
            }
        }

        return $out;
    }
}
