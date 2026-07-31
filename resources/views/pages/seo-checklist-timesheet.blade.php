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
    @endphp

    <div class="cabinet-sc-page" data-sc-hub="timesheet">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'timesheet',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <div class="cabinet-sc-plan-head">
            <div>
                <h2 class="cabinet-sc-plan-head__title">{{ __('Time log') }}</h2>
                <p class="cabinet-sc-plan-head__hint">{{ __('Time log hint') }}</p>
            </div>
            <div class="cabinet-sc-plan-head__meta">
                <span class="cabinet-sc-timesheet-total">
                    {{ __('Total') }}:
                    <strong>{{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration((int) ($timesheet['total'] ?? 0)) }}</strong>
                </span>
            </div>
        </div>

        <form method="get" action="{{ route('pages.seo-checklist.timesheet') }}" class="cabinet-sc-chronicle-filters mb-3">
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $filterFrom }}" aria-label="{{ __('Date from') }}">
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $filterTo }}" aria-label="{{ __('Date to') }}">
            <select name="project_ids[]"
                    class="form-select form-select-sm cabinet-sc-multi"
                    multiple
                    data-placeholder="{{ __('All projects') }}"
                    aria-label="{{ __('Projects') }}">
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" @if(in_array((int) $p->id, $filterProjectIds, true)) selected @endif>{{ $p->domain }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button>
            @if($filterProjectIds || $filterFrom !== now()->subDays(30)->toDateString() || $filterTo !== now()->toDateString())
                <a href="{{ route('pages.seo-checklist.timesheet') }}" class="btn btn-sm btn-link">{{ __('Reset') }}</a>
            @endif
        </form>

        @php $days = $timesheet['days'] ?? []; @endphp
        @if(empty($days))
            <div class="cabinet-sc-empty">
                <i class="bi bi-clock-history display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No time logged yet') }}</p>
                <p class="small text-secondary mb-0">{{ __('No time logged hint') }}</p>
            </div>
        @else
            <div class="cabinet-sc-timesheet">
                @foreach($days as $day)
                    <section class="cabinet-sc-timesheet__day">
                        <header class="cabinet-sc-timesheet__day-head">
                            <h3>{{ $day['label'] }}</h3>
                            <span>{{ $day['formatted'] }}</span>
                        </header>
                        <ul class="cabinet-sc-timesheet__list">
                            @foreach($day['entries'] as $entry)
                                <li>
                                    @if(!empty($entry['project_id']) && !empty($entry['item_id']))
                                        <a href="{{ route('pages.seo-checklist.show', ['id' => $entry['project_id']]) }}#sc-item-{{ $entry['item_id'] }}"
                                           class="cabinet-sc-timesheet__domain">{{ $entry['domain'] }}</a>
                                    @else
                                        <span class="cabinet-sc-timesheet__domain">{{ $entry['domain'] }}</span>
                                    @endif
                                    <span class="cabinet-sc-timesheet__task">{{ $entry['title'] }}</span>
                                    <span class="cabinet-sc-timesheet__dur">{{ $entry['formatted'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
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
