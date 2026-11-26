<?php

namespace App\Services\SiteAudit;

use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistItemNote;
use App\SeoChecklist\SeoChecklistProject;
use App\Services\SeoChecklist\SeoChecklistService;
use App\SiteAuditChecklistLink;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditIgnore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Синхронизирует результаты завершённого краула Site Audit с привязанной доской SEO-чеклиста.
 */
class SiteAuditChecklistSyncService
{
    public const CODE_PREFIX = 'sa_auto_';

    /**
     * @return array{
     *   ok:bool,
     *   skipped?:bool,
     *   reason?:string,
     *   dry?:bool,
     *   crawl_id:int,
     *   checklist_project_id?:int,
     *   created:int,
     *   updated:int,
     *   moved_to_review:int,
     *   reopened:int,
     *   skipped_ignored:int,
     *   skipped_partial_resolve:bool,
     *   actions:array<int,array<string,mixed>>
     * }
     */
    public function syncCrawl(SiteAuditCrawl $crawl, bool $dryRun = false, bool $force = false): array
    {
        $base = [
            'ok' => false,
            'crawl_id' => (int) $crawl->id,
            'created' => 0,
            'updated' => 0,
            'moved_to_review' => 0,
            'reopened' => 0,
            'skipped_ignored' => 0,
            'skipped_partial_resolve' => false,
            'actions' => [],
        ];

        if (! SiteAuditChecklistLink::tableReady() || ! SeoChecklistProject::tableReady()) {
            return array_merge($base, ['skipped' => true, 'reason' => 'tables_not_ready']);
        }

        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return array_merge($base, ['skipped' => true, 'reason' => 'crawl_not_done']);
        }

        /** @var SiteAuditChecklistLink|null $link */
        $link = SiteAuditChecklistLink::query()
            ->where('site_audit_project_id', (int) $crawl->project_id)
            ->first();

        if (! $link || (! $link->enabled && ! $force)) {
            return array_merge($base, ['skipped' => true, 'reason' => 'no_active_link']);
        }

        /** @var SeoChecklistProject|null $checklist */
        $checklist = SeoChecklistProject::query()
            ->with('template')
            ->find((int) $link->checklist_project_id);

        if (! $checklist || $checklist->status === 'archived') {
            return array_merge($base, ['skipped' => true, 'reason' => 'checklist_missing_or_archived']);
        }

        $base['checklist_project_id'] = (int) $checklist->id;
        $base['dry'] = $dryRun;

        $checklistService = app(SeoChecklistService::class);
        $stagesMeta = $checklistService->resolveTemplateStages($checklist->template);

        $catalog = config('site_audit.findings', []);
        $counts = is_array($crawl->counts_json) ? $crawl->counts_json : [];
        $allowedSeverities = $link->severities();

        // Игноры уровня всего кода по проекту аудита
        $ignoredCodes = SiteAuditIgnore::query()
            ->where('project_id', (int) $crawl->project_id)
            ->where(function ($q) {
                $q->whereNull('url_hash')->orWhere('url_hash', '');
            })
            ->pluck('code')
            ->map('strval')
            ->flip()
            ->all();

        // Защита от ложного закрытия при неполном крауле
        $allowResolveToReview = true;
        if ($link->last_synced_crawl_id && (int) $link->last_synced_crawl_id !== (int) $crawl->id) {
            $prevCrawl = SiteAuditCrawl::query()->find((int) $link->last_synced_crawl_id);
            if ($prevCrawl && (int) $prevCrawl->pages_crawled >= 20) {
                if ((int) $crawl->pages_crawled < (int) floor((int) $prevCrawl->pages_crawled * 0.5)) {
                    $allowResolveToReview = false;
                    $base['skipped_partial_resolve'] = true;
                }
            }
        }

        // Существующие автозадачи этой доски
        $existingByCode = SeoChecklistItem::query()
            ->where('project_id', (int) $checklist->id)
            ->whereNull('parent_id')
            ->where('code', 'like', self::CODE_PREFIX . '%')
            ->get()
            ->keyBy('code');

        // Собираем кандидатов из counts_json
        $activeFindings = [];
        foreach ($counts as $code => $rawCount) {
            $count = (int) $rawCount;
            if ($count < 1 || ! is_string($code)) {
                continue;
            }
            $meta = is_array($catalog[$code] ?? null) ? $catalog[$code] : [];
            if (! empty($meta['virtual'])) {
                continue;
            }
            $sev = (string) ($meta['severity'] ?? 'info');
            if (! in_array($sev, $allowedSeverities, true)) {
                continue;
            }
            if (isset($ignoredCodes[$code])) {
                $base['skipped_ignored']++;
                continue;
            }
            $activeFindings[$code] = [
                'code' => $code,
                'count' => $count,
                'severity' => $sev,
                'meta' => $meta,
            ];
        }

