@component('component.card', [
    'title' => $project->name,
    'titleHtml' => '<span class="visually-hidden">' . e($project->name) . '</span>',
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-project-page cabinet-mon-comp-positions-page" id="cabinet-mon-comp-positions-root">
        @include('monitoring.partials.show.project-chrome', [
            'project' => $project,
            'activeModule' => 'competitors-positions',
            'showViewTabs' => false,
            'pageHint' => __('Monitoring comp positions page hint'),
        ])

        @include('monitoring.competitors.partials.sub-nav', ['project' => $project, 'activeTab' => 'positions'])

        <div class="cabinet-mon-project-page__body">
            <section class="cabinet-mon-comp-positions-workspace card" aria-labelledby="comp-positions-workspace-title">
                <div class="cabinet-mon-comp-positions-workspace__head">
                    <div class="cabinet-mon-comp-positions-workspace__intro">
                        <h2 class="cabinet-mon-comp-positions-workspace__title h6 mb-0" id="comp-positions-workspace-title">
                            {{ __('Monitoring comp positions workspace title') }}
                        </h2>
                        <p class="cabinet-mon-comp-positions-workspace__meta text-secondary small mb-0">
                            {{ __('Monitoring comp positions workspace meta', [
                                'queries' => number_format($totalWords, 0, ',', ' '),
                                'domains' => count($competitors),
                            ]) }}
                        </p>
                    </div>
                </div>

                <div class="cabinet-mon-comp-positions-workspace__form">
                    <div class="cabinet-mon-comp-positions-workspace__field cabinet-mon-comp-positions-workspace__field--region">
                        <label class="cabinet-mon-comp-positions-workspace__label" for="searchEngines">{{ __('Region') }}</label>
                        <select name="region" class="form-select form-select-sm" id="searchEngines">
                            @foreach($searchEngines as $search)
                                <option value="{{ $search->id }}" @if($search->id == request('region')) selected @endif>
                                    {{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($search) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cabinet-mon-comp-positions-workspace__field cabinet-mon-comp-positions-workspace__field--action">
                        <label class="cabinet-mon-comp-positions-workspace__label visually-hidden" for="comp-positions-load">{{ __('Monitoring comp positions load action') }}</label>
                        <button type="button" class="btn btn-primary btn-sm w-100" id="comp-positions-load">
                            <i class="bi bi-table me-1" aria-hidden="true"></i>{{ __('Monitoring comp positions load action') }}
                        </button>
                    </div>
                    <div class="cabinet-mon-comp-positions-workspace__field cabinet-mon-comp-positions-workspace__field--hint">
                        <p class="cabinet-mon-comp-positions-workspace__hint mb-0">{{ __('Monitoring comp positions region hint') }}</p>
                    </div>
                </div>

                <div class="cabinet-mon-comp-positions-idle" id="comp-positions-idle">
                    <p class="cabinet-mon-comp-positions-idle__text text-secondary small mb-0">{{ __('Monitoring comp positions idle hint') }}</p>
                </div>

                <div class="cabinet-mon-comp-positions-progress d-none" id="download-results" role="status" aria-live="polite">
                    @include('monitoring.partials.show.loader', ['size' => 'sm'])
                    <div class="cabinet-mon-comp-positions-progress__copy">
                        <p class="cabinet-mon-comp-positions-progress__text mb-0">
                            {{ __('Monitoring comp positions loading') }}
                            <span class="cabinet-mon-comp-positions-progress__percent" id="comp-positions-progress-percent-wrap">
                                <span class="fw-semibold"><span id="ready-percent">0</span>%</span>
                            </span>
                        </p>
                        <p class="cabinet-mon-comp-positions-progress__detail text-secondary small mb-0" id="comp-positions-progress-detail"></p>
                    </div>
                </div>

                <h3 class="cabinet-mon-comp-positions-section-title d-none" id="comp-positions-table-title">
                    {{ __('Statistics for the selected region') }}
                </h3>

                <div id="comp-positions-table-area" class="d-none">
                    <div class="cabinet-mon-comp-positions-dt-bar">
                        <div id="comp-positions-dt-filter"></div>
                        <div id="comp-positions-dt-length"></div>
                    </div>

                    <div class="cabinet-mon-comp-positions-table-host">
                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-sm mb-0 w-100 cabinet-mon-comp-positions-table">
                            <thead>
                            <tr id="tableHeadRow">
                                <th scope="col">{{ __('Query') }}</th>
                                @foreach($competitors as $key => $competitor)
                                    <th scope="col"
                                        class="cabinet-mon-comp-positions-domain-col@if($key === 0) cabinet-mon-comp-positions-own-col@endif"
                                        data-col-domain="{{ $competitor }}">
                                        @if($key === 0)
                                            <span class="cabinet-mon-comp-positions-own-label">
                                                <i class="bi bi-house-fill me-1 flex-shrink-0" aria-hidden="true"></i>
                                                <span>{{ $competitor }}</span>
                                                <span class="badge rounded-pill text-bg-info ms-1 flex-shrink-0">{{ __('Your website') }}</span>
                                            </span>
                                        @else
                                            <span class="cabinet-mon-comp-positions-domain">{{ $competitor }}</span>
                                            <button type="button"
                                                    class="btn btn-sm cabinet-mon-comp-positions-remove remove-competitor-trigger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#removeCompetitor"
                                                    data-target="{{ $competitor }}"
                                                    data-id="{{ $key }}"
                                                    aria-label="{{ __('Remove') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody id="tableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cabinet-mon-comp-positions-stats card d-none" id="statistics-table" aria-label="{{ __('Monitoring comp positions stats region') }}">
                <div class="cabinet-mon-comp-positions-stats__head">
                    <h2 class="cabinet-mon-comp-positions-stats__title h6 mb-0">{{ __('Monitoring comp positions stats title') }}</h2>
                    <p class="cabinet-mon-comp-positions-stats__hint text-secondary small mb-0">{{ __('Monitoring comp positions stats hint') }}</p>
                </div>

                <div class="cabinet-mon-comp-positions-stats__panels">
                    @foreach([
                        ['id' => 'avg', 'collapse' => 'avgCollapse', 'chart' => 'bar-chart', 'table' => 'avg-position', 'tbody' => 'avg-position-tbody', 'label' => __('Average position'), 'sort' => 'asc'],
                        ['id' => 'top3', 'collapse' => 'top3Collapse', 'chart' => 'bar-chart-3', 'table' => 'top3', 'tbody' => 'top3-tbody', 'label' => __('Percentage of getting into the top') . ' 3', 'sort' => 'desc'],
                        ['id' => 'top10', 'collapse' => 'top10Collapse', 'chart' => 'bar-chart-10', 'table' => 'top10', 'tbody' => 'top10-tbody', 'label' => __('Percentage of getting into the top') . ' 10', 'sort' => 'desc'],
                        ['id' => 'top30', 'collapse' => 'top30Collapse', 'chart' => 'bar-chart-30', 'table' => 'top30', 'tbody' => 'top30-tbody', 'label' => __('Percentage of getting into the top') . ' 30', 'sort' => 'desc'],
                        ['id' => 'top50', 'collapse' => 'top50Collapse', 'chart' => 'bar-chart-50', 'table' => 'top50', 'tbody' => 'top50-tbody', 'label' => __('Percentage of getting into the top') . ' 50', 'sort' => 'desc'],
                        ['id' => 'top100', 'collapse' => 'top100Collapse', 'chart' => 'bar-chart-100', 'table' => 'top100', 'tbody' => 'top100-tbody', 'label' => __('Percentage of getting into the top') . ' 100', 'sort' => 'desc'],
                    ] as $panel)
                        <div class="cabinet-mon-comp-positions-stats-panel">
                            <button class="cabinet-mon-comp-positions-stats-panel__toggle btn btn-outline-secondary btn-sm collapsed chart-button"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#{{ $panel['collapse'] }}"
                                    aria-expanded="false"
                                    aria-controls="{{ $panel['collapse'] }}">
                                <i class="bi bi-chevron-down cabinet-mon-comp-positions-stats-panel__chevron" aria-hidden="true"></i>
                                {{ $panel['label'] }}
                            </button>
                            <div id="{{ $panel['collapse'] }}" class="collapse">
                                <div class="cabinet-mon-comp-positions-stats-panel__body">
                                    <div class="cabinet-mon-comp-positions-chart-wrap">
                                        <canvas id="{{ $panel['chart'] }}"></canvas>
                                    </div>
                                    <div class="cabinet-mon-comp-positions-mini-table-host">
                                        <table class="table table-hover table-sm mb-0 w-100 cabinet-mon-comp-positions-mini-table"
                                               id="{{ $panel['table'] }}"
                                               data-sort="{{ $panel['sort'] }}">
                                            <thead>
                                            <tr>
                                                <th scope="col">{{ __('Domain') }}</th>
                                                <th scope="col">{{ $panel['label'] }}</th>
                                            </tr>
                                            </thead>
                                            <tbody id="{{ $panel['tbody'] }}"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    @include('monitoring.competitors.partials.remove-competitor-modal')

    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3 d-none" style="z-index: 1080;">
        <div class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body toast-message"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        @include('monitoring.partials.smart-search-script')
        <script src="{{ asset('plugins/chart.js/2.7.3/chart.min.js') }}"></script>
        <script>
            window.cabinetMonCompPositionsConfig = {
                projectId: {{ (int) $project->id }},
                regionId: @json(request('region')),
                totalWords: {{ (int) $totalWords }},
                keywords: {!! $keywords !!},
                allKeywords: {!! $allKeywords !!},
                competitors: @json($competitors),
                parallel: {{ (int) $positionsParallel }},
                batchSize: {{ (int) $positionsBatchSize }},
                useBulkLoad: @json($useBulkLoad),
                snapshot: @json($snapshot),
                csrf: @json(csrf_token()),
                routes: {
                    removeCompetitor: @json(route('monitoring.remove.competitor')),
                    getStatistics: @json(route('monitoring.get.competitors.statistics')),
                    competitorsSnapshot: @json(route('monitoring.get.competitors.snapshot')),
                },
                i18n: {
                    removeConfirm: @json(__('Are you going to remove the domain')),
                    fromCompetitors: @json(__('from competitors')),
                    search: @json(__('Search')),
                    empty: @json(__('Empty')),
                    avgPosition: @json(__('Average position')),
                    topPct: @json(__('Percentage of getting into the top')),
                    raiseNeeded: @json(__('Monitoring comp positions chart raise')),
                    dataRetry: @json(__('Data could not be retrieved, the request was duplicated')),
                    tableInfo: @json(__('Monitoring dt table info')),
                    tableInfoEmpty: @json(__('Monitoring dt table info empty')),
                    tableInfoFiltered: @json(__('Monitoring dt table info filtered')),
                    loadingQueries: @json(__('Monitoring comp positions loading queries')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-competitors-positions.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-competitors-positions.js')) ?: time() }}"></script>
    @endslot
@endcomponent
