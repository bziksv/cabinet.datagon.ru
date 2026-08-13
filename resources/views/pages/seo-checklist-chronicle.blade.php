@component('component.card', [
    'title' => \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id()),
    'documentTitle' => cabinet_sc_document_title(__('Chronicle')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $filterProjectIds = array_map('intval', $filterProjectIds ?? []);
        $filterAuthorIds = array_map('intval', $filterAuthorIds ?? []);
        $filterPreset = (string) ($filterPreset ?? 'unread');
        if (!in_array($filterPreset, ['unread', 'notes', 'all'], true)) {
            $filterPreset = 'unread';
        }
        $statusLabels = $statusLabels ?? [];

        $chronicleQuery = function (array $extra = []) use ($filterProjectIds, $filterAuthorIds, $filterPreset) {
            $q = array_merge(['view' => $filterPreset], $extra);
            if ($filterProjectIds !== []) {
                $q['project_ids'] = $filterProjectIds;
            }
            if ($filterAuthorIds !== []) {
                $q['author_ids'] = $filterAuthorIds;
            }

            return $q;
        };

        $statusPill = function (?string $key) use ($statusLabels) {
            $key = (string) ($key ?? '');
            $label = $statusLabels[$key] ?? ($key !== '' ? $key : '—');
            $safe = preg_replace('/[^a-z0-9_-]/i', '', $key) ?: 'unknown';

            return '<span class="cabinet-sc-pill cabinet-sc-pill--'.$safe.'">'.e($label).'</span>';
        };

        $initials = function (?string $name): string {
            $name = trim((string) $name);
            if ($name === '' || $name === '—') {
                return '?';
            }
            $parts = preg_split('/\s+/u', $name) ?: [];
            $letters = '';
            foreach (array_slice($parts, 0, 2) as $part) {
                $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            }

            return $letters !== '' ? $letters : '?';
        };

        $renderAvatar = function ($user, ?string $name = null, string $mod = '') use ($initials) {
            $classes = trim('cabinet-sc-feed__avatar ' . $mod);
            $label = $name ?: ($user ? ($user->name ?: $user->email) : '—');
            if ($user && !empty($user->image)) {
                return '<span class="'.e($classes).' has-photo">'
                    .'<img src="'.e($user->image).'" alt="'.e($label).'" loading="lazy" decoding="async">'
                    .'</span>';
            }

            return '<span class="'.e($classes).'">'.e($initials($label)).'</span>';
        };

        $dayLabel = function ($dt): string {
            if (!$dt) {
                return '';
            }
            $today = now()->startOfDay();
            $day = $dt->copy()->startOfDay();
            if ($day->equalTo($today)) {
                return __('Today');
            }
            if ($day->equalTo($today->copy()->subDay())) {
                return __('Yesterday');
            }

            return $dt->format('d.m.Y');
        };
    @endphp

    <div class="cabinet-sc-page"
         data-sc-hub="chronicle"
         data-csrf="{{ csrf_token() }}"
         data-mark-read-url="{{ route('pages.seo-checklist.chronicle.read') }}"
         data-mark-unread-url="{{ route('pages.seo-checklist.chronicle.unread') }}"
         data-i18n-marked="{{ e(__('Notes marked as read')) }}"
         data-i18n-unmarked="{{ e(__('Notes marked as unread')) }}"
         data-i18n-mark-all="{{ e(__('Mark all notes read')) }}"
         data-i18n-mark-read="{{ e(__('Mark note read')) }}"
         data-i18n-mark-unread="{{ e(__('Mark note unread')) }}"
         data-auth-user-id="{{ (int) ($authUserId ?? auth()->id()) }}"
         data-i18n-no-unread="{{ e(__('No unread notes')) }}"
         data-i18n-unread-empty-hint="{{ e(__('Chronicle unread empty hint')) }}"
         data-i18n-preset-notes="{{ e(__('Chronicle preset notes')) }}"
         data-i18n-preset-all="{{ e(__('Chronicle preset all')) }}"
         data-notes-url="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'notes'])) }}"
         data-all-url="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'all'])) }}"
         data-filter-preset="{{ $filterPreset }}">
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

        <div data-sc-flash-slot>
            @if(session('success'))
                <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
            @endif
        </div>

        <div class="cabinet-sc-plan-head">
            <div>
                <h2 class="cabinet-sc-plan-head__title">{{ __('Chronicle') }}</h2>
                <p class="cabinet-sc-plan-head__hint">{{ __('Chronicle hint') }}</p>
            </div>
            <div class="cabinet-sc-feed__mark-all" data-sc-mark-all-wrap @if(($unreadNotesCount ?? 0) < 1) hidden @endif>
                <form method="post" action="{{ route('pages.seo-checklist.chronicle.read') }}" data-sc-mark-read data-sc-mark-all="1">
                    @csrf
                    <input type="hidden" name="all" value="1">
                    <input type="hidden" name="view" value="{{ $filterPreset }}">
                    @foreach($filterProjectIds as $pid)
                        <input type="hidden" name="project_ids[]" value="{{ $pid }}">
                    @endforeach
                    @foreach($filterAuthorIds as $aid)
                        <input type="hidden" name="author_ids[]" value="{{ $aid }}">
                    @endforeach
                    <button type="submit" class="btn btn-primary btn-sm" data-sc-mark-all-btn>
                        <i class="bi bi-check2-all" aria-hidden="true"></i>
                        <span data-sc-mark-all-label>{{ __('Mark all notes read') }} ({{ $unreadNotesCount }})</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="cabinet-sc-chronicle-presets mb-2" role="tablist" aria-label="{{ __('Chronicle presets') }}">
            <a href="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'unread'])) }}"
               class="cabinet-sc-chronicle-presets__item @if($filterPreset === 'unread') is-active @endif"
               role="tab"
               @if($filterPreset === 'unread') aria-current="page" @endif>
                {{ __('Unread notes') }}
                <span class="cabinet-sc-chronicle-presets__count"
                      data-sc-unread-preset-count
                      @if(($unreadNotesCount ?? 0) < 1) hidden @endif>{{ $unreadNotesCount }}</span>
            </a>
            <a href="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'notes'])) }}"
               class="cabinet-sc-chronicle-presets__item @if($filterPreset === 'notes') is-active @endif"
               role="tab"
               @if($filterPreset === 'notes') aria-current="page" @endif>
                {{ __('Chronicle preset notes') }}
            </a>
            <a href="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'all'])) }}"
               class="cabinet-sc-chronicle-presets__item @if($filterPreset === 'all') is-active @endif"
               role="tab"
               @if($filterPreset === 'all') aria-current="page" @endif>
                {{ __('Chronicle preset all') }}
            </a>
        </div>

        <form method="get" action="{{ route('pages.seo-checklist.chronicle') }}" class="cabinet-sc-chronicle-filters mb-3">
            <input type="hidden" name="view" value="{{ $filterPreset }}">
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
            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button>
            @if($filterProjectIds || $filterAuthorIds || $filterPreset !== 'unread')
                <a href="{{ route('pages.seo-checklist.chronicle', ['view' => 'unread']) }}" class="btn btn-sm btn-link">{{ __('Reset') }}</a>
            @endif
        </form>

        @php
            $unreadNotes = $chronicle['unread_notes'] ?? collect();
            $logs = $chronicle['items'] ?? collect();
        @endphp

        @if($unreadNotes->isNotEmpty() || $filterPreset === 'unread')
            <section class="cabinet-sc-feed cabinet-sc-feed--unread mb-3"
                     data-sc-unread-section
                     @if($unreadNotes->isEmpty()) hidden @endif>
                <header class="cabinet-sc-feed__head">
                    <div class="cabinet-sc-feed__head-main">
                        <span class="cabinet-sc-feed__icon" aria-hidden="true"><i class="bi bi-chat-dots-fill"></i></span>
                        <h3>{{ __('Unread notes') }}</h3>
                        <span class="cabinet-sc-feed__count" data-sc-unread-section-count>{{ $unreadNotes->count() }}</span>
                    </div>
                    <form method="post" action="{{ route('pages.seo-checklist.chronicle.read') }}" data-sc-mark-read data-sc-mark-all="1">
                        @csrf
                        <input type="hidden" name="all" value="1">
                        <input type="hidden" name="view" value="{{ $filterPreset }}">
                        @foreach($filterProjectIds as $pid)
                            <input type="hidden" name="project_ids[]" value="{{ $pid }}">
                        @endforeach
                        @foreach($filterAuthorIds as $aid)
                            <input type="hidden" name="author_ids[]" value="{{ $aid }}">
                        @endforeach
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-check2-all" aria-hidden="true"></i>
                            {{ __('Mark all notes read') }}
                        </button>
                    </form>
                </header>
                <ol class="cabinet-sc-feed__list" data-sc-unread-list>
                    @foreach($unreadNotes as $note)
                        @php
                            $item = $note->item;
                            $project = $item ? $item->project : null;
                            $author = $note->authorLabel();
                            $url = ($project && $item)
                                ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $item->id
                                : route('pages.seo-checklist');
                        @endphp
                        <li class="cabinet-sc-feed__item is-unread" data-sc-unread-item data-note-id="{{ $note->id }}">
                            <div class="cabinet-sc-feed__rail" aria-hidden="true">
                                {!! $renderAvatar($note->user, $author) !!}
                            </div>
                            <div class="cabinet-sc-feed__card">
                                <div class="cabinet-sc-feed__meta">
                                    <strong class="cabinet-sc-feed__who">{{ $author }}</strong>
                                    <time datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->format('d.m.Y H:i') }}</time>
                                    @if($project)
                                        <a class="cabinet-sc-feed__project" href="{{ $url }}">{{ $project->domain }}</a>
                                    @endif
                                    <span class="cabinet-sc-feed__kind cabinet-sc-feed__kind--note">{{ __('Note') }}</span>
                                </div>
                                @if($item)
                                    <a class="cabinet-sc-feed__task" href="{{ $url }}">{{ $item->title }}</a>
                                @endif
                                <div class="cabinet-sc-feed__note">{{ $note->body }}</div>
                                <div class="cabinet-sc-feed__actions">
                                    <form method="post" action="{{ route('pages.seo-checklist.chronicle.read') }}" data-sc-mark-read data-note-id="{{ $note->id }}">
                                        @csrf
                                        <input type="hidden" name="note_ids[]" value="{{ $note->id }}">
                                        <input type="hidden" name="view" value="{{ $filterPreset }}">
                                        @foreach($filterProjectIds as $pid)
                                            <input type="hidden" name="project_ids[]" value="{{ $pid }}">
                                        @endforeach
                                        @foreach($filterAuthorIds as $aid)
                                            <input type="hidden" name="author_ids[]" value="{{ $aid }}">
                                        @endforeach
                                        <button type="submit" class="btn btn-sm btn-primary cabinet-sc-feed__ack">
                                            <i class="bi bi-check2" aria-hidden="true"></i>
                                            {{ __('Mark note read') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <div class="cabinet-sc-empty cabinet-sc-empty--chronicle"
             data-sc-unread-empty
             @if(!($filterPreset === 'unread' && $unreadNotes->isEmpty())) hidden @endif>
            <p class="fw-semibold mb-1">{{ __('No unread notes') }}</p>
            <p class="small text-secondary mb-3">{{ __('Chronicle unread empty hint') }}</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'notes'])) }}" class="btn btn-sm btn-outline-secondary">
                    {{ __('Chronicle preset notes') }}
                </a>
                <a href="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'all'])) }}" class="btn btn-sm btn-link">
                    {{ __('Chronicle preset all') }}
                </a>
            </div>
        </div>

        @if($filterPreset !== 'unread')
            <section class="cabinet-sc-feed">
                <header class="cabinet-sc-feed__head">
                    <div class="cabinet-sc-feed__head-main">
                        <span class="cabinet-sc-feed__icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
                        <h3>{{ $filterPreset === 'notes' ? __('Chronicle preset notes') : __('Activity log') }}</h3>
                    </div>
                    <span class="cabinet-sc-feed__count">{{ $logs->count() }}</span>
                </header>
                @if($logs->isEmpty())
                    <div class="cabinet-sc-empty py-4">
                        <p class="small text-secondary mb-0">{{ $filterPreset === 'notes' ? __('No notes in chronicle') : __('No activity yet') }}</p>
                    </div>
                @else
                    @php
                        $grouped = $logs->groupBy(function ($log) {
                            return optional($log->created_at)->format('Y-m-d') ?: 'unknown';
                        });
                    @endphp
                    @foreach($grouped as $dayKey => $dayLogs)
                        <div class="cabinet-sc-feed__day">
                            <div class="cabinet-sc-feed__day-label">
                                {{ $dayLabel(optional($dayLogs->first())->created_at) }}
                            </div>
                            <ol class="cabinet-sc-feed__list">
                                @foreach($dayLogs as $log)
                                    @php
                                        $meta = is_array($log->meta_json) ? $log->meta_json : [];
                                        $project = $log->project;
                                        $item = $log->item;
                                        $who = $log->user
                                            ? (trim(($log->user->name ?? '') . ' ' . ($log->user->last_name ?? '')) ?: ($log->user->name ?: $log->user->email))
                                            : '—';
                                        $anchorId = $item
                                            ? (int) ($item->parent_id ?: $item->id)
                                            : 0;
                                        if ($anchorId < 1 && !empty($meta['parent_id'])) {
                                            $anchorId = (int) $meta['parent_id'];
                                        } elseif ($anchorId < 1 && $item) {
                                            $anchorId = (int) $item->id;
                                        }
                                        $url = ($project && $anchorId > 0)
                                            ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $anchorId
                                            : ($project ? route('pages.seo-checklist.show', ['id' => $project->id]) : route('pages.seo-checklist'));
                                        $taskTitle = $meta['title'] ?? ($item->title ?? null);
                                        $isNote = $log->type === 'note';
                                        $isStatus = $log->type === 'status_change';
                                        $isCreated = $log->type === 'item_created';
                                        $statusTo = $meta['to'] ?? null;
                                        $isDoneEvent = $isStatus && in_array($statusTo, ['done', 'skip'], true);
                                        $createdByName = $meta['created_by_name']
                                            ?? ($item && $item->createdByUser
                                                ? (trim(($item->createdByUser->name ?? '') . ' ' . ($item->createdByUser->last_name ?? '')) ?: $item->createdByUser->email)
                                                : null);
                                        $createdAtLabel = $meta['created_at']
                                            ?? ($item && $item->created_at ? $item->created_at->format('d.m.Y H:i') : null);
                                        $doneByName = $meta['done_by_name']
                                            ?? ($item && $item->doneByUser
                                                ? (trim(($item->doneByUser->name ?? '') . ' ' . ($item->doneByUser->last_name ?? '')) ?: $item->doneByUser->email)
                                                : ($isDoneEvent ? $who : null));
                                        $doneAtLabel = $meta['done_at']
                                            ?? ($item && $item->done_at ? $item->done_at->format('d.m.Y H:i') : ($isDoneEvent ? $log->created_at->format('d.m.Y H:i') : null));
                                        $createdLabel = ($createdByName || $createdAtLabel)
                                            ? __('Created by :name on :date', [
                                                'name' => $createdByName ?: '—',
                                                'date' => $createdAtLabel ?: '—',
                                            ])
                                            : null;
                                        $doneLabel = ($isDoneEvent || ($doneAtLabel && $doneByName))
                                            ? __('Completed by :name on :date', [
                                                'name' => $doneByName ?: '—',
                                                'date' => $doneAtLabel ?: '—',
                                            ])
                                            : null;
                                        if ($isCreated && !$createdLabel) {
                                            $createdLabel = __('Created by :name on :date', [
                                                'name' => $who,
                                                'date' => $log->created_at->format('d.m.Y H:i'),
                                            ]);
                                        }
                                        $noteId = $isNote ? (int) ($meta['note_id'] ?? 0) : 0;
                                        $isOwnNote = $isNote && (int) $log->user_id === (int) ($authUserId ?? 0);
                                        $noteIsRead = $noteId > 0 && !empty(($readNoteIds ?? [])[$noteId]);
                                        $noteIsUnread = $isNote && !$isOwnNote && $noteId > 0 && !$noteIsRead;
                                        $kindClass = $isNote ? 'is-note' : (($isDoneEvent || $isCreated) ? 'is-done' : 'is-status');
                                    @endphp
                                    <li class="cabinet-sc-feed__item {{ $isNote ? 'is-note' : '' }} {{ $isStatus ? 'is-status' : '' }} {{ $isCreated ? 'is-created' : '' }} {{ $isDoneEvent ? 'is-done-event' : '' }} {{ $noteIsUnread ? 'is-unread' : '' }}"
                                        @if($noteId > 0) data-sc-feed-note data-note-id="{{ $noteId }}" data-note-read="{{ $noteIsRead ? '1' : '0' }}" @endif>
                                        <div class="cabinet-sc-feed__rail" aria-hidden="true">
                                            {!! $renderAvatar($log->user, $who, $kindClass) !!}
                                        </div>
                                        <div class="cabinet-sc-feed__card">
                                            <div class="cabinet-sc-feed__meta">
                                                <strong class="cabinet-sc-feed__who">{{ $who }}</strong>
                                                <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('H:i') }}</time>
                                                @if($project)
                                                    <a class="cabinet-sc-feed__project" href="{{ $url }}">{{ $project->domain }}</a>
                                                @endif
                                                @if($isNote)
                                                    <span class="cabinet-sc-feed__kind cabinet-sc-feed__kind--note">{{ __('Note') }}</span>
                                                @elseif($isCreated)
                                                    <span class="cabinet-sc-feed__kind cabinet-sc-feed__kind--created">{{ __('Chronicle kind created') }}</span>
                                                @elseif($isDoneEvent)
                                                    <span class="cabinet-sc-feed__kind cabinet-sc-feed__kind--done">{{ __('Chronicle kind completed') }}</span>
                                                @elseif($isStatus)
                                                    <span class="cabinet-sc-feed__kind cabinet-sc-feed__kind--status">{{ __('Status') }}</span>
                                                @endif
                                            </div>

                                            @if($taskTitle)
                                                <a class="cabinet-sc-feed__task" href="{{ $url }}">{{ $taskTitle }}</a>
                                            @endif

                                            @if($isCreated || $isDoneEvent)
                                                <div class="cabinet-sc-feed__audit">
                                                    @if($createdLabel)
                                                        <span>{{ $createdLabel }}</span>
                                                    @endif
                                                    @if($isDoneEvent && $doneLabel)
                                                        <span>{{ $doneLabel }}</span>
                                                    @endif
                                                </div>
                                            @elseif($isStatus)
                                                <div class="cabinet-sc-feed__status">
                                                    {!! $statusPill($meta['from'] ?? null) !!}
                                                    <span class="cabinet-sc-feed__arrow" aria-hidden="true">→</span>
                                                    {!! $statusPill($meta['to'] ?? null) !!}
                                                </div>
                                            @elseif($isNote)
                                                <div class="cabinet-sc-feed__note">{{ \Illuminate\Support\Str::limit($meta['body'] ?? '', 220) }}</div>
                                            @else
                                                <div class="cabinet-sc-feed__note text-secondary">{{ $log->type }}</div>
                                            @endif

                                            @if($isNote && !$isOwnNote && $noteId > 0)
                                                <div class="cabinet-sc-feed__actions" data-sc-note-actions>
                                                    <form method="post"
                                                          action="{{ route('pages.seo-checklist.chronicle.read') }}"
                                                          data-sc-mark-read
                                                          data-note-id="{{ $noteId }}"
                                                          @if($noteIsRead) hidden @endif>
                                                        @csrf
                                                        <input type="hidden" name="note_ids[]" value="{{ $noteId }}">
                                                        <input type="hidden" name="view" value="{{ $filterPreset }}">
                                                        <button type="submit" class="btn btn-sm btn-primary cabinet-sc-feed__ack">
                                                            <i class="bi bi-check2" aria-hidden="true"></i>
                                                            {{ __('Mark note read') }}
                                                        </button>
                                                    </form>
                                                    <form method="post"
                                                          action="{{ route('pages.seo-checklist.chronicle.unread') }}"
                                                          data-sc-mark-unread
                                                          data-note-id="{{ $noteId }}"
                                                          @if(!$noteIsRead) hidden @endif>
                                                        @csrf
                                                        <input type="hidden" name="note_ids[]" value="{{ $noteId }}">
                                                        <input type="hidden" name="view" value="{{ $filterPreset }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary cabinet-sc-feed__ack">
                                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                                            {{ __('Mark note unread') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                @endif
            </section>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
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
