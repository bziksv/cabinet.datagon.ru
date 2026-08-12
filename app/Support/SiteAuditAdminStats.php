<?php

namespace App\Support;

use App\SiteAuditCrawl;
use App\SiteAuditProject;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class SiteAuditAdminStats
{
    private const TARIFF_ORDER = [
        'Free' => 1,
        'Optimal' => 2,
        'Ultimate' => 3,
        'Maximum' => 4,
    ];

    private const RUNNING = [
        SiteAuditCrawl::STATUS_QUEUED,
        SiteAuditCrawl::STATUS_QUEUED_WAIT,
        SiteAuditCrawl::STATUS_DISCOVERING,
        SiteAuditCrawl::STATUS_FETCHING,
        SiteAuditCrawl::STATUS_AGGREGATING,
    ];

    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public static function snapshot(int $crawlLimit = 400): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $now = Carbon::now();
        $dayAgo = $now->copy()->subDay();
        $weekAgo = $now->copy()->subDays(7);
        $monthAgo = $now->copy()->subDays(30);

        $projectsTotal = (int) SiteAuditProject::query()->count();
        $usersWithProjects = (int) SiteAuditProject::query()
            ->selectRaw('COUNT(DISTINCT user_id) as c')
            ->value('c');

        $crawlsTotal = (int) SiteAuditCrawl::query()->count();
        $crawlsToday = (int) SiteAuditCrawl::query()->where('created_at', '>=', $dayAgo)->count();
        $crawls7d = (int) SiteAuditCrawl::query()->where('created_at', '>=', $weekAgo)->count();
        $crawls30d = (int) SiteAuditCrawl::query()->where('created_at', '>=', $monthAgo)->count();
        $crawlsRunning = (int) SiteAuditCrawl::query()->whereIn('status', self::RUNNING)->count();
        $crawlsDone = (int) SiteAuditCrawl::query()->where('status', SiteAuditCrawl::STATUS_DONE)->count();
        $crawlsFailed = (int) SiteAuditCrawl::query()
            ->whereIn('status', [SiteAuditCrawl::STATUS_FAILED, SiteAuditCrawl::STATUS_CANCELLED])
            ->count();

        $pagesFetchedTotal = (int) SiteAuditCrawl::query()->sum('pages_fetched');
        $pagesFetched7d = (int) SiteAuditCrawl::query()
            ->where('created_at', '>=', $weekAgo)
            ->sum('pages_fetched');

        $usersActive7d = (int) SiteAuditCrawl::query()
            ->where('created_at', '>=', $weekAgo)
            ->selectRaw('COUNT(DISTINCT user_id) as c')
            ->value('c');

        $crawlLimit = max(50, min(1000, $crawlLimit));
        $crawls = SiteAuditCrawl::query()
            ->with([
                'project:id,user_id,domain,name',
                'project.user' => static function ($query) {
                    $query->select('id', 'email', 'name', 'telegram_bot_active', 'chat_id', 'last_online_at');
                },
                'project.user.roles:id,name',
            ])
            ->orderByDesc('id')
            ->limit($crawlLimit)
            ->get([
                'id', 'project_id', 'user_id', 'status',
                'pages_total', 'pages_fetched', 'pages_limit',
                'buckets_json', 'started_at', 'finished_at', 'created_at', 'error',
            ]);

        $rows = [];
        foreach ($crawls as $crawl) {
            $project = $crawl->project;
            $user = $project ? $project->user : null;
            if (! $user && $crawl->user_id) {
                $user = User::query()
                    ->with('roles:id,name')
                    ->select('id', 'email', 'name', 'telegram_bot_active', 'chat_id', 'last_online_at')
                    ->find($crawl->user_id);
            }

            $roleNames = $user ? $user->getRoleNames() : collect();
            $tariffCode = self::tariffLabel($roleNames);
            $lastOnline = $user && $user->last_online_at
                ? Carbon::parse($user->last_online_at)
                : null;
            $started = $crawl->started_at ?: $crawl->created_at;
            $finished = $crawl->finished_at;
            $buckets = is_array($crawl->buckets_json) ? $crawl->buckets_json : [];

            $rows[] = [
                'user_id' => (int) ($user ? $user->id : $crawl->user_id),
                'email' => $user ? (string) $user->email : '—',
                'name' => $user && $user->name ? (string) $user->name : '',
                'last_online_at' => $lastOnline ? $lastOnline->format('d.m.Y H:i') : null,
                'last_online_human' => $lastOnline ? $lastOnline->diffForHumans() : null,
                'last_online_sort' => $lastOnline ? $lastOnline->timestamp : 0,
                'tariff_code' => $tariffCode,
                'tariff_label' => self::tariffDisplayName($tariffCode),
                'tariff_sort' => self::tariffSortKey($tariffCode),
                'telegram' => $user && $user->telegram_bot_active && $user->chat_id,
                'project_id' => (int) $crawl->project_id,
                'domain' => $project ? (string) $project->domain : '—',
                'crawl_id' => (int) $crawl->id,
                'status' => (string) $crawl->status,
                'status_label' => SiteAuditCrawl::statusLabel($crawl->status),
                'pages_fetched' => (int) $crawl->pages_fetched,
                'pages_limit' => (int) $crawl->pages_limit,
                'pages_total' => (int) $crawl->pages_total,
                'critical' => (int) ($buckets['critical'] ?? 0),
                'other' => (int) ($buckets['other'] ?? 0),
                'important' => (int) ($buckets['important'] ?? 0),
                'warning' => (int) ($buckets['warning'] ?? 0),
                'info' => (int) ($buckets['info'] ?? 0),
                'started_at' => $started ? $started->format('d.m.Y H:i') : null,
                'started_sort' => $started ? $started->timestamp : 0,
                'finished_at' => $finished ? $finished->format('d.m.Y H:i') : null,
                'finished_sort' => $finished ? $finished->timestamp : 0,
                'error' => $crawl->error ? mb_substr((string) $crawl->error, 0, 120) : null,
                'crawl_url' => route('pages.site-audit.crawl.show', $crawl->id),
            ];
        }

        return [
            'summary' => [
                'projects_total' => $projectsTotal,
                'users_with_projects' => $usersWithProjects,
                'users_active_7d' => $usersActive7d,
                'crawls_total' => $crawlsTotal,
                'crawls_today' => $crawlsToday,
                'crawls_7d' => $crawls7d,
                'crawls_30d' => $crawls30d,
                'crawls_running' => $crawlsRunning,
                'crawls_done' => $crawlsDone,
                'crawls_failed' => $crawlsFailed,
                'pages_fetched_total' => $pagesFetchedTotal,
                'pages_fetched_7d' => $pagesFetched7d,
                'rows_shown' => count($rows),
                'rows_limit' => $crawlLimit,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection|\Illuminate\Database\Eloquent\Collection  $roleNames
     */
    private static function tariffLabel($roleNames): string
    {
        foreach (['Maximum', 'Ultimate', 'Optimal', 'Free'] as $code) {
            if ($roleNames->contains($code)) {
                return $code;
            }
        }

        $first = $roleNames->first(static function ($name) {
            return $name !== 'user';
        });

        return $first ?: '—';
    }

    private static function tariffDisplayName(string $code): string
    {
        if ($code === '—' || $code === '') {
            return '—';
        }

        return (string) __($code);
    }

    private static function tariffSortKey(string $code): int
    {
        return self::TARIFF_ORDER[$code] ?? 99;
    }
}
