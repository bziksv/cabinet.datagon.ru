<?php

namespace App\Services\SiteAudit;

use App\SeoChecklist\SeoChecklistProject;
use App\Services\SeoChecklist\SeoChecklistService;
use App\SiteAuditChecklistLink;
use App\SiteAuditProject;
use App\SiteAuditSchedule;
use App\Support\SiteAuditLimits;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Массовое подключение досок SEO-чеклиста команды к Site Audit:
 * для каждого сайта находим/создаём проект аудита, привязываем автозадачи
 * и (если хватает лимита) ставим еженедельное авторасписание в ночные слоты.
 *
 * plan() ничего не пишет, apply() пересчитывает plan() и пишет только выбранные строки.
 */
final class SiteAuditChecklistBulkConnector
{
    /** Ночные часы (МСК-подобно): без пересечения с дневной нагрузкой и окном 11–14. */
    public const NIGHT_HOURS = [2, 3, 4, 5];

    public const STATUS_OK = 'ok';
    public const STATUS_NO_RIGHTS = 'no_rights';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_NO_DOMAIN = 'no_domain';

    public function __construct(private SeoChecklistService $checklists)
    {
    }

    public function teams(User $user): Collection
    {
        return $this->checklists->teamsForUser((int) $user->id);
    }

    /**
     * Слот для i-го нового расписания: дни недели по кругу, затем следующий ночной час.
     *
     * @return array{weekday:int,hour:int}
     */
    public static function slotFor(int $index): array
    {
        $hours = self::NIGHT_HOURS;

        return [
            'weekday' => ($index % 7) + 1,
            'hour' => $hours[intdiv($index, 7) % count($hours)],
        ];
    }

    public static function normalizeDomain(string $raw): string
    {
        $d = strtolower(trim($raw));
        $d = preg_replace('#^https?://#i', '', $d) ?? $d;
        $d = preg_replace('#/.*$#', '', $d) ?? $d;
        $d = preg_replace('/:\d+$/', '', $d) ?? $d;

        return rtrim($d, '/');
    }