        $samplesByCode = $this->sampleUrls((int) $crawl->id, array_keys($activeFindings));
        $actorUserId = (int) ($link->created_by ?: $checklist->user_id ?: $crawl->user_id ?: 0);
        $role = in_array((string) $link->default_role, ['owner', 'pm', 'shared', 'any'], true)
            ? (string) $link->default_role
            : 'owner';
        $skipWeekends = (bool) ($checklist->skip_weekends ?? false);

        // 1. Создание / обновление / регрессия
        foreach ($activeFindings as $code => $row) {
            $itemCode = self::CODE_PREFIX . $code;
            $meta = $row['meta'];
            $count = (int) $row['count'];
            $sev = (string) $row['severity'];
            $baseTitle = (string) ($meta['title'] ?? $code);
            $title = $baseTitle . ' — ' . number_format($count, 0, '', ' ') . ' URL';
            $help = $this->buildHelpText($code, $meta, $samplesByCode[$code] ?? []);
            $linksJson = [
                [
                    'label' => 'Открыть отчёт в Site Audit',
                    'path' => '/site-audit/crawl/' . $crawl->id . '/report/' . rawurlencode($code),
                ],
            ];
            $stageKey = SiteAuditChecklistStageMapper::resolveStageKey(
                $code,
                $meta,
                $stagesMeta,
                (string) ($link->stage_mode ?: 'smart'),
                (string) ($link->fallback_stage_key ?: 'audits')
            );
            $stageSort = (int) ($stagesMeta[$stageKey]['sort'] ?? 50);
            $dueDays = $link->dueDaysForSeverity($sev);
            $dueAt = $dueDays > 0 ? $this->addDays($dueDays, $skipWeekends) : null;

            /** @var SeoChecklistItem|null $existing */
            $existing = $existingByCode->get($itemCode);

            if (! $existing) {
                $base['created']++;
                $base['actions'][] = [
                    'action' => 'create',
                    'code' => $code,
                    'stage_key' => $stageKey,
                    'title' => $title,
                    'severity' => $sev,
                    'count' => $count,
                ];

                if (! $dryRun) {
                    $sort = (int) $checklist->items()
                        ->whereNull('parent_id')
                        ->where('stage_key', $stageKey)
                        ->max('sort') + 10;

                    SeoChecklistItem::query()->create([
                        'project_id' => (int) $checklist->id,
                        'parent_id' => null,
                        'code' => $itemCode,
                        'stage_key' => $stageKey,
                        'stage_sort' => $stageSort,
                        'sort' => $sort,
                        'title' => $title,
                        'help' => $help,
                        'role' => $role,
                        'is_important' => $sev === 'critical',
                        'include_in_report' => true,
                        'allows_subtasks' => true,
                        'repeat_rule' => null,
                        'due_days_from_start' => null,
                        'due_at' => $dueAt,
                        'links_json' => $linksJson,
                        'status' => 'todo',
                        'created_by' => $actorUserId > 0 ? $actorUserId : null,
                    ]);
                }
                continue;
            }

            // Пропущенную специалистом задачу не трогаем
            if ($existing->status === 'skip') {
                continue;
            }

            // Регрессия: задача была закрыта (done), а проблема снова появилась
            if ($existing->status === 'done') {
                $base['reopened']++;
                $base['actions'][] = [
                    'action' => 'reopen',
                    'item_id' => (int) $existing->id,
                    'code' => $code,
                    'title' => $title,
                    'count' => $count,
                ];

                if (! $dryRun) {
                    $existing->forceFill([
                        'title' => $title,
                        'help' => $help,
                        'links_json' => $linksJson,
                        'status' => 'rework',
                        'due_at' => $dueAt,
                        'done_at' => null,
                    ])->save();

                    $this->addSystemNote(
                        $existing,
                        $actorUserId,
                        'Регрессия в аудите #' . $crawl->id . ': проблема снова обнаружена (' . number_format($count, 0, '', ' ') . ' URL).'
                    );
                }
                continue;
            }

            // Открытая задача — обновляем без создания дубля
            $prevTitle = (string) $existing->title;
            $base['updated']++;
            $base['actions'][] = [
                'action' => 'update',
                'item_id' => (int) $existing->id,
                'code' => $code,
                'title' => $title,
                'count' => $count,
            ];

            if (! $dryRun) {
                $existing->forceFill([
                    'title' => $title,
                    'help' => $help,
                    'links_json' => $linksJson,
                ])->save();

                if ($prevTitle !== $title) {
                    $this->addSystemNote(
                        $existing,
                        $actorUserId,
                        'Обновлено по аудиту #' . $crawl->id . ': сейчас ' . number_format($count, 0, '', ' ') . ' URL.'
                    );
                }
            }
        }

