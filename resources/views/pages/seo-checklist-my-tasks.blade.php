@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title(__('My tasks')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $groups = $plan['groups'] ?? [];
        $stages = $plan['stages'] ?? [];
        $planSections = !empty($stages) ? $stages : array_values(array_filter($groups, function ($g) {
            return ($g['items'] ?? collect())->count() > 0;
        }));
        $hasAny = count($planSections) > 0;
        $statusLabels = $statusLabels ?? [];
        $filterProjects = $filterProjects ?? collect();
        $manageableProjects = $manageableProjects ?? collect();
        $projectStagesMap = $projectStagesMap ?? [];
        $planCount = (int) ($plan['count'] ?? $myTasksCount ?? 0);
        $overdueCount = (int) ($plan['overdue'] ?? 0);
        $useStages = !empty($stages);
    @endphp

    <div class="cabinet-sc-page cabinet-sc-plan-v2"
         id="cabinetSeoChecklistPlan"
         data-plan-visual="v2"
         data-sc-hub="my-tasks"
         data-csrf="{{ csrf_token() }}"
         data-status-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/status') }}"
         data-note-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/notes') }}"
         data-subtask-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/subtasks') }}"
         data-subtask-reorder-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/subtasks/reorder') }}"
         data-update-url-template="{{ url('/checklist/__PROJECT__/items/__ID__') }}"
         data-mark-read-url="{{ route('pages.seo-checklist.chronicle.read') }}"
         data-mark-unread-url="{{ route('pages.seo-checklist.chronicle.unread') }}"
         data-timer-start-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/start') }}"
         data-timer-stop-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/stop') }}"
         data-timer-stop-active-url="{{ route('pages.seo-checklist.timer.stop-active') }}"
         data-i18n-comment-required="{{ e(__('Comment required for this status')) }}"
         data-i18n-choose-status="{{ e(__('Choose task status')) }}"
         data-i18n-timer-start="{{ e(__('Start timer')) }}"
         data-i18n-timer-stop="{{ e(__('Stop timer')) }}"
         data-i18n-timer-start-short="{{ e(__('Timer start')) }}"
         data-i18n-timer-stop-short="{{ e(__('Timer stop')) }}"
         data-i18n-waiting-review="{{ e(__('Waiting for review')) }}"
         data-i18n-mark-read="{{ e(__('Mark as read')) }}"
         data-i18n-mark-unread="{{ e(__('Mark note unread')) }}"
         data-i18n-mark-unread-short="{{ e(__('Mark unread short')) }}"
         data-i18n-unread="{{ e(__('Unread')) }}"
         data-i18n-send-review-first="{{ e(__('Send to review first')) }}"
         data-i18n-close-subs-first="{{ e(__('Close open checklist items first')) }}"
         data-i18n-include-report="{{ e(__('Include in SEO reports')) }}"
         data-i18n-include-report-hint="{{ e(__('Include in SEO reports hint')) }}">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'my-tasks',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <div class="cabinet-sc-plan-topbar">
            <div class="cabinet-sc-plan-topbar__main">
                <div>
                    <p class="cabinet-sc-plan-topbar__eyebrow">{{ __('My tasks') }}</p>
                    <h1 class="cabinet-sc-plan-topbar__title">{{ __('Work plan') }}</h1>
                    <p class="cabinet-sc-plan-topbar__meta">
                        {{ number_format($planCount, 0, '', ' ') }} {{ __('Tasks') }}
                        @if($overdueCount > 0)
                            · <span class="cabinet-sc-plan-topbar__hot">{{ number_format($overdueCount, 0, '', ' ') }} {{ __('Overdue') }}</span>
                        @endif
                        · {{ number_format(count($planSections), 0, '', ' ') }} {{ $useStages ? __('Stages') : __('Groups') }}
                    </p>
                </div>
                <div class="cabinet-sc-plan-topbar__actions">
                    <a href="{{ route('pages.seo-checklist') }}" class="cabinet-sc-plan-chip-btn">{{ __('Projects') }}</a>
                </div>
            </div>
        </div>

        @if($manageableProjects->isNotEmpty())
            <details class="cabinet-sc-action cabinet-sc-action--add mb-3" data-sc-my-task-add>
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
                    <form method="post"
                          action=""
                          class="cabinet-sc-team__form"
                          data-sc-my-task-form
                          data-action-template="{{ url('/checklist/__PROJECT__/items') }}">
                        @csrf
                        <div class="cabinet-sc-team__fields">
                            <label>
                                <span>{{ __('Project') }}</span>
                                <select name="project_id"
                                        class="form-select form-select-sm"
                                        data-sc-my-task-project
                                        data-placeholder="{{ __('Select project') }}"
                                        required>
                                    <option value=""></option>
                                    @foreach($manageableProjects as $mp)
                                        @php
                                            $boardName = ($mp->template && $mp->template->title) ? $mp->template->title : (($mp->title && $mp->title !== $mp->domain) ? $mp->title : null);
                                        @endphp
                                        <option value="{{ $mp->id }}">{{ $mp->domain }}@if($boardName) · {{ $boardName }}@endif</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>{{ __('Task') }}</span>
                                <input type="text" name="title" class="form-control form-control-sm" required placeholder="{{ __('New task') }}…">
                            </label>
                            <label>
                                <span>{{ __('Stage') }}</span>
                                <select name="stage_key" class="form-select form-select-sm" data-sc-my-task-stage required></select>
                            </label>
                            <label>
                                <span>{{ __('Role') }}</span>
                                <select name="role" class="form-select form-select-sm">
                                    @foreach($roleLabels as $rk => $rl)
                                        <option value="{{ $rk }}" @if($rk === 'owner') selected @endif>{{ $rl }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>{{ __('Due date') }}</span>
                                <input type="date" name="due_at" class="form-control form-control-sm">
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
                            <label class="small mb-0" data-tip="{{ __('Include in SEO reports hint') }}">
                                <input type="checkbox" name="include_in_report" value="1" checked>
                                {{ __('Include in SEO reports') }}
                            </label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-sm btn-primary" data-sc-my-task-submit>{{ __('Add task') }}</button>
                            <span class="small text-danger" data-sc-my-task-error></span>
                        </div>
                    </form>
                </div>
            </details>
        @endif

        @if(!$hasAny)
            <div class="cabinet-sc-empty">
                <i class="bi bi-calendar2-check display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No upcoming tasks') }}</p>
                <p class="small text-secondary mb-3">{{ __('No upcoming tasks hint') }}</p>
                <a href="{{ route('pages.seo-checklist') }}" class="btn btn-primary btn-sm">{{ __('Open projects') }}</a>
            </div>
        @else
            <div class="cabinet-sc-plan-layout">
                <div class="cabinet-sc-plan-rail-slot" data-sc-plan-rail-slot>
                    <nav class="cabinet-sc-plan-rail" data-sc-plan-rail aria-label="{{ $useStages ? __('Stages') : __('Groups') }}">
                        <div class="cabinet-sc-plan-rail__head">{{ $useStages ? __('Stages') : __('Groups') }}</div>
                        <ul class="cabinet-sc-plan-rail__list">
                            @foreach($planSections as $railGroup)
                                <li>
                                    <div class="cabinet-sc-plan-rail__item">
                                        <a class="cabinet-sc-plan-rail__link"
                                           href="#sc-plan-{{ $railGroup['key'] }}"
                                           data-sc-plan-rail-link="{{ $railGroup['key'] }}">
                                            <span class="cabinet-sc-plan-rail__name">{{ $railGroup['title'] }}</span>
                                            <span class="cabinet-sc-plan-rail__count">{{ number_format($railGroup['count'] ?? ($railGroup['items'] ?? collect())->count(), 0, '', ' ') }}</span>
                                        </a>
                                        @if(!empty($railGroup['chips']))
                                            <div class="cabinet-sc-plan-rail__chips" role="group" aria-label="{{ __('Status') }}">
                                                @foreach($railGroup['chips'] as $chip)
                                                    <button type="button"
                                                            class="cabinet-sc-plan-rail__chip cabinet-sc-plan-rail__chip--{{ $chip['key'] }}"
                                                            data-sc-plan-rail-chip="{{ $chip['key'] }}"
                                                            data-sc-plan-rail-chip-stage="{{ $railGroup['key'] }}"
                                                            data-tip="{{ $chip['title'] }} — {{ number_format($chip['count'], 0, '', ' ') }}"
                                                            aria-label="{{ $chip['title'] }} — {{ number_format($chip['count'], 0, '', ' ') }}">
                                                        <i class="bi {{ $chip['icon'] }}" aria-hidden="true"></i>
                                                        <span>{{ number_format($chip['count'], 0, '', ' ') }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </div>

                <div class="cabinet-sc-plan-workspace">
                    @component('pages.partials.seo-checklist-task-filters', [
                        'context' => 'plan',
                        'reviewCount' => (int) ($reviewCount ?? 0),
                        'showReview' => !empty($showReviewTab) && (int) ($reviewCount ?? 0) > 0,
                        'searchId' => 'cabinetScPlanSearch',
                    ])
                        @slot('leading')
                            @include('pages.partials.seo-checklist-task-filters-leading-plan', [
                                'filterProjects' => $filterProjects ?? [],
                            ])
                        @endslot
                    @endcomponent

                    <div class="cabinet-sc-plan-filter-empty d-none"
                         data-sc-plan-filter-empty
                         data-i18n-empty-default="{{ e(__('No tasks match filters')) }}"
                         data-i18n-empty-project="{{ e(__('No plan tasks for this project')) }}"
                         data-i18n-empty-done="{{ e(__('Only completed tasks in plan for this project')) }}"
                         hidden>
                        <p class="small text-secondary mb-2" data-sc-plan-filter-empty-text>{{ __('No tasks match filters') }}</p>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary d-none"
                                data-sc-plan-filter-empty-done>
                            {{ __('Show completed') }}
                        </button>
                    </div>

                    <div class="cabinet-sc-plan">
                        @foreach($planSections as $group)
                            @php $items = $group['items'] ?? collect(); @endphp
                            @if($items->count() === 0)
                                @continue
                            @endif
                            <section class="cabinet-sc-plan__group cabinet-sc-plan__group--stage"
                                     id="sc-plan-{{ $group['key'] }}"
                                     data-sc-plan-group="{{ $group['key'] }}">
                                <header class="cabinet-sc-plan__group-head">
                                    <h3 class="cabinet-sc-plan__group-title">{{ $group['title'] }}</h3>
                                    <span class="cabinet-sc-plan__group-count" data-sc-plan-count>{{ number_format($items->count(), 0, '', ' ') }}</span>
                                </header>
                                <ul class="cabinet-sc-plan__list">
                                    @foreach($items as $item)
                                        @include('pages.partials.seo-checklist-plan-item', [
                                            'item' => $item,
                                            'roleLabels' => $roleLabels,
                                            'statusLabels' => $statusLabels,
                                            'myRolesOnProject' => ($membershipRolesByProject ?? [])[(int) $item->project_id] ?? [],
                                            'canApprove' => $item->project
                                                ? app(\App\Services\SeoChecklist\SeoChecklistService::class)
                                                    ->canApproveReview($item->project, (int) auth()->id())
                                                : false,
                                            'canManage' => $item->project
                                                ? app(\App\Services\SeoChecklist\SeoChecklistService::class)
                                                    ->canManageProject($item->project, (int) auth()->id())
                                                : false,
                                        ])
                                    @endforeach
                                </ul>
                                @php
                                    $stageProjects = $useStages
                                        ? $manageableProjects->filter(function ($mp) use ($projectStagesMap, $group) {
                                            $stList = $projectStagesMap[(int) $mp->id] ?? [];
                                            foreach ($stList as $st) {
                                                if (($st['key'] ?? '') === (string) $group['key']) {
                                                    return true;
                                                }
                                            }
                                            return false;
                                        })->values()
                                        : collect();
                                @endphp
                                @if($useStages && $stageProjects->isNotEmpty())
                                    <details class="cabinet-sc-stage-add" data-sc-stage-add-details>
                                        <summary class="cabinet-sc-stage-add__summary">
                                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                            <span>{{ __('Add task') }}</span>
                                        </summary>
                                        <form method="post"
                                              action=""
                                              class="cabinet-sc-stage-add__form"
                                              data-sc-my-task-form
                                              data-action-template="{{ url('/checklist/__PROJECT__/items') }}">
                                            @csrf
                                            <input type="hidden" name="stage_key" value="{{ $group['key'] }}">
                                            <input type="hidden" name="include_in_report" value="1">
                                            <div class="cabinet-sc-stage-add__project">
                                                <select name="project_id"
                                                        class="form-select form-select-sm"
                                                        data-sc-my-task-stage-project
                                                        data-placeholder="{{ __('Select project') }}"
                                                        required>
                                                    <option value=""></option>
                                                    @foreach($stageProjects as $mp)
                                                        @php
                                                            $boardName = ($mp->template && $mp->template->title) ? $mp->template->title : (($mp->title && $mp->title !== $mp->domain) ? $mp->title : null);
                                                        @endphp
                                                        <option value="{{ $mp->id }}">{{ $mp->domain }}@if($boardName) · {{ $boardName }}@endif</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="text"
                                                   name="title"
                                                   class="form-control form-control-sm cabinet-sc-stage-add__title"
                                                   required
                                                   placeholder="{{ __('New task') }}…">
                                            <input type="date"
                                                   name="due_at"
                                                   class="form-control form-control-sm cabinet-sc-stage-add__date"
                                                   aria-label="{{ __('Due date') }}">
                                            <select name="role" class="form-select form-select-sm cabinet-sc-stage-add__role" aria-label="{{ __('Role') }}">
                                                @foreach($roleLabels as $rk => $rl)
                                                    <option value="{{ $rk }}" @if($rk === 'owner') selected @endif>{{ $rl }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary" data-sc-my-task-submit>{{ __('Add') }}</button>
                                            <span class="small text-danger" data-sc-my-task-error></span>
                                        </form>
                                    </details>
                                @endif
                            </section>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

    </div>

    @include('partials.cabinet-confirm-modal')
    @include('pages.partials.seo-checklist-status-modal')

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-attach.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-attach.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-status-modal.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-status-modal.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-notes.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-notes.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-plan.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-plan.js')) ?: time() }}"></script>
        <script>
            (function () {
                if (!window.jQuery || !jQuery.fn.select2) return;
                var select2Lang = {
                    noResults: function () { return @json(__('Nothing found')); },
                    searching: function () { return @json(__('Searching')); }
                };
                var $el = jQuery('[data-sc-plan-project]');
                if ($el.length) {
                    $el.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: $el.data('placeholder') || '',
                        allowClear: true,
                        language: select2Lang
                    });
                }

                var stagesMap = @json($projectStagesMap);
                var $proj = jQuery('[data-sc-my-task-project]');
                var $stage = jQuery('[data-sc-my-task-stage]');

                function syncStages(projectId) {
                    if (!$stage.length) return;
                    var list = stagesMap[String(projectId)] || [];
                    $stage.empty();
                    list.forEach(function (st) {
                        $stage.append(jQuery('<option>').val(st.key).text(st.title));
                    });
                }

                if ($proj.length) {
                    $proj.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: $proj.data('placeholder') || '',
                        allowClear: false,
                        language: select2Lang
                    });
                    $proj.on('change', function () {
                        syncStages(jQuery(this).val());
                    });
                    var initialProj = ($el.length && $el.val()) || $proj.find('option[value!=""]').first().val() || '';
                    if (initialProj) {
                        $proj.val(initialProj).trigger('change');
                    }
                }

                // Ленивая инициализация Select2 для инлайн-форм внизу каждого этапа (toggle не всплывает — слушаем на capture)
                document.addEventListener('toggle', function (ev) {
                    var el = ev.target;
                    if (!el || !el.matches || !el.matches('[data-sc-stage-add-details]') || !el.open) return;
                    var $sp = jQuery(el).find('[data-sc-my-task-stage-project]');
                    if (!$sp.length || $sp.data('select2')) return;
                    $sp.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: $sp.data('placeholder') || '',
                        allowClear: false,
                        language: select2Lang
                    });
                    var filterPid = ($el.length && $el.val()) || '';
                    var preselect = (filterPid && $sp.find('option[value="' + filterPid + '"]').length)
                        ? filterPid
                        : ($sp.find('option[value!=""]').first().val() || '');
                    if (preselect) {
                        $sp.val(preselect).trigger('change');
                    }
                }, true);

                jQuery(document).on('submit', '[data-sc-my-task-form]', function (e) {
                    e.preventDefault();
                    var $f = jQuery(this);
                    var $p = $f.find('select[name="project_id"]');
                    var $e = $f.find('[data-sc-my-task-error]');
                    var pid = $p.val();
                    if (!pid) {
                        $e.text(@json(__('Select project')));
                        return;
                    }
                    $e.text('');
                    var tpl = String($f.data('action-template') || '');
                    var url = tpl.replace('__PROJECT__', encodeURIComponent(pid));
                    var $btn = $f.find('[data-sc-my-task-submit]');
                    $btn.prop('disabled', true);

                    var fd = new FormData($f[0]);
                    fd.delete('project_id');
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: fd
                    }).then(function (r) {
                        return r.json().catch(function () { return { ok: false, message: 'Ошибка сервера' }; });
                    }).then(function (data) {
                        if (data && data.ok) {
                            window.location.reload();
                            return;
                        }
                        $e.text((data && data.message) || @json(__('Error')));
                        $btn.prop('disabled', false);
                    }).catch(function () {
                        $e.text('Ошибка сети — попробуйте ещё раз');
                        $btn.prop('disabled', false);
                    });
                });
            })();
        </script>
    @endslot
@endcomponent

