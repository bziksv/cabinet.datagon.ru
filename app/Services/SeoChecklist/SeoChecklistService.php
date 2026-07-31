<?php

namespace App\Services\SeoChecklist;

use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistProject;
use App\SeoChecklist\SeoChecklistTemplate;
use App\SeoChecklist\SeoChecklistTemplateTask;
use App\Support\HomeUserSites;
use App\Support\SeoChecklistDefaultTemplate;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeoChecklistService
{
    public function ensureSystemTemplate(): SeoChecklistTemplate
    {
        $template = SeoChecklistTemplate::systemDefault();
        if ($template) {
            return $template;
        }

        return DB::transaction(function () {
            $template = SeoChecklistTemplate::query()->create([
                'user_id' => null,
                'code' => SeoChecklistDefaultTemplate::CODE,
                'title' => 'SEO чеклист (стандарт)',
                'description' => 'Базовый шаблон ведения SEO-проекта',
                'is_system' => true,
            ]);

            foreach (SeoChecklistDefaultTemplate::tasks() as $task) {
                SeoChecklistTemplateTask::query()->create([
                    'template_id' => $template->id,
                    'parent_id' => null,
                    'code' => $task['code'],
                    'stage_key' => $task['stage_key'],
                    'stage_sort' => $task['stage_sort'],
                    'sort' => $task['sort'],
                    'title' => $task['title'],
                    'help' => $task['help'],
                    'role' => $task['role'],
                    'is_important' => !empty($task['is_important']),
                    'allows_subtasks' => !empty($task['allows_subtasks']),
                    'repeat_rule' => $task['repeat_rule'] ?? null,
                    'links_json' => $task['links'] ?? [],
                ]);
            }

            return $template;
        });
    }

    /**
     * @return array{ok:bool,message?:string,project?:SeoChecklistProject}
     */
    public function createProject(int $userId, string $rawDomain, ?int $ownerUserId = null, ?int $pmUserId = null): array
    {
        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '') {
            return ['ok' => false, 'message' => __('Invalid domain')];
        }

        $existing = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->first();
        if ($existing) {
            return ['ok' => true, 'project' => $existing];
        }

        try {
            $project = DB::transaction(function () use ($userId, $domain, $ownerUserId, $pmUserId) {
                $template = $this->ensureSystemTemplate();
                $project = SeoChecklistProject::query()->create([
                    'user_id' => $userId,
                    'template_id' => $template->id,
                    'domain' => $domain,
                    'title' => 'SEO — ' . $domain,
                    'status' => 'active',
                    'owner_user_id' => $ownerUserId ?: $userId,
                    'pm_user_id' => $pmUserId,
                    'progress_done' => 0,
                    'progress_total' => 0,
                    'last_activity_at' => now(),
                ]);

                $tasks = $template->tasks()->get();
                foreach ($tasks as $task) {
                    $title = $task->title;
                    if ($task->code === 'project_seo_header') {
                        $title = 'Работа с проектом по SEO — ' . $domain;
                    }

                    SeoChecklistItem::query()->create([
                        'project_id' => $project->id,
                        'parent_id' => null,
                        'code' => $task->code,
                        'stage_key' => $task->stage_key,
                        'stage_sort' => $task->stage_sort,
                        'sort' => $task->sort,
                        'title' => $title,
                        'help' => $task->help,
                        'role' => $task->role,
                        'is_important' => $task->is_important,
                        'allows_subtasks' => $task->allows_subtasks,
                        'repeat_rule' => $task->repeat_rule,
                        'links_json' => $task->links_json ?: [],
                        'status' => 'todo',
                    ]);
                }

                $project->recalculateProgress();

                return $project->fresh();
            });
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => __('Could not create SEO checklist')];
        }

        return ['ok' => true, 'project' => $project];
    }

    public function setItemStatus(SeoChecklistItem $item, string $status, int $userId): bool
    {
        if (!in_array($status, SeoChecklistItem::STATUSES, true)) {
            return false;
        }

        $item->status = $status;
        if ($status === 'done' || $status === 'skip') {
            $item->done_at = now();
            $item->done_by = $userId;
        } else {
            $item->done_at = null;
            $item->done_by = null;
        }
        $item->save();

        if ($item->project) {
            $item->project->recalculateProgress();
        }

        return true;
    }
}
