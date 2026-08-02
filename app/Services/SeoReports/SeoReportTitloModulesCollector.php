<?php

namespace App\Services\SeoReports;

use App\DomainInformation;
use App\DomainMonitoring;
use App\ProjectRelevanceHistory;
use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistProject;
use App\SiteAuditCrawl;
use App\SiteAuditProject;
use App\Support\HomeUserSites;
use Carbon\Carbon;
use Throwable;

class SeoReportTitloModulesCollector
{
    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectAudit(int $userId, string $domain): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return $this->fail('empty', __('Project not found'));
        }

        try {
            $project = SiteAuditProject::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        if (!$project) {
            return $this->fail('not_connected', __('Site Audit is not connected'));
        }

        /** @var SiteAuditCrawl|null $crawl */
        $crawl = $project->crawls()
            ->where('status', 'done')
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();

        if (!$crawl) {
            return $this->fail('empty', __('No Site Audit crawl yet'));
        }

        $buckets = is_array($crawl->buckets_json) ? $crawl->buckets_json : [];

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $project->id,
                'crawl_id' => (int) $crawl->id,
                'finished_at' => optional($crawl->finished_at)->toIso8601String(),
                'buckets' => [
                    'critical' => (int) ($buckets['critical'] ?? 0),
                    'other' => (int) ($buckets['other'] ?? 0),
                    'warning' => (int) ($buckets['warning'] ?? 0),
                    'info' => (int) ($buckets['info'] ?? 0),
                ],
                'open_url' => route('pages.site-audit.crawl.show', ['id' => $crawl->id]),
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectChecklist(int $userId, string $domain, ?Carbon $from, ?Carbon $to): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        try {
            $project = SeoChecklistProject::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        if (!$project) {
            return $this->fail('not_connected', __('SEO Checklist is not connected'));
        }

        $closedQuery = $project->items()
            ->whereIn('status', SeoChecklistItem::CLOSED_STATUSES)
            ->whereNotNull('done_at');
        if ($from) {
            $closedQuery->where('done_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $closedQuery->where('done_at', '<=', $to->copy()->endOfDay());
        }
        $closedInPeriod = (int) $closedQuery->count();

        $overdue = (int) $project->items()
            ->whereIn('status', SeoChecklistItem::OPEN_STATUSES)
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now())
            ->count();

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $project->id,
                'progress_done' => (int) ($project->progress_done ?? 0),
                'progress_total' => (int) ($project->progress_total ?? 0),
                'closed_in_period' => $closedInPeriod,
                'overdue' => $overdue,
                'open_url' => route('pages.seo-checklist.show', ['id' => $project->id]),
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectRelevance(int $userId, string $domain): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        try {
            $project = ProjectRelevanceHistory::query()
                ->where('user_id', $userId)
                ->where('name', $domain)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        if (!$project) {
            return $this->fail('not_connected', __('Relevance analysis is not connected'));
        }

        $checks = (int) ($project->count_checks ?? 0);
        $sites = (int) ($project->count_sites ?? 0);
        $points = (float) ($project->total_points ?? 0);
        $avg = $checks > 0 ? round($points / max(1, $checks), 1) : null;

        if ($checks < 1) {
            return $this->fail('empty', __('No relevance analyses yet'));
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $project->id,
                'count_checks' => $checks,
                'count_sites' => $sites,
                'avg_points' => $avg,
                'avg_position' => $project->avg_position !== null ? (float) $project->avg_position : null,
                'last_check' => $project->last_check ? (string) $project->last_check : null,
                'open_url' => route('relevance.history'),
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectUptime(int $userId, string $domain): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        try {
            $monitors = DomainMonitoring::query()
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        $monitor = null;
        foreach ($monitors as $row) {
            if (HomeUserSites::normalizeDomain((string) $row->link) === $domain) {
                $monitor = $row;
                break;
            }
        }
        if (!$monitor) {
            return $this->fail('not_connected', __('Uptime monitoring is not connected'));
        }

        $domainInfo = null;
        try {
            $domainInfo = DomainInformation::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->first();
        } catch (Throwable $e) {
            $domainInfo = null;
        }

        $daysLeft = null;
        if ($domainInfo && method_exists($domainInfo, 'daysUntilExpiry')) {
            try {
                $daysLeft = $domainInfo->daysUntilExpiry();
            } catch (Throwable $e) {
                $daysLeft = null;
            }
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $monitor->id,
                'uptime_percent' => $monitor->uptime_percent !== null ? (float) $monitor->uptime_percent : null,
                'broken' => !empty($monitor->broken),
                'last_check' => $monitor->last_check ? (string) $monitor->last_check : null,
                'domain_days_left' => $daysLeft,
                'open_url' => route('site.monitoring'),
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message:string,progress:string}
     */
    private function fail(string $status, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'progress' => $status === 'error' ? 'error' : 'skip',
        ];
    }
}
