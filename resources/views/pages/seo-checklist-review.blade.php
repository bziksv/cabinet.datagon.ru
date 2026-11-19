@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title(__('For review')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page cabinet-sc-plan-v2"
         id="cabinetSeoChecklistPlan"
         data-sc-hub="review"
         data-csrf="{{ csrf_token() }}"
         data-status-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/status') }}"
         data-note-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/notes') }}"
         data-subtask-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/subtasks') }}"
         data-mark-read-url="{{ route('pages.seo-checklist.chronicle.read') }}"
         data-mark-unread-url="{{ route('pages.seo-checklist.chronicle.unread') }}"
         data-timer-start-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/start') }}"
         data-timer-stop-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/stop') }}"
         data-timer-stop-active-url="{{ route('pages.seo-checklist.timer.stop-active') }}"
         data-can-approve="1"
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
         data-i18n-close-subs-first="{{ e(__('Close open checklist items first')) }}">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'review',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => true,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <div class="cabinet-sc-plan-head">
            <div>
                <h2 class="cabinet-sc-plan-head__title">{{ __('For review') }}</h2>
                <p class="cabinet-sc-plan-head__hint">{{ __('For review hint') }}</p>
            </div>
        </div>

        @if(($filterProjects ?? collect())->isNotEmpty())
            <form method="get"
                  action="{{ route('pages.seo-checklist.review') }}"
                  class="cabinet-sc-plan-filters cabinet-sc-plan-filters--sticky mb-3"
                  data-sc-review-filters>
                <div class="cabinet-sc-plan-filters__bar">
                    <label class="cabinet-sc-plan-filters__project">
                        <span class="visually-hidden">{{ __('Filter by project') }}</span>
                        <select class="form-select form-select-sm"
                                name="project"
                                data-sc-review-project
                                data-placeholder="{{ __('All projects') }}">
                            <option value=""></option>
                            @foreach($filterProjects as $fp)
                                @php
                                    $fpDomain = trim((string) ($fp->domain ?? ''));
                                    $fpTitle = trim((string) ($fp->title ?? ''));
                                    $fpTitleUseful = $fpTitle !== ''
                                        && strcasecmp($fpTitle, $fpDomain) !== 0
                                        && ($fpDomain === '' || mb_stripos($fpTitle, $fpDomain) === false);
                                    $fpCount = (int) ($fp->review_count ?? 0);
                                @endphp
                                <option value="{{ $fp->id }}" @if((int) ($filterProjectId ?? 0) === (int) $fp->id) selected @endif>
                                    {{ $fpDomain }}@if($fpTitleUseful) · {{ $fpTitle }}@endif
                                    ({{ number_format($fpCount, 0, '', ' ') }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </form>
        @endif

        @if($items->isEmpty())
            <div class="cabinet-sc-empty">
                <i class="bi bi-clipboard2-check display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No tasks for review') }}</p>
                <p class="small text-secondary mb-0">{{ __('No tasks for review hint') }}</p>
            </div>
        @else
            @php
                $reviewTotal = (int) ($filteredReviewCount ?? $items->total());
                $reviewFrom = (int) $items->firstItem();
                $reviewTo = (int) $items->lastItem();
            @endphp
            <div class="cabinet-sc-plan">
                <section class="cabinet-sc-plan__group cabinet-sc-plan__group--review" data-sc-plan-group="review">
                    <header class="cabinet-sc-plan__group-head">
                        <h3 class="cabinet-sc-plan__group-title">{{ __('Status review') }}</h3>
                        <span class="cabinet-sc-plan__group-count" data-sc-plan-count>
                            {{ number_format($reviewFrom, 0, '', ' ') }}–{{ number_format($reviewTo, 0, '', ' ') }}
                            /
                            {{ number_format($reviewTotal, 0, '', ' ') }}
                        </span>
                    </header>
                    <ul class="cabinet-sc-plan__list">
                        @foreach($items as $item)
                            @include('pages.partials.seo-checklist-plan-item', [
                                'item' => $item,
                                'roleLabels' => $roleLabels,
                                'statusLabels' => $statusLabels,
                                'canApprove' => true,
                                'canManage' => $item->project
                                    ? app(\App\Services\SeoChecklist\SeoChecklistService::class)
                                        ->canManageProject($item->project, (int) auth()->id())
                                    : false,
                            ])
                        @endforeach
                    </ul>
                </section>
            </div>
            @if($items->hasPages())
                <nav class="cabinet-sc-plan-pager mt-3" aria-label="{{ __('For review') }}">
                    {{ $items->onEachSide(1)->links('pagination::bootstrap-4') }}
                </nav>
            @endif
        @endif
    </div>

    @include('partials.cabinet-confirm-modal')
    @include('pages.partials.seo-checklist-status-modal')

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}?v={{ @filemtime(public_path('js/cabinet-select2-defaults.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-attach.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-attach.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-status-modal.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-status-modal.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-notes.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-notes.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-plan.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-plan.js')) ?: time() }}"></script>
        <script>
            (function () {
                if (!window.jQuery || !jQuery.fn.select2) return;
                var form = document.querySelector('[data-sc-review-filters]');
                var $el = jQuery('[data-sc-review-project]');
                if (!$el.length) return;
                $el.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: $el.data('placeholder') || '',
                    allowClear: true,
                    language: {
                        noResults: function () { return @json(__('Nothing found')); },
                        searching: function () { return @json(__('Searching')); }
                    }
                });
                $el.on('change select2:select select2:clear', function () {
                    if (form) form.submit();
                });
            })();
        </script>
    @endslot
@endcomponent