        // 2. Исчезнувшие проблемы (0 URL в новом крауле) -> в review («На проверку»)
        if ($allowResolveToReview) {
            foreach ($existingByCode as $itemCode => $existing) {
                if (! $existing->isOpenStatus() || $existing->status === 'review') {
                    continue;
                }
                $findingCode = substr((string) $itemCode, strlen(self::CODE_PREFIX));
                if ($findingCode === '' || isset($activeFindings[$findingCode]) || isset($ignoredCodes[$findingCode])) {
                    continue;
                }

                // Проверяем, что в новом крауле реально 0 (а не просто сменили фильтр severity)
                $newCount = (int) ($counts[$findingCode] ?? 0);
                if ($newCount > 0) {
                    continue;
                }

                $base['moved_to_review']++;
                $base['actions'][] = [
                    'action' => 'to_review',
                    'item_id' => (int) $existing->id,
                    'code' => $findingCode,
                    'title' => (string) $existing->title,
                ];

                if (! $dryRun) {
                    $existing->forceFill([
                        'status' => 'review',
                    ])->save();

                    $this->addSystemNote(
                        $existing,
                        $actorUserId,
                        'Аудит #' . $crawl->id . ' больше не находит эту проблему (0 URL). Проверьте и закройте задачу.'
                    );
                }
            }
        }

        if (! $dryRun) {
            $checklist->recalculateProgress();

            $summary = [
                'created' => $base['created'],
                'updated' => $base['updated'],
                'moved_to_review' => $base['moved_to_review'],
                'reopened' => $base['reopened'],
                'skipped_ignored' => $base['skipped_ignored'],
                'skipped_partial_resolve' => $base['skipped_partial_resolve'],
            ];

            $link->forceFill([
                'last_synced_crawl_id' => (int) $crawl->id,
                'last_synced_at' => now(),
                'last_sync_summary_json' => $summary,
            ])->save();
        }

        $base['ok'] = true;

        return $base;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, array<int, string>>
     */
    private function sampleUrls(int $crawlId, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $out = [];
        foreach ($codes as $code) {
            $urls = SiteAuditFinding::query()
                ->where('crawl_id', $crawlId)
                ->where('code', $code)
                ->whereNotNull('url')
                ->where('url', '!=', '')
                ->limit(5)
                ->pluck('url')
                ->all();
            if ($urls !== []) {
                $out[$code] = $urls;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, string>  $sampleUrls
     */
    private function buildHelpText(string $code, array $meta, array $sampleUrls): string
    {
        $help = SiteAuditFindingHelp::forCode($code, $meta);
        $parts = [];
        if (! empty($help['what'])) {
            $parts[] = 'Что найдено: ' . trim((string) $help['what']);
        }
        if (! empty($help['why'])) {
            $parts[] = 'Почему важно: ' . trim((string) $help['why']);
        }
        if (! empty($help['how'])) {
            $parts[] = 'Как исправить: ' . trim((string) $help['how']);
        }
        if ($sampleUrls !== []) {
            $parts[] = "Примеры URL:\n- " . implode("\n- ", array_slice($sampleUrls, 0, 5));
        }

        return implode("\n\n", $parts);
    }

    private function addDays(int $days, bool $skipWeekends): Carbon
    {
        $dt = now()->startOfDay();
        if (! $skipWeekends) {
            return $dt->addDays($days)->endOfDay();
        }

        $left = $days;
        while ($left > 0) {
            $dt->addDay();
            if (! $dt->isWeekend()) {
                $left--;
            }
        }

        return $dt->endOfDay();
    }

    private function addSystemNote(SeoChecklistItem $item, int $userId, string $body): void
    {
        if ($userId < 1) {
            return;
        }
        try {
            SeoChecklistItemNote::query()->create([
                'item_id' => (int) $item->id,
                'user_id' => $userId,
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SiteAuditChecklistSync note failed: ' . $e->getMessage(), [
                'item_id' => $item->id,
            ]);
        }
    }
}
