@component('component.card', [
    'title' => \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id()),
    'documentTitle' => cabinet_sc_document_title($template->title ?: __('Templates')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page"
         data-sc-hub="templates"
         data-csrf="{{ csrf_token() }}"
         data-tpl-move-url-template="{{ url('/checklist/templates/'.$template->id.'/tasks/__ID__/move') }}"
         data-tpl-stage-move-url-template="{{ url('/checklist/templates/'.$template->id.'/stages/__KEY__/move') }}"
         data-tpl-stage-update-url-template="{{ url('/checklist/templates/'.$template->id.'/stages/__KEY__') }}"
         data-tpl-stage-delete-url-template="{{ url('/checklist/templates/'.$template->id.'/stages/__KEY__/delete') }}"
         data-tpl-subtask-url-template="{{ url('/checklist/templates/'.$template->id.'/tasks/__ID__/subtasks') }}"
         data-i18n-delete-subtask="{{ e(__('Delete this subtask?')) }}"
         data-i18n-delete-stage="{{ e(__('Delete this stage?')) }}"
         data-i18n-stage-has-tasks="{{ e(__('Stage has tasks')) }}">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'template',
            'scContextTemplate' => $template,
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <div class="cabinet-sc-show-head">
            <div>
                <h1 class="cabinet-sc-hero__title mb-1">{{ $template->title }}</h1>
                <p class="small text-secondary mb-0">
                    {{ __('Used in :count projects', ['count' => (int) ($usageCount ?? 0)]) }}
                    @if($template->is_system && $readOnly)
                        · {{ __('System template is read-only') }}
                    @elseif($template->is_system && !$readOnly)
                        · {{ __('System template admin editable') }}
                    @endif
                </p>
            </div>
            <div class="cabinet-sc-show-actions">
                <form method="post" action="{{ route('pages.seo-checklist.templates.duplicate', ['templateId' => $template->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Duplicate') }}</button>
                </form>
                @if(!$readOnly && !$template->is_system)
                    @if(($usageCount ?? 0) > 0)
                        <button type="button" class="btn btn-outline-danger btn-sm" disabled title="{{ __('Template is used by projects') }}">{{ __('Delete') }}</button>
                    @else
                        <form method="post" action="{{ route('pages.seo-checklist.templates.delete', ['templateId' => $template->id]) }}"
                              onsubmit='return confirm(@json(__("Delete this template?")));'>
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        @if(!$readOnly)
            <form method="post" action="{{ route('pages.seo-checklist.templates.update', ['templateId' => $template->id]) }}" class="cabinet-sc-tpl-settings mb-3">
                @csrf
                <div class="cabinet-sc-tpl-settings__grid">
                    <label class="cabinet-sc-tpl-settings__field">
                        <span>{{ __('Title') }}</span>
                        <input type="text" name="title" class="form-control" required value="{{ old('title', $template->title) }}">
                    </label>
                    <label class="cabinet-sc-tpl-settings__field">
                        <span>{{ __('Description') }}</span>
                        <input type="text" name="description" class="form-control" value="{{ old('description', $template->description) }}" placeholder="{{ __('Optional') }}">
                    </label>
                </div>
                <div class="cabinet-sc-tpl-settings__footer">
                    <label class="cabinet-sc-tpl-settings__check" title="{{ __('Skip weekends hint') }}">
                        <input type="checkbox" name="skip_weekends" value="1" @if(old('skip_weekends', $template->skip_weekends)) checked @endif>
                        <span>
                            <strong>{{ __('Skip weekends in due dates') }}</strong>
                            <em>{{ __('Skip weekends hint') }}</em>
                        </span>
                    </label>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        @elseif(!empty($template->skip_weekends))
            <p class="small text-secondary mb-3">{{ __('Skip weekends in due dates') }}: {{ __('Yes') }}</p>
        @endif

        <div class="cabinet-sc-toolbar cabinet-sc-toolbar--sticky mb-3" data-sc-tpl-search-bar>
            <input type="search"
                   class="form-control form-control-sm cabinet-sc-search"
                   placeholder="{{ __('Smart search checklist') }}…"
                   data-sc-tpl-search
                   autocomplete="off"
                   aria-label="{{ __('Smart search checklist') }}">
            <span class="cabinet-sc-search-count small text-secondary" data-sc-tpl-search-count></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-chip="important">{{ __('Important') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-chip="repeat">{{ __('Recurring') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-expand>{{ __('Expand all') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-collapse>{{ __('Collapse all') }}</button>
        </div>
        <p class="cabinet-sc-empty-filter small text-secondary d-none mb-3" data-sc-tpl-empty>{{ __('No tasks match filters') }}</p>

        @php
            $tplTaskTotal = 0;
            foreach ($stages as $st) {
                $tplTaskTotal += count($st['tasks'] ?? []);
            }
            $stageCount = count($stages);
        @endphp
        @if(!$readOnly && $stageCount === 0)
            <div class="cabinet-sc-empty-stages mb-3">
                <div class="cabinet-sc-empty-stages__text">
                    <strong>{{ __('No stages yet') }}</strong>
                    <span>{{ __('No stages yet hint') }}</span>
                </div>
                <form method="post" action="{{ route('pages.seo-checklist.templates.stage.skeleton', ['templateId' => $template->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('Apply SEO skeleton') }}</button>
                </form>
            </div>
        @elseif(!$readOnly && $tplTaskTotal === 0)
            <div class="alert alert-info py-2 px-3 small mb-3" role="status">
                {{ __('Empty template how to add') }}
            </div>
        @endif

        <div class="cabinet-sc-stages" data-sc-tpl-stages>
            @foreach($stages as $stageIndex => $stage)
                @php
                    $stageSearch = mb_strtolower(trim(
                        ($stage['title'] ?? '') . ' ' .
                        ($stage['lead'] ?? '') . ' ' .
                        ($stage['key'] ?? '')
                    ));
                    $isFirstStage = $stageIndex === 0;
                    $isLastStage = $stageIndex === $stageCount - 1;
                    $stageTaskCount = count($stage['tasks'] ?? []);
                @endphp
                <details class="cabinet-sc-stage" data-sc-tpl-stage data-stage-key="{{ $stage['key'] }}" open>
                    <summary class="cabinet-sc-stage__summary">
                        @if(!$readOnly)
                            <span class="cabinet-sc-stage__order" data-sc-tpl-stage-controls>
                                <button type="button"
                                        class="cabinet-sc-stage__order-btn"
                                        data-sc-tpl-stage-move="up"
                                        @if($isFirstStage) disabled @endif
                                        title="{{ __('Move up') }}"
                                        aria-label="{{ __('Move up') }}">↑</button>
                                <button type="button"
                                        class="cabinet-sc-stage__order-btn"
                                        data-sc-tpl-stage-move="down"
                                        @if($isLastStage) disabled @endif
                                        title="{{ __('Move down') }}"
                                        aria-label="{{ __('Move down') }}">↓</button>
                            </span>
                            <span class="cabinet-sc-stage__edit" data-sc-tpl-stage-controls>
                                <input type="text"
                                       class="cabinet-sc-stage__title-input"
                                       data-sc-tpl-stage-title
                                       value="{{ $stage['title'] }}"
                                       aria-label="{{ __('Stage title') }}">
                                <input type="text"
                                       class="cabinet-sc-stage__lead-input"
                                       data-sc-tpl-stage-lead
                                       value="{{ $stage['lead'] ?? '' }}"
                                       placeholder="{{ __('Stage lead optional') }}"
                                       aria-label="{{ __('Stage lead optional') }}">
                            </span>
                            <span class="cabinet-sc-stage__aside" data-sc-tpl-stage-controls>
                                <span class="cabinet-sc-stage__meta" data-sc-tpl-stage-meta data-total="{{ $stageTaskCount }}">{{ $stageTaskCount }}</span>
                                <button type="button"
                                        class="cabinet-sc-stage__delete"
                                        data-sc-tpl-stage-delete
                                        @if($stageTaskCount > 0) disabled title="{{ __('Stage has tasks') }}" @else title="{{ __('Delete stage') }}" @endif
                                        aria-label="{{ __('Delete stage') }}">×</button>
                            </span>
                        @else
                            <span class="cabinet-sc-stage__title-wrap">
                                <span class="cabinet-sc-stage__title">{{ $stage['title'] }}</span>
                                @if(!empty($stage['lead']))
                                    <span class="cabinet-sc-stage__lead text-secondary">{{ $stage['lead'] }}</span>
                                @endif
                            </span>
                            <span class="cabinet-sc-stage__meta" data-sc-tpl-stage-meta data-total="{{ $stageTaskCount }}">{{ $stageTaskCount }}</span>
                        @endif
                    </summary>
                    <ul class="cabinet-sc-tasks">
                        @foreach($stage['tasks'] as $taskIndex => $task)
                            @php
                                $taskLinks = is_array($task->links_json) ? $task->links_json : [];
                                $linkBlob = collect($taskLinks)->map(function ($link) {
                                    return trim(($link['label'] ?? '') . ' ' . ($link['path'] ?? ''));
                                })->implode(' ');
                                $roleLabel = $roleLabels[$task->role] ?? $task->role;
                                $taskSearch = mb_strtolower(trim(implode(' ', array_filter([
                                    $task->title,
                                    $task->help,
                                    $task->code,
                                    $task->stage_key,
                                    $task->role,
                                    $roleLabel,
                                    $task->repeat_rule,
                                    $task->is_important ? 'важн important' : null,
                                    $task->repeat_rule ? 'повтор recurring monthly weekly' : null,
                                    $linkBlob,
                                    $stageSearch,
                                ]))));
                                $isFirstInStage = $taskIndex === 0;
                                $isLastInStage = $taskIndex === count($stage['tasks']) - 1;
                            @endphp
                            <li id="tpl-task-{{ $task->id }}"
                                class="cabinet-sc-task cabinet-sc-task--tpl {{ $task->is_important ? 'is-important-card' : '' }} {{ $task->repeat_rule ? 'is-repeat-card' : '' }}"
                                data-sc-tpl-task
                                data-search="{{ e($taskSearch) }}"
                                data-important="{{ $task->is_important ? '1' : '0' }}"
                                data-repeat="{{ $task->repeat_rule ? '1' : '0' }}">
                                @if($readOnly)
                                    <div class="cabinet-sc-task__main">
                                        <span class="cabinet-sc-task__title {{ $task->is_important ? 'is-important' : '' }}">{{ $task->title }}</span>
                                        <span class="cabinet-sc-role cabinet-sc-role--{{ $task->role }}">{{ $roleLabel }}</span>
                                    </div>
                                    @if($task->help)
                                        <p class="cabinet-sc-task__help cabinet-sc-task__help--tpl">{{ $task->help }}</p>
                                    @endif
                                    @if(count($taskLinks) > 0)
                                        <div class="cabinet-sc-task__links cabinet-sc-task__links--tpl">
                                            @foreach($taskLinks as $link)
                                                <a href="{{ $link['path'] ?? '#' }}" class="cabinet-sc-task__link">{{ $link['label'] ?? $link['path'] }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <form method="post" action="{{ route('pages.seo-checklist.templates.task.update', ['templateId' => $template->id, 'taskId' => $task->id]) }}" class="cabinet-sc-tpl-task">
                                        @csrf
                                        <label class="cabinet-sc-tpl-task__field">
                                            <span class="cabinet-sc-tpl-task__label">{{ __('Task') }}</span>
                                            <input type="text" name="title" class="form-control form-control-sm" value="{{ $task->title }}" required>
                                        </label>
                                        <label class="cabinet-sc-tpl-task__field">
                                            <span class="cabinet-sc-tpl-task__label">{{ __('Hint / help') }}</span>
                                            <textarea name="help" class="form-control form-control-sm" rows="2" placeholder="{{ __('What to do and how to check') }}">{{ $task->help }}</textarea>
                                        </label>
                                        @if(count($taskLinks) > 0)
                                            <div class="cabinet-sc-task__links cabinet-sc-task__links--tpl">
                                                @foreach($taskLinks as $link)
                                                    <a href="{{ $link['path'] ?? '#' }}" class="cabinet-sc-task__link">{{ $link['label'] ?? $link['path'] }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                        <div class="cabinet-sc-tpl-task__footer">
                                            <div class="cabinet-sc-tpl-task__row">
                                                <select name="role" class="form-select form-select-sm" aria-label="{{ __('Role') }}">
                                                    @foreach($roleLabels as $rk => $rl)
                                                        <option value="{{ $rk }}" @if($task->role === $rk) selected @endif>{{ $rl }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="repeat_rule" class="form-select form-select-sm" aria-label="{{ __('Recurring') }}">
                                                    @include('pages.partials.seo-checklist-repeat-options', ['selected' => $task->repeat_rule])
                                                </select>
                                                <label class="cabinet-sc-tpl-task__check small mb-0" title="{{ __('Due days from project start') }}">
                                                    <span class="text-secondary">{{ __('Due day') }}</span>
                                                    <input type="number"
                                                           name="due_days_from_start"
                                                           class="form-control form-control-sm cabinet-sc-due-input"
                                                           min="1"
                                                           max="365"
                                                           placeholder="—"
                                                           value="{{ $task->due_days_from_start }}">
                                                </label>
                                                <label class="cabinet-sc-tpl-task__check small mb-0">
                                                    <input type="checkbox" name="is_important" value="1" @if($task->is_important) checked @endif>
                                                    {{ __('Important') }}
                                                </label>
                                            </div>
                                            <div class="cabinet-sc-tpl-task__actions">
                                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="cabinet-sc-tpl-subtasks" data-sc-tpl-subtasks>
                                        <div class="cabinet-sc-tpl-subtasks__head">{{ __('Subtasks') }}</div>
                                        <ul class="cabinet-sc-tpl-subtasks__list" data-sc-tpl-subtasks-list>
                                            @foreach($task->children as $child)
                                                <li data-sc-tpl-subtask data-id="{{ $child->id }}">
                                                    <span class="cabinet-sc-tpl-subtasks__dot" aria-hidden="true"></span>
                                                    <span class="cabinet-sc-tpl-subtasks__title">{{ $child->title }}</span>
                                                    <button type="button"
                                                            class="cabinet-sc-tpl-subtasks__remove"
                                                            data-sc-tpl-subtask-delete
                                                            data-url="{{ route('pages.seo-checklist.templates.task.delete', ['templateId' => $template->id, 'taskId' => $child->id]) }}"
                                                            title="{{ __('Delete') }}"
                                                            aria-label="{{ __('Delete') }}">×</button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="cabinet-sc-tpl-subtasks__form">
                                            <input type="text"
                                                   class="cabinet-sc-tpl-subtasks__input"
                                                   data-sc-tpl-subtask-title
                                                   placeholder="{{ __('New subtask') }}…">
                                            <button type="button"
                                                    class="cabinet-sc-tpl-subtasks__add"
                                                    data-sc-tpl-subtask-add
                                                    data-url="{{ route('pages.seo-checklist.templates.task.subtasks', ['templateId' => $template->id, 'taskId' => $task->id]) }}">
                                                + {{ __('Add') }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="cabinet-sc-tpl-task__toolbar">
                                        <div class="cabinet-sc-tpl-task__order" title="{{ __('Order within stage') }}">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-sc-tpl-move="up"
                                                    @if($isFirstInStage) disabled @endif
                                                    title="{{ __('Move up') }}"
                                                    aria-label="{{ __('Move up') }}">↑</button>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-sc-tpl-move="down"
                                                    @if($isLastInStage) disabled @endif
                                                    title="{{ __('Move down') }}"
                                                    aria-label="{{ __('Move down') }}">↓</button>
                                        </div>
                                        <form method="post" action="{{ route('pages.seo-checklist.templates.task.delete', ['templateId' => $template->id, 'taskId' => $task->id]) }}"
                                              class="cabinet-sc-tpl-task__delete"
                                              onsubmit='return confirm(@json(__("Delete this task?")));'>
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete task') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                        @if(!$readOnly)
                            <li class="cabinet-sc-task cabinet-sc-task--tpl cabinet-sc-task--add" data-sc-tpl-add>
                                <form method="post" action="{{ route('pages.seo-checklist.templates.task.store', ['templateId' => $template->id]) }}" class="cabinet-sc-tpl-task">
                                    @csrf
                                    <input type="hidden" name="stage_key" value="{{ $stage['key'] }}">
                                    <div class="cabinet-sc-tpl-task__field">
                                        <span class="cabinet-sc-tpl-task__label">{{ __('New task') }}</span>
                                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="{{ __('New task') }}…">
                                    </div>
                                    <div class="cabinet-sc-tpl-task__footer">
                                        <div class="cabinet-sc-tpl-task__row">
                                            <select name="role" class="form-select form-select-sm">
                                                @foreach($roleLabels as $rk => $rl)
                                                    <option value="{{ $rk }}" @if($rk === 'owner') selected @endif>{{ $rl }}</option>
                                                @endforeach
                                            </select>
                                            <select name="repeat_rule" class="form-select form-select-sm">
                                                @include('pages.partials.seo-checklist-repeat-options', ['selected' => ''])
                                            </select>
                                            <label class="cabinet-sc-tpl-task__check small mb-0">
                                                <input type="checkbox" name="is_important" value="1">
                                                {{ __('Important') }}
                                            </label>
                                        </div>
                                        <div class="cabinet-sc-tpl-task__actions">
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Add task') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </li>
                        @endif
                    </ul>
                </details>
            @endforeach

            @if(!$readOnly)
                <form method="post"
                      action="{{ route('pages.seo-checklist.templates.stage.store', ['templateId' => $template->id]) }}"
                      class="cabinet-sc-add-stage">
                    @csrf
                    <div class="cabinet-sc-add-stage__title">{{ __('Add stage') }}</div>
                    <div class="cabinet-sc-add-stage__row">
                        <input type="text" name="title" class="form-control" required placeholder="{{ __('Stage title') }}…">
                        <input type="text" name="lead" class="form-control" placeholder="{{ __('Stage lead optional') }}">
                        <button type="submit" class="btn btn-primary">{{ __('Add stage') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
    @endslot
@endcomponent
