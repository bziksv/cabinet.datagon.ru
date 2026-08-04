@php
    $project = $item->project;
    $isOver = method_exists($item, 'isOverdue') && $item->isOverdue();
    $roleLabel = $roleLabels[$item->role] ?? $item->role;
    $projectArchived = $project && $project->status === 'archived';
    $runningLog = $item->timeLogs->first();
    $timerRunning = (bool) $runningLog;
    $displaySeconds = (int) $item->time_spent_seconds + ($runningLog ? $runningLog->elapsedSeconds() : 0);
    $projectUrl = $project
        ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $item->id
        : route('pages.seo-checklist');
    $canApprove = !empty($canApprove);
    $canManage = !empty($canManage);
@endphp
<li class="cabinet-sc-plan__item @if($isOver) is-overdue @endif @if($item->status === 'doing') is-doing @endif @if($item->is_important) is-important @endif @if($timerRunning) is-timing @endif"
    data-sc-plan-item
    data-id="{{ $item->id }}"
    data-project-id="{{ $item->project_id }}"
    data-domain="{{ $project ? $project->domain : '' }}"
    data-status="{{ $item->status }}"
    data-important="{{ $item->is_important ? '1' : '0' }}"
    data-overdue="{{ $isOver ? '1' : '0' }}"
    data-due-soon="{{ (method_exists($item, 'isDueSoon') && $item->isDueSoon()) ? '1' : '0' }}"
    data-can-approve="{{ $canApprove ? '1' : '0' }}"
    data-time-spent="{{ (int) $item->time_spent_seconds }}"
    data-timer-running="{{ $timerRunning ? '1' : '0' }}"
    data-timer-started-at="{{ $timerRunning && $runningLog->started_at ? $runningLog->started_at->toIso8601String() : '' }}">
    <div class="cabinet-sc-plan__row">
        <div class="cabinet-sc-plan__main">
            @if($project)
                <a href="{{ $projectUrl }}" class="cabinet-sc-plan__domain" title="{{ __('Open in project') }}">{{ $project->domain }}</a>
            @endif
            <span class="cabinet-sc-plan__task">{{ $item->title }}</span>
        </div>
        <div class="cabinet-sc-plan__controls">
            @if($item->is_important)
                <span class="cabinet-sc-plan__flag"
                      data-tip="{{ __('Important task hint') }}"
                      title="{{ __('Important task hint') }}"
                      aria-label="{{ __('Important task hint') }}"
                      role="img"
                      tabindex="0">!</span>
            @endif
            @if(trim((string) ($item->help ?? '')) !== '')
                <span class="cabinet-sc-plan__help-tip"
                      data-tip="{{ e($item->help) }}"
                      title="{{ e($item->help) }}"
                      aria-label="{{ __('Hint / help') }}"
                      role="img"
                      tabindex="0">?</span>
            @endif
            <span class="cabinet-sc-role cabinet-sc-role--{{ $item->role }}">{{ $roleLabel }}</span>
            @if($item->due_at)
                <span class="cabinet-sc-plan__due @if($isOver) is-overdue @endif">
                    @if($isOver)
                        {{ __('Overdue') }} · {{ $item->due_at->format('d.m') }}
                    @else
                        {{ __('Due') }} {{ $item->due_at->format('d.m') }}
                    @endif
                </span>
            @endif
            <span class="cabinet-sc-time @if($timerRunning) is-running @endif"
                  data-sc-time
                  title="{{ __('Time spent') }}">
                {{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration($displaySeconds) }}
            </span>
            @if(!$projectArchived)
                <button type="button"
                        class="btn btn-sm @if($timerRunning) btn-danger @else btn-outline-success @endif"
                        data-sc-timer
                        title="{{ $timerRunning ? __('Stop timer') : __('Start timer') }}">
                    {{ $timerRunning ? __('Timer stop') : __('Timer start') }}
                </button>
                <select class="form-select form-select-sm cabinet-sc-plan__status"
                        data-sc-status
                        aria-label="{{ __('Status') }}">
                    @foreach($statusLabels as $value => $label)
                        @php
                            $hideClosed = in_array($value, ['done', 'skip'], true)
                                && !$canApprove
                                && $item->status !== $value;
                        @endphp
                        @if(!$hideClosed)
                            <option value="{{ $value }}"
                                    @if($item->status === $value) selected @endif>
                                {{ $label }}
                            </option>
                        @endif
                    @endforeach
                </select>
            @endif
            <a href="{{ $projectUrl }}"
               class="btn btn-sm cabinet-sc-plan__open"
               title="{{ __('Open in project') }}">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                {{ __('To project') }}
            </a>
        </div>
    </div>
    @php
        $children = $item->relationLoaded('children') ? $item->children : collect();
        $openChildren = $children->filter(function ($c) {
            return !in_array($c->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true);
        })->count();
    @endphp
    @if($children->isNotEmpty())
        <div class="cabinet-sc-plan__subs">
            <div class="cabinet-sc-plan__subs-head">
                {{ __('Subtasks') }}
                <span>{{ $openChildren }}/{{ $children->count() }}</span>
            </div>
            <ul class="cabinet-sc-plan__subs-list">
                @foreach($children as $child)
                    @php
                        $childDone = in_array($child->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true);
                    @endphp
                    <li class="cabinet-sc-plan__sub @if($childDone) is-done @endif"
                        data-sc-plan-sub
                        data-id="{{ $child->id }}"
                        data-project-id="{{ $item->project_id }}"
                        data-status="{{ $child->status }}">
                        <label class="cabinet-sc-plan__sub-check">
                            <input type="checkbox"
                                   data-sc-plan-sub-done
                                   @if($childDone) checked @endif
                                   @if($projectArchived) disabled @endif>
                            <span>{{ $child->title }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</li>
