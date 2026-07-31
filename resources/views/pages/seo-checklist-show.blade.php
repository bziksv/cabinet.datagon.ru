@component('component.card', [
    'title' => $project->domain,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $pct = $project->progress_total > 0
            ? (int) round(100 * $project->progress_done / $project->progress_total)
            : 0;
    @endphp

    <div class="cabinet-sc-page"
         id="cabinetSeoChecklist"
         data-project-id="{{ $project->id }}"
         data-status-url-template="{{ url('/seo-checklist/'.$project->id.'/items/__ID__/status') }}"
         data-note-url-template="{{ url('/seo-checklist/'.$project->id.'/items/__ID__/notes') }}"
         data-subtask-url-template="{{ url('/seo-checklist/'.$project->id.'/items/__ID__/subtasks') }}"
         data-csrf="{{ csrf_token() }}"
         data-i18n-comment-required="{{ e(__('Comment required for this status')) }}">

        <div class="cabinet-sc-show-head">
            <div>
                <a href="{{ route('pages.seo-checklist') }}" class="cabinet-sc-back small">← {{ __('All checklists') }}</a>
                <h1 class="cabinet-sc-hero__title mb-1">{{ $project->title ?: $project->domain }}</h1>
                <p class="text-secondary small mb-0">
                    <span data-sc-progress-label>{{ $project->progress_done }}/{{ $project->progress_total }}</span>
                    · <span data-sc-progress-pct>{{ $pct }}%</span>
                </p>
            </div>
            <form method="post" action="{{ route('pages.seo-checklist.archive', ['id' => $project->id]) }}"
                  onsubmit="return confirm(@json(__('Archive this SEO checklist?')));">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('To archive') }}</button>
            </form>
        </div>

        <div class="cabinet-sc-progress mb-3" aria-hidden="true">
            <span data-sc-progress-bar style="width: {{ $pct }}%"></span>
        </div>

        <div class="cabinet-sc-filters mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary active" data-sc-filter="all">{{ __('All') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="open">{{ __('Open tasks') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="important">{{ __('Important') }}</button>
        </div>

        <div class="cabinet-sc-stages">
            @foreach($stages as $stage)
                @php
                    $stagePct = $stage['total'] > 0 ? (int) round(100 * $stage['done'] / $stage['total']) : 0;
                @endphp
                <details class="cabinet-sc-stage" data-sc-stage open>
                    <summary class="cabinet-sc-stage__summary">
                        <span class="cabinet-sc-stage__title">{{ $stage['title'] }}</span>
                        <span class="cabinet-sc-stage__meta">{{ $stage['done'] }}/{{ $stage['total'] }} · {{ $stagePct }}%</span>
                    </summary>
                    <ul class="cabinet-sc-tasks">
                        @foreach($stage['items'] as $item)
                            <li class="cabinet-sc-task"
                                data-sc-item
                                data-id="{{ $item->id }}"
                                data-status="{{ $item->status }}"
                                data-important="{{ $item->is_important ? '1' : '0' }}"
                                data-allows-subtasks="{{ $item->allows_subtasks ? '1' : '0' }}">
                                <div class="cabinet-sc-task__main">
                                    <label class="cabinet-sc-check">
                                        <input type="checkbox"
                                               data-sc-done
                                               {{ in_array($item->status, ['done', 'skip'], true) ? 'checked' : '' }}>
                                        <span class="cabinet-sc-task__title {{ $item->is_important ? 'is-important' : '' }}">
                                            {{ $item->title }}
                                        </span>
                                    </label>
                                    <div class="cabinet-sc-task__actions">
                                        <select class="form-select form-select-sm" data-sc-status aria-label="{{ __('Status') }}">
                                            @foreach($statusLabels as $value => $label)
                                                <option value="{{ $value }}" @if($item->status === $value) selected @endif>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-toggle-notes title="{{ __('Notes') }}">
                                            <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                @if($item->help)
                                    <p class="cabinet-sc-task__help">{{ $item->help }}</p>
                                @endif
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
                                <div class="cabinet-sc-task__notes d-none" data-sc-notes>
                                    <ul class="cabinet-sc-notes-list" data-sc-notes-list>
                                        @foreach($item->notes as $note)
                                            <li>
                                                <span class="text-secondary small">{{ $note->created_at->format('d.m.Y H:i') }}</span>
                                                {{ $note->body }}
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="cabinet-sc-notes-form">
                                        <textarea class="form-control form-control-sm" rows="2" data-sc-note-body placeholder="{{ __('Add a note') }}…"></textarea>
                                        <button type="button" class="btn btn-sm btn-primary" data-sc-note-save>{{ __('Save') }}</button>
                                    </div>
                                    @if($item->allows_subtasks)
                                        <div class="cabinet-sc-subtask-form mt-2">
                                            <input type="text" class="form-control form-control-sm" data-sc-subtask-title placeholder="{{ __('New subtask') }}…">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-sc-subtask-add>{{ __('Add subtask') }}</button>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist.js')) ?: time() }}"></script>
    @endslot
@endcomponent
