@component('component.card', [
    'title' => \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id()),
    'documentTitle' => cabinet_sc_document_title($project->domain ?: ($project->title ?: __('Projects'))),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $pct = $project->progress_total > 0
            ? (int) round(100 * $project->progress_done / $project->progress_total)
            : 0;
        $owner = $project->ownerUser;
        $pm = $project->pmUser;
        $ownerLabel = $owner
            ? trim(($owner->name ?? '') . ' ' . ($owner->last_name ?? '')) ?: $owner->email
            : '—';
        $pmLabel = $pm
            ? trim(($pm->name ?? '') . ' ' . ($pm->last_name ?? '')) ?: $pm->email
            : '—';
        $canManage = $canManage ?? true;
        $accessKind = $accessKind ?? 'account';
    @endphp

    <div class="cabinet-sc-page"
         id="cabinetSeoChecklist"
         data-project-id="{{ $project->id }}"
         data-can-manage="{{ $canManage ? '1' : '0' }}"
         data-status-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/status') }}"
         data-note-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/notes') }}"
         data-subtask-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/subtasks') }}"
         data-update-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__') }}"
         data-delete-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/delete') }}"
         data-timer-start-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/timer/start') }}"
         data-timer-stop-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/timer/stop') }}"
         data-time-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/time') }}"
         data-timer-stop-active-url="{{ route('pages.seo-checklist.timer.stop-active') }}"
         data-i18n-time-by-day="{{ e(__('Time by day')) }}"
         data-i18n-no-time-logged="{{ e(__('No time logged yet')) }}"
         data-csrf="{{ csrf_token() }}"
         data-my-roles="{{ implode(',', $myRoles) }}"
         data-can-approve="{{ !empty($canApproveReview) ? '1' : '0' }}"
         data-i18n-comment-required="{{ e(__('Comment required for this status')) }}"
         data-i18n-choose-status="{{ e(__('Choose task status')) }}"
         data-i18n-send-review-first="{{ e(__('Send to review first')) }}"
         data-i18n-only-pm-auditor="{{ e(__('Only PM or auditor can approve')) }}"
         data-i18n-delete-confirm="{{ e(__('Delete this task?')) }}"
         data-i18n-show-completed="{{ e(__('Show completed stages')) }}"
         data-i18n-hide-completed="{{ e(__('Hide completed stages')) }}"
         data-i18n-open-hides-stages="{{ e(__('Open filter already hides completed stages')) }}"
         data-i18n-add-description="{{ e(__('Add description')) }}"
         data-i18n-timer-start="{{ e(__('Start timer')) }}"
         data-i18n-timer-stop="{{ e(__('Stop timer')) }}"
         data-i18n-timer-start-short="{{ e(__('Timer start')) }}"
         data-i18n-timer-stop-short="{{ e(__('Timer stop')) }}">

        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'project',
            'scContextProject' => $project,
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sc-show-head">
            <div>
                <h1 class="cabinet-sc-hero__title mb-1">
                    <a href="https://{{ $project->domain }}" target="_blank" rel="noopener noreferrer" class="cabinet-sc-domain-link">
                        {{ $project->title ?: $project->domain }}
                    </a>
                    @if($accessKind === 'pm')
                        <span class="cabinet-sc-role cabinet-sc-role--pm">{{ __('Shared as PM') }}</span>
                    @elseif($accessKind === 'owner')
                        <span class="cabinet-sc-role cabinet-sc-role--owner">{{ __('Shared as owner') }}</span>
                    @elseif($accessKind === 'auditor')
                        <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('Shared as auditor') }}</span>
                    @elseif($accessKind === 'participant')
                        <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('Shared as participant') }}</span>
                    @endif
                </h1>
                <p class="text-secondary small mb-0">
                    <span data-sc-progress-label>{{ $project->progress_done }}/{{ $project->progress_total }}</span>
                    · <span data-sc-progress-pct>{{ $pct }}%</span>
                    @if($project->status === 'archived')
                        · <span class="text-warning">{{ __('Archive') }}</span>
                    @endif
                </p>
                <p class="cabinet-sc-team-line small mb-0">
                    <span>{{ __('SEO role owner') }}: <strong>{{ $ownerLabel }}</strong></span>
                    <span class="cabinet-sc-team-line__sep">·</span>
                    <span>{{ __('SEO role PM') }}: <strong>{{ $pmLabel }}</strong></span>
                </p>
            </div>
            <div class="cabinet-sc-show-actions">
                @if($project->status !== 'archived' && $project->progress_done < $project->progress_total)
                    <button type="button" class="btn btn-primary btn-sm" data-sc-continue>
                        {{ __('Continue work') }}
                    </button>
                @endif
                <a href="{{ route('pages.seo-checklist.pdf', ['id' => $project->id]) }}" class="btn btn-outline-primary btn-sm">PDF</a>
                @if($canManage)
                    @if($project->status === 'archived')
                        <form method="post" action="{{ route('pages.seo-checklist.restore', ['id' => $project->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Restore') }}</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('pages.seo-checklist.archive', ['id' => $project->id]) }}"
                              onsubmit='return confirm(@json(__("Archive this SEO checklist?")));'>
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('To archive') }}</button>
                        </form>
                    @endif
                    <button type="button"
                            class="btn btn-outline-danger btn-sm"
                            data-sc-delete-project
                            data-url="{{ route('pages.seo-checklist.delete', ['id' => $project->id]) }}"
                            data-domain="{{ $project->domain }}">
                        {{ __('Delete') }}
                    </button>
                @endif
            </div>
        </div>

        <div class="cabinet-sc-progress cabinet-sc-progress--show mb-3" aria-hidden="true">
            <span data-sc-progress-bar style="width: {{ $pct }}%"></span>
        </div>

        @if(count($stages) > 0)
            <nav class="cabinet-sc-stage-nav mb-3" aria-label="{{ __('Stages') }}">
                @foreach($stages as $stage)
                    @php
                        $stagePctNav = $stage['total'] > 0 ? (int) round(100 * $stage['done'] / $stage['total']) : 0;
                        $stageDone = $stage['total'] > 0 && $stage['done'] >= $stage['total'];
                    @endphp
                    <a href="#sc-stage-{{ $stage['key'] }}"
                       class="cabinet-sc-stage-nav__item @if($stageDone) is-complete @endif"
                       data-sc-stage-jump="{{ $stage['key'] }}"
                       title="{{ $stage['title'] }} · {{ $stage['done'] }}/{{ $stage['total'] }}">
                        <span class="cabinet-sc-stage-nav__label">{{ $stage['title'] }}</span>
                        <span class="cabinet-sc-stage-nav__meta">{{ $stagePctNav }}%</span>
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="cabinet-sc-show-toolbar cabinet-sc-show-toolbar--sticky mb-3">
            <div class="cabinet-sc-toolbar-row">
                <input type="search"
                       class="form-control form-control-sm cabinet-sc-search"
                       placeholder="{{ __('Search tasks') }}…"
                       data-sc-task-search
                       autocomplete="off">
                <div class="cabinet-sc-stage-controls">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-stages-expand>{{ __('Expand all') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-stages-collapse>{{ __('Collapse all') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-stages-hide-done>{{ __('Hide completed stages') }}</button>
                </div>
            </div>
            <div class="cabinet-sc-filters" title="{{ __('Filters can be combined') }}">
                <button type="button" class="btn btn-sm btn-outline-secondary active" data-sc-filter="all" aria-pressed="true">{{ __('All') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="open" aria-pressed="false">{{ __('Open tasks') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="important" aria-pressed="false">{{ __('Important') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="overdue" data-tip="{{ __('Overdue filter hint') }}" title="{{ __('Overdue filter hint') }}" aria-pressed="false">{{ __('Overdue') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="due-soon" data-tip="{{ __('Due soon filter hint') }}" title="{{ __('Due soon filter hint') }}" aria-pressed="false">{{ __('Due soon') }}</button>
                @if(count($myRoles) > 0)
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="mine" aria-pressed="false">{{ __('My tasks') }}</button>
                @endif
                <span class="cabinet-sc-filters__sep" aria-hidden="true"></span>
                @foreach($roleLabels as $roleKey => $roleLabel)
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="role:{{ $roleKey }}" aria-pressed="false">{{ $roleLabel }}</button>
                @endforeach
            </div>
            <div class="cabinet-sc-role-stats cabinet-sc-role-stats--inline">
                @foreach(['owner', 'pm', 'shared', 'any'] as $roleKey)
                    @php $rs = $roleStats[$roleKey] ?? ['done' => 0, 'total' => 0]; @endphp
                    @if(($rs['total'] ?? 0) > 0)
                        <span class="cabinet-sc-role-stat">
                            <span class="cabinet-sc-role cabinet-sc-role--{{ $roleKey }}">{{ $roleLabels[$roleKey] ?? $roleKey }}</span>
                            {{ $rs['done'] }}/{{ $rs['total'] }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>

        @if($canManage)
            <div class="cabinet-sc-actions mb-3">
                <details class="cabinet-sc-action cabinet-sc-action--team">
                    <summary class="cabinet-sc-action__summary">
                        <span class="cabinet-sc-action__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                        <span class="cabinet-sc-action__text">
                            <strong>{{ __('SEO checklist team') }}</strong>
                            <em>{{ __('Click to open team settings') }}</em>
                        </span>
                        <span class="cabinet-sc-action__cta">
                            <span class="cabinet-sc-action__cta-open">{{ __('Expand') }}</span>
                            <span class="cabinet-sc-action__cta-close">{{ __('Collapse') }}</span>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </span>
                    </summary>
                    <div class="cabinet-sc-action__body">
                        <form method="post" action="{{ route('pages.seo-checklist.assign-team', ['id' => $project->id]) }}" class="cabinet-sc-team__form">
                            @csrf
                            <input type="hidden" name="return_to" value="show">
                            <div class="cabinet-sc-team__fields">
                                <label>
                                    <span>{{ __('Team') }}</span>
                                    <select name="team_id" class="form-select form-select-sm">
                                        <option value="">{{ __('Optional') }} — {{ __('No team') }}</option>
                                        @foreach(($teams ?? []) as $team)
                                            <option value="{{ $team->id }}" @if((int) $project->team_id === (int) $team->id) selected @endif>
                                                {{ $team->title }} · {{ $team->members_count ?? $team->members->count() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <p class="cabinet-sc-team__hint small text-secondary mb-2">
                                {{ __('SEO checklist assign team hint') }}
                                <a href="{{ route('profile.index') }}#team">{{ __('Manage teams') }}</a>
                            </p>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                        </form>
                        @if($project->team)
                            @php $project->loadMissing(['team.members.user']); @endphp
                            <div class="cabinet-sc-role-groups mt-2">
                                @foreach(($teamRoleLabels ?? []) as $roleKey => $roleLabel)
                                    @php $roleMembers = $project->team->members->where('role', $roleKey); @endphp
                                    @if($roleMembers->isNotEmpty())
                                        <div class="cabinet-sc-role-group">
                                            <div class="cabinet-sc-role-group__title">
                                                <span class="cabinet-sc-role cabinet-sc-role--{{ in_array($roleKey, ['owner', 'pm'], true) ? $roleKey : 'shared' }}">{{ $roleLabel }}</span>
                                            </div>
                                            <ul class="cabinet-sc-role-group__list">
                                                @foreach($roleMembers as $member)
                                                    @php
                                                        $u = $member->user;
                                                        $name = $u ? (trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email) : '—';
                                                    @endphp
                                                    <li><span>{{ $name }}</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </details>

                @if($project->status !== 'archived')
                    <details class="cabinet-sc-action cabinet-sc-action--add">
                        <summary class="cabinet-sc-action__summary">
                            <span class="cabinet-sc-action__icon" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                            <span class="cabinet-sc-action__text">
                                <strong>{{ __('Add task') }}</strong>
                                <em>{{ __('Click to add a new task') }}</em>
                            </span>
                            <span class="cabinet-sc-action__cta">
                                <span class="cabinet-sc-action__cta-open">{{ __('Expand') }}</span>
                                <span class="cabinet-sc-action__cta-close">{{ __('Collapse') }}</span>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </span>
                        </summary>
                        <div class="cabinet-sc-action__body">
                            <form method="post" action="{{ route('pages.seo-checklist.items.store', ['id' => $project->id]) }}" class="cabinet-sc-team__form">
                                @csrf
                                <div class="cabinet-sc-team__fields">
                                    <label>
                                        <span>{{ __('Task') }}</span>
                                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="{{ __('New task') }}…">
                                    </label>
                                    <label>
                                        <span>{{ __('Stage') }}</span>
                                        <select name="stage_key" class="form-select form-select-sm" required>
                                            @foreach(($stagesMeta ?? []) as $sk => $sm)
                                                <option value="{{ $sk }}">{{ $sm['title'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span>{{ __('Role') }}</span>
                                        <select name="role" class="form-select form-select-sm">
                                            @foreach($roleLabels as $rk => $rl)
                                                <option value="{{ $rk }}" @if($rk === 'owner') selected @endif>{{ $rl }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <div class="cabinet-sc-tpl-task__row mb-2">
                                    <select name="repeat_rule" class="form-select form-select-sm">
                                        @include('pages.partials.seo-checklist-repeat-options', ['selected' => ''])
                                    </select>
                                    <label class="small mb-0">
                                        <input type="checkbox" name="is_important" value="1">
                                        {{ __('Important') }}
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Add task') }}</button>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        @endif

        <div class="cabinet-sc-stages">
            @foreach($stages as $stage)
                @php
                    $stagePct = $stage['total'] > 0 ? (int) round(100 * $stage['done'] / $stage['total']) : 0;
                @endphp
                <details class="cabinet-sc-stage"
                         data-sc-stage
                         data-sc-stage-key="{{ $stage['key'] }}"
                         data-complete="{{ ($stage['total'] > 0 && $stage['done'] >= $stage['total']) ? '1' : '0' }}"
                         id="sc-stage-{{ $stage['key'] }}"
                         open>
                    <summary class="cabinet-sc-stage__summary">
                        <span class="cabinet-sc-stage__title-wrap">
                            <span class="cabinet-sc-stage__title">{{ $stage['title'] }}</span>
                            <span class="cabinet-sc-stage__bar" aria-hidden="true"><span style="width: {{ $stagePct }}%"></span></span>
                        </span>
                        <span class="cabinet-sc-stage__meta">{{ $stage['done'] }}/{{ $stage['total'] }} · {{ $stagePct }}%</span>
                    </summary>
                    <ul class="cabinet-sc-tasks">
                        @foreach($stage['items'] as $item)
                            @php
                                $role = in_array($item->role, ['owner', 'pm', 'shared', 'any'], true) ? $item->role : 'any';
                                $itemOverdue = $item->isOverdue();
                                $itemDueSoon = $item->isDueSoon(2);
                                $dueTitle = null;
                                if ($item->due_at) {
                                    $dueDate = $item->due_at->format('d.m.Y');
                                    if ($itemOverdue) {
                                        $dueTitle = __('Overdue tooltip', ['date' => $dueDate]);
                                    } elseif ($itemDueSoon) {
                                        $dueTitle = __('Due soon tooltip', ['date' => $dueDate]);
                                    } else {
                                        $dueTitle = __('Due date') . ': ' . $dueDate;
                                    }
                                }
                                $runningLog = $item->timeLogs->first();
                                $timerRunning = (bool) $runningLog;
                                $displaySeconds = (int) $item->time_spent_seconds + ($runningLog ? $runningLog->elapsedSeconds() : 0);
                            @endphp
                            <li id="sc-item-{{ $item->id }}"
                                class="cabinet-sc-task @if($itemOverdue) is-overdue @elseif($itemDueSoon) is-due-soon @endif @if($timerRunning) is-timing @endif"
                                data-sc-item
                                data-id="{{ $item->id }}"
                                data-status="{{ $item->status }}"
                                data-role="{{ $role }}"
                                data-important="{{ $item->is_important ? '1' : '0' }}"
                                data-overdue="{{ $itemOverdue ? '1' : '0' }}"
                                data-due-soon="{{ $itemDueSoon ? '1' : '0' }}"
                                data-allows-subtasks="{{ $item->allows_subtasks ? '1' : '0' }}"
                                data-time-spent="{{ (int) $item->time_spent_seconds }}"
                                data-timer-running="{{ $timerRunning ? '1' : '0' }}"
                                data-timer-started-at="{{ $timerRunning && $runningLog->started_at ? $runningLog->started_at->toIso8601String() : '' }}"
                                data-search="{{ e(mb_strtolower($item->title . ' ' . ($item->help ?: ''))) }}">
                                <div class="cabinet-sc-task__main">
                                    <label class="cabinet-sc-check" title="{{ __('Status') }}">
                                        <input type="checkbox"
                                               data-sc-done
                                               {{ in_array($item->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true) ? 'checked' : '' }}>
                                    </label>
                                    <div class="cabinet-sc-task__body">
                                        <button type="button"
                                                class="cabinet-sc-task__title {{ $item->is_important ? 'is-important' : '' }}"
                                                data-sc-title
                                                @if($project->status === 'archived') disabled @endif
                                                title="{{ __('Click to edit') }}">
                                            {{ $item->title }}
                                        </button>
                                        @if($item->help || $project->status !== 'archived')
                                            <p class="cabinet-sc-task__help @if(!$item->help) is-empty @endif"
                                               data-sc-help
                                               data-raw-value="{{ e($item->help ?: '') }}"
                                               @if($project->status !== 'archived') title="{{ __('Click to edit') }}" tabindex="0" role="button" @endif>
                                                {{ $item->help ?: __('Add description') }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="cabinet-sc-task__actions">
                                        @if($item->due_at)
                                            <span class="cabinet-sc-due @if($itemOverdue) is-overdue @elseif($itemDueSoon) is-due-soon @endif"
                                                  data-tip="{{ $dueTitle }}"
                                                  title="{{ $dueTitle }}">
                                                @if($itemOverdue)
                                                    {{ __('Overdue') }}
                                                @elseif($itemDueSoon)
                                                    {{ __('Due soon') }}
                                                @else
                                                    {{ __('Due') }} {{ $item->due_at->format('d.m') }}
                                                @endif
                                            </span>
                                        @endif
                                        <button type="button"
                                                class="cabinet-sc-time @if($timerRunning) is-running @endif"
                                                data-sc-time
                                                data-sc-toggle-time
                                                title="{{ __('Time by day') }}">
                                            {{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration($displaySeconds) }}
                                        </button>
                                        @if($project->status !== 'archived')
                                            <button type="button"
                                                    class="btn btn-sm @if($timerRunning) btn-danger @else btn-outline-success @endif"
                                                    data-sc-timer
                                                    title="{{ $timerRunning ? __('Stop timer') : __('Start timer') }}">
                                                {{ $timerRunning ? __('Timer stop') : __('Timer start') }}
                                            </button>
                                        @endif
                                        <span class="cabinet-sc-role cabinet-sc-role--{{ $role }}" title="{{ __('Role') }}">
                                            {{ $roleLabels[$role] ?? $role }}
                                        </span>
                                        @if($item->repeat_rule)
                                            <span class="cabinet-sc-repeat" title="{{ __('Recurring') }}">↻ {{ \App\Support\SeoChecklistDefaultTemplate::repeatRuleLabel($item->repeat_rule) }}</span>
                                        @endif
                                        <select class="form-select form-select-sm" data-sc-status aria-label="{{ __('Status') }}">
                                            @foreach($statusLabels as $value => $label)
                                                @php
                                                    // done/skip — только PM/аудитор (или уже закрытая задача, чтобы статус был виден)
                                                    $hideClosed = in_array($value, ['done', 'skip'], true)
                                                        && empty($canApproveReview)
                                                        && $item->status !== $value;
                                                @endphp
                                                @if(!$hideClosed)
                                                    <option value="{{ $value }}"
                                                            @if($item->status === $value) selected @endif>{{ $label }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-toggle-notes title="{{ __('Notes') }}">
                                            <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                                        </button>
                                        @if($canManage && $project->status !== 'archived')
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-sc-delete title="{{ __('Delete') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @if(!empty($item->links_json))
                                    <div class="cabinet-sc-task__links">
                                        @foreach($item->links_json as $link)
                                            @php
                                                $href = $link['url'] ?? null;
                                                if (!$href && !empty($link['path'])) {
                                                    $href = url($link['path']);
                                                }
                                            @endphp
                                            @if($href)
                                                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer">
                                                    {{ $link['label'] ?? __('Open') }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                <div class="cabinet-sc-subtasks-block" data-sc-subtasks-block>
                                    <div class="cabinet-sc-subtasks-block__head">
                                        <span class="cabinet-sc-subtasks-block__title">
                                            <i class="bi bi-list-check" aria-hidden="true"></i>
                                            {{ __('Subtasks') }}
                                        </span>
                                        <span class="cabinet-sc-subtasks-block__count" data-sc-subtasks-count>
                                            {{ $item->children->count() }}
                                        </span>
                                    </div>
                                    <ul class="cabinet-sc-subtasks" data-sc-subtasks>
                                        @foreach($item->children as $child)
                                            <li class="cabinet-sc-subtask"
                                                data-sc-subitem
                                                data-id="{{ $child->id }}"
                                                data-status="{{ $child->status }}">
                                                <label class="cabinet-sc-check cabinet-sc-check--sub">
                                                    <input type="checkbox"
                                                           data-sc-done
                                                           {{ in_array($child->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true) ? 'checked' : '' }}>
                                                </label>
                                                <button type="button"
                                                        class="cabinet-sc-subtask__title {{ in_array($child->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true) ? 'is-done-text' : '' }}"
                                                        data-sc-title
                                                        @if($project->status === 'archived') disabled @endif
                                                        title="{{ __('Click to edit') }}">
                                                    {{ $child->title }}
                                                </button>
                                                @if($canManage && $project->status !== 'archived')
                                                    <button type="button" class="btn btn-link btn-sm text-danger p-0" data-sc-delete title="{{ __('Delete') }}">×</button>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if($project->status !== 'archived')
                                        <div class="cabinet-sc-subtask-form">
                                            <input type="text"
                                                   class="cabinet-sc-subtask-form__input"
                                                   data-sc-subtask-title
                                                   placeholder="{{ __('Add subtask') }}…"
                                                   aria-label="{{ __('Add subtask') }}">
                                            <button type="button" class="cabinet-sc-subtask-form__add" data-sc-subtask-add>
                                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                                {{ __('Add') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                                <div class="cabinet-sc-task__time d-none" data-sc-time-panel>
                                    <div class="cabinet-sc-time-panel__head">
                                        <strong>{{ __('Time by day') }}</strong>
                                        <span class="text-secondary small" data-sc-time-total></span>
                                    </div>
                                    <ul class="cabinet-sc-time-panel__list" data-sc-time-list></ul>
                                </div>
                                <div class="cabinet-sc-task__notes d-none" data-sc-notes>
                                    <ul class="cabinet-sc-notes-list" data-sc-notes-list>
                                        @foreach($item->notes as $note)
                                            <li>
                                                <div class="cabinet-sc-notes-list__meta">
                                                    <strong class="cabinet-sc-notes-list__author">{{ $note->authorLabel() }}</strong>
                                                    <span class="text-secondary small">{{ $note->created_at->format('d.m.Y H:i') }}</span>
                                                </div>
                                                <div class="cabinet-sc-notes-list__body">{{ $note->body }}</div>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="cabinet-sc-notes-form">
                                        <textarea class="form-control form-control-sm" rows="2" data-sc-note-body placeholder="{{ __('Add a note') }}…"></textarea>
                                        <button type="button" class="btn btn-sm btn-primary" data-sc-note-save>{{ __('Save') }}</button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>
    </div>

    @include('pages.partials.seo-checklist-delete-project-modal')
    @include('pages.partials.seo-checklist-status-modal')

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-status-modal.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-status-modal.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist.js')) ?: time() }}"></script>
    @endslot
@endcomponent