    /**
     * @param  array<int,int>|null  $boardIds  null — выбрать все подходящие строки; массив — только эти доски
     * @return array{ok:bool,error:?string,team:?array,rows:list<array<string,mixed>>,summary:array<string,int>}
     */
    public function plan(User $user, int $teamId, ?array $boardIds = null): array
    {
        $empty = ['ok' => false, 'team' => null, 'rows' => [], 'summary' => []];

        if (! SiteAuditProject::teamColumnReady() || ! SiteAuditChecklistLink::tableReady()) {
            return ['error' => 'Нужны миграции (команды и связи с чеклистом)'] + $empty;
        }

        $team = $this->checklists->findAccessibleTeam((int) $user->id, $teamId);
        if (! $team) {
            return ['error' => 'Команда не найдена'] + $empty;
        }

        $boards = SeoChecklistProject::query()
            ->where('team_id', $team->id)
            ->where('status', 'active')
            ->with('template:id,title')
            ->orderBy('domain')
            ->orderBy('id')
            ->get(['id', 'user_id', 'team_id', 'domain', 'title', 'template_id', 'status']);

        $domains = $boards
            ->map(fn ($b) => self::normalizeDomain((string) $b->domain))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $auditByDomain = SiteAuditProject::query()
            ->where('user_id', $user->id)
            ->whereIn('domain', $domains)
            ->get()
            ->keyBy(fn (SiteAuditProject $p) => self::normalizeDomain((string) $p->domain));

        $auditIdsWithSchedule = SiteAuditSchedule::query()
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->whereIn('project_id', $auditByDomain->pluck('id')->all() ?: [0])
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $freeSlots = max(0, SiteAuditLimits::schedulesLimit($user) - SiteAuditLimits::schedulesUsed($user));
        $slotIndex = 0;
        $seen = [];
        $rows = [];

        foreach ($boards as $board) {
            $domain = self::normalizeDomain((string) $board->domain);
            $audit = $domain !== '' ? $auditByDomain->get($domain) : null;

            $row = [
                'board_id' => (int) $board->id,
                'board_title' => (string) ($board->title ?: ($board->template->title ?? 'SEO-чеклист')),
                'domain' => $domain,
                'status' => self::STATUS_OK,
                'note' => '',
                'audit_project_id' => $audit ? (int) $audit->id : null,
                'audit_exists' => (bool) $audit,
                'has_schedule' => false,
                'slot' => null,
                'slot_label' => null,
                'selected' => false,
            ];

            if ($domain === '') {
                $row['status'] = self::STATUS_NO_DOMAIN;
                $row['note'] = 'У доски не указан сайт';
            } elseif (! $this->checklists->canManageProject($board, (int) $user->id)) {
                $row['status'] = self::STATUS_NO_RIGHTS;
                $row['note'] = 'Доска другого участника — подключит её владелец';
            } elseif (isset($seen[$domain])) {
                $row['status'] = self::STATUS_DUPLICATE;
                $row['note'] = 'Для этого сайта уже выбрана другая доска';
            } else {
                $seen[$domain] = true;

                if ($audit && (int) ($audit->team_id ?? 0) > 0 && (int) $audit->team_id !== (int) $team->id) {
                    $row['note'] = 'Проект аудита переедет в эту команду';
                }

                if ($audit && in_array((int) $audit->id, $auditIdsWithSchedule, true)) {
                    $row['has_schedule'] = true;
                    $row['note'] = trim($row['note'] . ' Авторасписание уже включено');
                } elseif ($freeSlots > 0) {
                    $slot = self::slotFor($slotIndex++);
                    $freeSlots--;
                    $row['slot'] = $slot;
                    $row['slot_label'] = $this->slotLabel($slot);
                    $row['note'] = trim($row['note'] . ' Новое авторасписание');
                } else {
                    $row['note'] = trim($row['note'] . ' Лимит авторасписаний исчерпан — подключим без расписания');
                }
            }

            $row['selected'] = $row['status'] === self::STATUS_OK
                && ($boardIds === null || in_array((int) $board->id, $boardIds, true));

            $rows[] = $row;
        }

        $summary = [
            'boards' => count($rows),
            'ok' => count(array_filter($rows, fn ($r) => $r['status'] === self::STATUS_OK)),
            'selected' => count(array_filter($rows, fn ($r) => $r['selected'])),
            'new_schedules' => count(array_filter($rows, fn ($r) => $r['selected'] && $r['slot'] !== null)),
            'has_schedule' => count(array_filter($rows, fn ($r) => $r['selected'] && $r['has_schedule'])),
            'no_schedule' => count(array_filter($rows, fn ($r) => $r['selected'] && $r['slot'] === null && ! $r['has_schedule'])),
            'skipped' => count(array_filter($rows, fn ($r) => $r['status'] !== self::STATUS_OK)),
            'free_slots_left' => $freeSlots,
        ];

        return [
            'ok' => true,
            'error' => null,
            'team' => ['id' => (int) $team->id, 'title' => (string) $team->title],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<int,int>  $boardIds
     * @return array{ok:bool,error:?string,linked:int,scheduled:int,summary:array<string,int>}
     */
    public function apply(User $user, int $teamId, array $boardIds): array
    {
        $plan = $this->plan($user, $teamId, array_values(array_map('intval', $boardIds)));
        if (! $plan['ok']) {
            return ['ok' => false, 'error' => $plan['error'], 'linked' => 0, 'scheduled' => 0, 'summary' => []];
        }

        $teamIdResolved = (int) $plan['team']['id'];
        $linked = 0;
        $scheduled = 0;

        DB::transaction(function () use ($user, $teamIdResolved, $plan, &$linked, &$scheduled) {
            foreach ($plan['rows'] as $row) {
                if (! $row['selected']) {
                    continue;
                }

                $audit = SiteAuditProject::query()->firstOrCreate(
                    ['user_id' => $user->id, 'domain' => $row['domain']],
                    ['name' => $row['domain'], 'settings_json' => []]
                );

                if ((int) ($audit->team_id ?? 0) !== $teamIdResolved) {
                    $audit->team_id = $teamIdResolved;
                    $audit->save();
                }

                $link = SiteAuditChecklistLink::query()->firstOrNew(['site_audit_project_id' => $audit->id]);
                if (! $link->exists) {
                    $link->fill([
                        'severities_json' => SiteAuditChecklistLink::DEFAULT_SEVERITIES,
                        'stage_mode' => 'smart',
                        'fallback_stage_key' => 'audits',
                        'default_role' => 'owner',
                        'due_days_critical' => 3,
                        'due_days_important' => 7,
                        'due_days_other' => 14,
                        'created_by' => (int) $user->id,
                    ]);
                }
                $link->checklist_project_id = (int) $row['board_id'];
                $link->enabled = true;
                $link->save();
                $linked++;

                if ($row['slot'] !== null) {
                    $schedule = SiteAuditSchedule::query()->firstOrNew([
                        'user_id' => $user->id,
                        'project_id' => $audit->id,
                    ]);
                    $schedule->domain = $audit->domain;
                    $schedule->enabled = true;
                    $schedule->frequency = SiteAuditSchedule::FREQ_WEEKLY;
                    $schedule->settings_json = [
                        'crawl_speed' => 'normal',
                        'concurrency' => SiteAuditLimits::resolveConcurrency($user, 1),
                        'pages_limit' => SiteAuditLimits::resolvePagesLimit($user, null),
                        'weekday' => (int) $row['slot']['weekday'],
                        'hour' => (int) $row['slot']['hour'],
                        'save_html' => 'off',
                    ];
                    $schedule->next_run_at = $schedule->computeNextRun(Carbon::now());
                    $schedule->save();
                    $scheduled++;
                }
            }
        });

        return [
            'ok' => true,
            'error' => null,
            'linked' => $linked,
            'scheduled' => $scheduled,
            'summary' => $plan['summary'],
        ];
    }

    /**
     * @param  array{weekday:int,hour:int}  $slot
     */
    public function slotLabel(array $slot): string
    {
        $days = SiteAuditSchedule::weekdayLabels();
        $day = $days[$slot['weekday']] ?? (string) $slot['weekday'];

        return $day . ', ' . str_pad((string) $slot['hour'], 2, '0', STR_PAD_LEFT) . ':00';
    }
}
