@component('component.card', [
    'title' => $project->name,
    'titleHtml' => '<span class="visually-hidden">' . e($project->name) . '</span>',
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-project-page cabinet-mon-comp-dynamics-page" id="cabinet-mon-comp-dynamics-root">
        @include('monitoring.partials.show.project-chrome', [
            'project' => $project,
            'activeModule' => 'competitors-dynamics',
            'showViewTabs' => false,
            'pageHint' => __('Monitoring comp dynamics page hint'),
        ])

        @include('monitoring.competitors.partials.sub-nav', ['project' => $project, 'activeTab' => 'dynamics'])

        <div class="cabinet-mon-project-page__body">
            @include('monitoring.competitors.partials.dynamics-body', [
                'project' => $project,
                'searchEngines' => $searchEngines,
                'competitorDomains' => $competitorDomains,
            ])
        </div>
    </div>

    @include('monitoring.competitors.partials.remove-history-report-modal')

    @slot('js')
        <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
        <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}?v={{ @filemtime(public_path('js/cabinet-select2-defaults.js')) ?: time() }}"></script>
        <script>
            window.cabinetMonCompDynamicsConfig = {
                projectId: {{ (int) $project->id }},
                ownDomain: @json($project->url),
                competitorDomains: @json($competitorDomains),
                regionId: @json(request('region')),
                csrf: @json(csrf_token()),
                routes: {
                    historyPositions: @json(route('monitoring.competitors.history.positions')),
                    estimateChangesDates: @json(route('monitoring.changes.dates.estimate')),
                    calendarPositions: @json(url('/monitoring/projects/get-positions-for-calendars')),
                    changesDatesCheck: @json(route('monitoring.changes.dates.check')),
                    changesDatesCheckBatch: @json(route('monitoring.changes.dates.check.batch')),
                    changesDatesRemove: @json(route('monitoring.changes.dates.remove')),
                    changesDatesResult: @json(url('/monitoring/competitors/result-analyse')),
                },
                i18n: {
                    empty: @json(__('Empty')),
                    inQueue: @json(__('In queue')),
                    inProgress: @json(__('In progress')),
                    fail: @json(__('Fail')),
                    show: @json(__('show')),
                    removeConfirmHistory: @json(__('Monitoring comp positions history remove confirm')),
                    snapshotsUnit: @json(__('Monitoring comp dates result snapshots')),
                    progressCount: @json(__('Monitoring comp dates progress count')),
                    largeHint: @json(__('Monitoring comp positions history large hint')),
                    largeConfirm: @json(__('Monitoring comp positions history large confirm')),
                    stale: @json(__('Monitoring comp positions history stale')),
                    staleHint: @json(__('Monitoring comp positions history stale hint')),
                    submitting: @json(__('Monitoring comp positions history submitting')),
                    duplicateActive: @json(__('Monitoring comp positions history duplicate active')),
                    submitFail: @json(__('Monitoring comp positions history submit fail')),
                    pendingWaiting: @json(__('Monitoring comp dynamics pending waiting')),
                    pendingPosition: @json(__('Monitoring comp dynamics pending position')),
                    competitorsAll: @json(__('Monitoring comp dynamics competitors all short')),
                    competitorsSelected: @json(__('Monitoring comp dynamics competitors selected short')),
                    competitorsRequired: @json(__('Monitoring comp dynamics competitors required')),
                    deleting: @json(__('Monitoring comp dynamics deleting')),
                    deleteFail: @json(__('Monitoring comp dynamics delete fail')),
                },
                dateRangeI18n: {
                    last7: @json(__('Monitoring show date last 7 days')),
                    last30: @json(__('Monitoring show date last 30 days')),
                    last60: @json(__('Monitoring show date last 60 days')),
                    last90: @json(__('Monitoring show date last 90 days')),
                    last180: @json(__('Monitoring show date last 180 days')),
                    last365: @json(__('Monitoring show date last 365 days')),
                    lastMonth: @json(__('Monitoring show date last month')),
                    apply: @json(__('Apply')),
                    cancel: @json(__('Cancel')),
                    daysOfWeek: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    monthNames: [
                        'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                        'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
                    ],
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-button-busy.js') }}?v={{ @filemtime(public_path('js/cabinet-button-busy.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-monitoring-date-range.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-date-range.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-monitoring-competitors-dynamics.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-competitors-dynamics.js')) ?: time() }}"></script>
    @endslot
@endcomponent
