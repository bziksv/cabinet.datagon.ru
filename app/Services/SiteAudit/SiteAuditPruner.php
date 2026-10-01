<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditCrawlStat;
use App\SiteAuditProject;
use Illuminate\Support\Facades\DB;

class SiteAuditPruner
{
    /** Чанк DELETE findings — иначе один UPDATE на 0.5–1M строк + concurrent insert = «полчаса». */
    private const FINDINGS_BATCH = 5000;

    private const PAGES_BATCH = 2000;

    /**
     * Полное удаление crawl (синхронно, чанками). Для prune / CLI / jobs.
     */
    public function deleteCrawl(SiteAuditCrawl $crawl): void
    {
        $id = (int) $crawl->id;
        $this->purgeCrawlRows($id);

        if (SiteAuditCrawl::query()->where('id', $id)->exists()) {
            SiteAuditCrawl::query()->where('id', $id)->delete();
        }

        SiteAuditUserAgentSession::clear($id);
        SiteAuditCrawlEngine::clearStoredState($id);
    }

    /**
     * UI: сразу убрать проверку из списка, findings/pages — в очередь.
     */
    public function queueDeleteCrawl(SiteAuditCrawl $crawl): void
    {
        $id = (int) $crawl->id;

        SiteAuditCrawlStat::query()->where('crawl_id', $id)->delete();
        if (SiteAuditCrawl::query()->where('id', $id)->exists()) {
            SiteAuditCrawl::query()->where('id', $id)->delete();
        }

        SiteAuditUserAgentSession::clear($id);
        SiteAuditCrawlEngine::clearStoredState($id);

        \App\Jobs\SiteAudit\DeleteSiteAuditCrawlJob::dispatch($id);
    }

    /**
     * Чанковый DELETE pages/findings (без одной гигантской транзакции).
     */
    public function purgeCrawlRows(int $crawlId): void
    {
        $crawlId = (int) $crawlId;
        if ($crawlId < 1) {
            return;
        }

        $findingsBatch = max(500, (int) config('site_audit.delete_findings_batch', self::FINDINGS_BATCH));
        $pagesBatch = max(200, (int) config('site_audit.delete_pages_batch', self::PAGES_BATCH));

        // Без DB::transaction вокруг всего: иначе один lock на полчаса.
        do {
            $n = (int) DB::delete(
                'DELETE FROM site_audit_findings WHERE crawl_id = ? LIMIT ' . $findingsBatch,
                [$crawlId]
            );
        } while ($n > 0);

        do {
            $n = (int) DB::delete(
                'DELETE FROM site_audit_pages WHERE crawl_id = ? LIMIT ' . $pagesBatch,
                [$crawlId]
            );
        } while ($n > 0);

        SiteAuditCrawlStat::query()->where('crawl_id', $crawlId)->delete();
    }

    /**
     * Оставляет N последних краулов проекта, остальные удаляет.
     *
     * @return int сколько краулов удалено
     */
    public function pruneProject(int $projectId, ?int $keep = null): int
    {
        $keep = $keep !== null
            ? max(1, $keep)
            : max(1, (int) config('site_audit.history_keep_per_project', 10));

        $ids = SiteAuditCrawl::query()
            ->where('project_id', $projectId)
            ->orderByDesc('id')
            ->pluck('id');

        if ($ids->count() <= $keep) {
            return 0;
        }

        $toDelete = $ids->slice($keep)->values();
        $deleted = 0;

        foreach ($toDelete as $id) {
            $crawl = SiteAuditCrawl::query()->find($id);
            if (! $crawl) {
                continue;
            }
            // не трогаем активные
            if (! $crawl->isFinished()) {
                continue;
            }
            $this->deleteCrawl($crawl);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Тариф SiteAuditProjects: сколько проверок (crawl) хранить у пользователя.
     * Удаляет самые старые завершённые; активные не трогает.
     *
     * @return int сколько краулов удалено
     */
    public function pruneUserToLimit(int $userId, ?int $keep = null): int
    {
        if ($keep === null) {
            $user = \App\User::query()->find($userId);
            $keep = \App\Support\SiteAuditLimits::projectsLimit($user);
        }
        $keep = max(1, (int) $keep);

        $ids = SiteAuditCrawl::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->pluck('id');

        if ($ids->count() <= $keep) {
            return 0;
        }

        $keepIds = $ids->take($keep)->flip();
        $deleted = 0;

        foreach ($ids as $id) {
            if ($keepIds->has($id)) {
                continue;
            }
            $crawl = SiteAuditCrawl::query()->find($id);
            if (! $crawl || ! $crawl->isFinished()) {
                continue;
            }
            $this->deleteCrawl($crawl);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Удалить все краулы пользователя (история аудита). Проекты тоже.
     */
    public function purgeUserHistory(int $userId): int
    {
        $deleted = 0;
        $crawlIds = SiteAuditCrawl::query()->where('user_id', $userId)->pluck('id');
        foreach ($crawlIds as $id) {
            $crawl = SiteAuditCrawl::query()->find($id);
            if (! $crawl) {
                continue;
            }
            if (! $crawl->isFinished()) {
                $crawl->status = SiteAuditCrawl::STATUS_CANCELLED;
                $crawl->error = 'Остановлен: очистка истории после перехода на бесплатный тариф';
                $crawl->finished_at = now();
                $crawl->save();
            }
            $this->deleteCrawl($crawl);
            $deleted++;
        }

        SiteAuditProject::query()->where('user_id', $userId)->delete();
        if (class_exists(\App\SiteAuditSchedule::class)) {
            \App\SiteAuditSchedule::query()->where('user_id', $userId)->delete();
        }

        return $deleted;
    }

    /**
     * Prune по всем проектам пользователя (или всем, если userId=null).
     */
    public function pruneAll(?int $userId = null, ?int $keep = null): int
    {
        $q = SiteAuditCrawl::query()->select('project_id')->distinct();
        if ($userId) {
            $q->where('user_id', $userId);
        }

        $total = 0;
        foreach ($q->pluck('project_id') as $projectId) {
            $total += $this->pruneProject((int) $projectId, $keep);
        }

        return $total;
    }
}