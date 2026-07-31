@component('component.card', [
    'title' => \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id()),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $filterProjectIds = array_map('intval', $filterProjectIds ?? []);
        $filterAuthorIds = array_map('intval', $filterAuthorIds ?? []);
    @endphp

    <div class="cabinet-sc-page" data-sc-hub="chronicle">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'chronicle',
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

        <div class="cabinet-sc-plan-head">
            <div>
                <h2 class="cabinet-sc-plan-head__title">{{ __('Chronicle') }}</h2>
                <p class="cabinet-sc-plan-head__hint">{{ __('Chronicle hint') }}</p>
            </div>
            @if(($unreadNotesCount ?? 0) > 0)
                <form method="post" action="{{ route('pages.seo-checklist.chronicle.read') }}">
                    @csrf
                    <input type="hidden" name="all" value="1">
                    @foreach($filterProjectIds as $pid)
                        <input type="hidden" name="project_ids[]" value="{{ $pid }}">
                    @endforeach
                    @foreach($filterAuthorIds as $aid)
                        <input type="hidden" name="author_ids[]" value="{{ $aid }}">
                    @endforeach
                    @if(!empty($filterUnread))
                        <input type="hidden" name="unread" value="1">
                    @endif
                    <button type="submit" class="btn btn-primary btn-sm">
                        {{ __('Mark all notes read') }} ({{ $unreadNotesCount }})
                    </button>
                </form>
            @endif
        </div>

        <form method="get" action="{{ route('pages.seo-checklist.chronicle') }}" class="cabinet-sc-chronicle-filters mb-3">
            <select name="project_ids[]"
                    class="form-select form-select-sm cabinet-sc-multi"
                    multiple
                    data-placeholder="{{ __('All projects') }}"
                    aria-label="{{ __('Projects') }}">
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" @if(in_array((int) $p->id, $filterProjectIds, true)) selected @endif>{{ $p->domain }}</option>
                @endforeach
            </select>
            <select name="author_ids[]"
                    class="form-select form-select-sm cabinet-sc-multi"
                    multiple
                    data-placeholder="{{ __('All authors') }}"
                    aria-label="{{ __('All authors') }}">
                @foreach(($authors ?? collect()) as $author)
                    <option value="{{ $author->id }}" @if(in_array((int) $author->id, $filterAuthorIds, true)) selected @endif>
                        {{ $author->name ?: $author->email }}
                    </option>
                @endforeach
            </select>
            <label class="cabinet-sc-chronicle-filters__check">
                <input type="checkbox" name="unread" value="1" @if(!empty($filterUnread)) checked @endif>
                <span>{{ __('Unread notes only') }}</span>
            </label>
            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button>
            @if($filterProjectIds || $filterUnread || $filterAuthorIds)
                <a href="{{ route('pages.seo-checklist.chronicle') }}" class="btn btn-sm btn-link">{{ __('Reset') }}</a>
            @endif
        </form>

        @php
            $unreadNotes = $chronicle['unread_notes'] ?? collect();
            $logs = $chronicle['items'] ?? collect();
        @endphp

        @if($unreadNotes->isNotEmpty())
            <section class="cabinet-sc-chronicle-block mb-3">
                <header class="cabinet-sc-chronicle-block__head">
                    <h3>{{ __('Unread notes') }}</h3>
                    <span>{{ $unreadNotes->count() }}</span>
                </header>
                <ul class="cabinet-sc-chronicle-list">
                    @foreach($unreadNotes as $note)
                        @php
                            $item = $note->item;
                            $project = $item ? $item->project : null;
                            $url = ($project && $item)
                                ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $item->id
                                : route('pages.seo-checklist');
                        @endphp
                        <li class="cabinet-sc-chronicle-list__item is-unread">
                            <div class="cabinet-sc-chronicle-list__meta">
                                <strong>{{ $note->authorLabel() }}</strong>
                                <span>{{ $note->created_at->format('d.m.Y H:i') }}</span>
                                @if($project)
                                    <a href="{{ $url }}">{{ $project->domain }}</a>
                                @endif
                                @if($item)
                                    <span class="text-secondary">{{ \Illuminate\Support\Str::limit($item->title, 60) }}</span>
                                @endif
                            </div>
                            <div class="cabinet-sc-chronicle-list__body">{{ $note->body }}</div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(empty($filterUnread))
            <section class="cabinet-sc-chronicle-block">
                <header class="cabinet-sc-chronicle-block__head">
                    <h3>{{ __('Activity log') }}</h3>
                    <span>{{ $logs->count() }}</span>
                </header>
                @if($logs->isEmpty())
                    <div class="cabinet-sc-empty py-4">
                        <p class="small text-secondary mb-0">{{ __('No activity yet') }}</p>
                    </div>
                @else
                    <ul class="cabinet-sc-chronicle-list">
                        @foreach($logs as $log)
                            @php
                                $meta = is_array($log->meta_json) ? $log->meta_json : [];
                                $project = $log->project;
                                $item = $log->item;
                                $url = ($project && $item)
                                    ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $item->id
                                    : ($project ? route('pages.seo-checklist.show', ['id' => $project->id]) : route('pages.seo-checklist'));
                            @endphp
                            <li class="cabinet-sc-chronicle-list__item">
                                <div class="cabinet-sc-chronicle-list__meta">
                                    <strong>{{ $log->user ? ($log->user->name ?: $log->user->email) : '—' }}</strong>
                                    <span>{{ $log->created_at->format('d.m.Y H:i') }}</span>
                                    @if($project)
                                        <a href="{{ $url }}">{{ $project->domain }}</a>
                                    @endif
                                </div>
                                <div class="cabinet-sc-chronicle-list__body">
                                    @if($log->type === 'status_change')
                                        {{ $meta['title'] ?? ($item->title ?? '—') }}:
                                        {{ $statusLabels[$meta['from'] ?? ''] ?? ($meta['from'] ?? '—') }}
                                        →
                                        {{ $statusLabels[$meta['to'] ?? ''] ?? ($meta['to'] ?? '—') }}
                                    @elseif($log->type === 'note')
                                        {{ __('Note') }}: {{ \Illuminate\Support\Str::limit($meta['body'] ?? '', 160) }}
                                        @if(!empty($meta['title']))
                                            <span class="text-secondary">· {{ $meta['title'] }}</span>
                                        @endif
                                    @else
                                        {{ $log->type }}
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @elseif($unreadNotes->isEmpty())
            <div class="cabinet-sc-empty">
                <p class="fw-semibold mb-1">{{ __('No unread notes') }}</p>
            </div>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            (function () {
                if (!window.jQuery || !jQuery.fn.select2) return;
                jQuery('.cabinet-sc-multi').each(function () {
                    var $el = jQuery(this);
                    $el.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: $el.data('placeholder') || '',
                        allowClear: true,
                        closeOnSelect: false
                    });
                });
            })();
        </script>
    @endslot
@endcomponent
