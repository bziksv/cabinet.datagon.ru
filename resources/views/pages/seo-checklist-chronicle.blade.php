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
                <form method="post" action="{{ route('pages.seo-checklist.chronicle.read') }}" class="cabinet-sc-feed__mark-all">
                    @csrf
                    <input type="hidden" name="all" value="1">
                    <input type="hidden" name="view" value="{{ $filterPreset }}">
                    @foreach($filterProjectIds as $pid)
                        <input type="hidden" name="project_ids[]" value="{{ $pid }}">
                    @endforeach
                    @foreach($filterAuthorIds as $aid)
                        <input type="hidden" name="author_ids[]" value="{{ $aid }}">
                    @endforeach
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-all" aria-hidden="true"></i>
                        {{ __('Mark all notes read') }} ({{ $unreadNotesCount }})
                    </button>
                </form>
            @endif
        </div>

        <div class="cabinet-sc-chronicle-presets mb-2" role="tablist" aria-label="{{ __('Chronicle presets') }}">
            <a href="{{ route('pages.seo-checklist.chronicle', $chronicleQuery(['view' => 'unread'])) }}"
               class="cabinet-sc-chronicle-presets__item @if($filterPreset === 'unread') is-active @endif"
               role="tab"
               @if($filterPreset === 'unread') aria-current="page" @endif>
                {{ __('Unread notes') }}
                @if(($unreadNotesCount ?? 0) > 0)
                    <span class="cabinet-sc-chronicle-presets__count">{{ $unreadNotesCount }}</span>
                @endif
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

        @if($unreadNotes->isNotEmpty())
            <section class="cabinet-sc-feed cabinet-sc-feed--unread mb-3">
                <header class="cabinet-sc-feed__head">
                    <div class="cabinet-sc-feed__head-main">
                        <span class="cabinet-sc-feed__icon" aria-hidden="true"><i class="bi bi-chat-dots-fill"></i></span>
                        <h3>{{ __('Unread notes') }}</h3>
                        <span class="cabinet-sc-feed__count">{{ $unreadNotes->count() }}</span>
                    </div>
                    <form method="post" action="{{ route('pages.seo-checklist.chronicle.read') }}">
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
                <ol class="cabinet-sc-feed__list">
                    @foreach($unreadNotes as $note)
                        @php
                            $item = $note->item;
                            $project = $item ? $item->project : null;
                            $author = $note->authorLabel();
                            $url = ($project && $item)
                                ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $item->id
                                : route('pages.seo-checklist');
                        @endphp
                        <li class="cabinet-sc-feed__item is-unread">
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
                                    <form method="post" action="{{ route('pages.seo-checklist.chronicle.read') }}">
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

        @if($filterPreset === 'unread' && $unreadNotes->isEmpty())
            <div class="cabinet-sc-empty cabinet-sc-empty--chronicle">
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
        @endif

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
                                        $who = $log->user ? ($log->user->name ?: $log->user->email) : '—';
                                        $url = ($project && $item)
                                            ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $item->id
                                            : ($project ? route('pages.seo-checklist.show', ['id' => $project->id]) : route('pages.seo-checklist'));
                                        $taskTitle = $meta['title'] ?? ($item->title ?? null);
                                        $isNote = $log->type === 'note';
                                        $isStatus = $log->type === 'status_change';
                                    @endphp
                                    <li class="cabinet-sc-feed__item {{ $isNote ? 'is-note' : '' }} {{ $isStatus ? 'is-status' : '' }}">
                                        <div class="cabinet-sc-feed__rail" aria-hidden="true">
                                            {!! $renderAvatar($log->user, $who, $isNote ? 'is-note' : 'is-status') !!}
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
                                                @elseif($isStatus)
                                                    <span class="cabinet-sc-feed__kind cabinet-sc-feed__kind--status">{{ __('Status') }}</span>
                                                @endif
                                            </div>

                                            @if($taskTitle)
                                                <a class="cabinet-sc-feed__task" href="{{ $url }}">{{ $taskTitle }}</a>
                                            @endif

                                            @if($isStatus)
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
