@component('component.card', [
    'title' => $project->name,
    'titleHtml' => '<span class="visually-hidden">' . e($project->name) . '</span>',
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-project-page cabinet-mon-top100-page" id="cabinet-mon-top100-root">
        @include('monitoring.partials.show.project-chrome', [
            'project' => $project,
            'activeModule' => 'top100',
            'showViewTabs' => false,
        ])

        <div class="cabinet-mon-project-page__body">
            @include('monitoring.top100.partials.guide')

            <section class="cabinet-mon-top100-workspace card" aria-label="{{ __('Analysis Settings') }}">
                <div class="cabinet-mon-top100-panel cabinet-mon-top100-panel--setup">
                    <div class="cabinet-mon-top100-panel__head">
                        <h3 class="h6 mb-1">{{ __('Monitoring top100 setup title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring top100 setup lead') }}</p>
                    </div>
                    @php
                        $top100SelectedLr = request('region');
                    @endphp
                    <form class="cabinet-mon-top100-setup-grid" autocomplete="off" novalidate>
                        <div class="cabinet-mon-top100-step-field cabinet-mon-top100-field--phrase">
                            <div class="cabinet-mon-top100-step-field__head">
                                <span class="cabinet-mon-top100-step-badge" aria-hidden="true">1</span>
                                <label class="cabinet-mon-top100-field__label mb-0" for="words-select">{{ __('Phrase') }}</label>
                            </div>
                            <div class="cabinet-mon-top100-step-field__control">
                                <select class="form-select form-select-sm" id="words-select" name="top100-phrase" autocomplete="off">
                                    <option value=""></option>
                                    @foreach($project->keywords as $keyword)
                                        <option value="{{ $keyword->query }}">{{ $keyword->query }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cabinet-mon-top100-step-field__hint" aria-hidden="true"></div>
                        </div>
                        <div class="cabinet-mon-top100-step-field cabinet-mon-top100-field--dates">
                            <div class="cabinet-mon-top100-step-field__head">
                                <span class="cabinet-mon-top100-step-badge" aria-hidden="true">2</span>
                                <label class="cabinet-mon-top100-field__label mb-0" for="date-range">{{ __('Date range') }}</label>
                            </div>
                            <div class="cabinet-mon-top100-step-field__control">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                                    <input type="text" id="date-range" name="top100-date-range" class="form-control" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="{{ __('Monitoring top100 dates placeholder') }}" readonly>
                                </div>
                            </div>
                            <div class="cabinet-mon-top100-step-field__hint">
                                <div class="form-text mb-0">{{ __('Monitoring top100 dates hint') }}</div>
                            </div>
                        </div>
                        <div class="cabinet-mon-top100-step-field cabinet-mon-top100-field--region">
                            <div class="cabinet-mon-top100-step-field__head">
                                <span class="cabinet-mon-top100-step-badge" aria-hidden="true">3</span>
                                <label class="cabinet-mon-top100-field__label mb-0" for="searchEngines">{{ __('Region') }}</label>
                            </div>
                            <div class="cabinet-mon-top100-step-field__control">
                                <select name="top100-region" class="form-select form-select-sm" id="searchEngines" autocomplete="off">
                                    <option value="" @if(!$top100SelectedLr) selected @endif>{{ __('Select a search region') }}</option>
                                    @foreach($project->searchengines as $search)
                                        <option value="{{ $search->lr }}" @if((string) $search->lr === (string) $top100SelectedLr) selected @endif>
                                            {{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($search) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cabinet-mon-top100-step-field__hint">
                                <div class="form-text mb-0">{{ __('Monitoring top100 region hint') }}</div>
                            </div>
                        </div>
                        <div class="cabinet-mon-top100-step-field cabinet-mon-top100-field--action">
                            <div class="cabinet-mon-top100-step-field__head">
                                <span class="cabinet-mon-top100-step-badge" aria-hidden="true">4</span>
                                <span class="cabinet-mon-top100-field__label mb-0">{{ __('Monitoring top100 step run label') }}</span>
                            </div>
                            <div class="cabinet-mon-top100-step-field__control">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="analyse">
                                    <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('Analyse') }}
                                </button>
                            </div>
                            <div class="cabinet-mon-top100-step-field__hint" aria-hidden="true"></div>
                        </div>
                    </form>
                </div>

                <div class="cabinet-mon-top100-panel cabinet-mon-top100-panel--tools is-locked" id="top100-tools-panel">
                    <div class="cabinet-mon-top100-panel__head">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="cabinet-mon-top100-step-badge cabinet-mon-top100-step-badge--muted" aria-hidden="true">5</span>
                            <div>
                                <h3 class="h6 mb-0">{{ __('Monitoring top100 tools title') }}</h3>
                                <p class="small text-secondary mb-0">{{ __('Monitoring top100 tools lead') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="cabinet-mon-top100-tools-grid">
                        <div class="cabinet-mon-top100-field cabinet-mon-top100-field--top">
                            <label class="cabinet-mon-top100-field__label" for="top">{{ __('The maximum value of the top') }}</label>
                            <div class="cabinet-mon-top100-field__control">
                                <select name="top" id="top" class="form-select form-select-sm" disabled>
                                    <option value="100">TOP 100</option>
                                    <option value="50">TOP 50</option>
                                    <option value="30">TOP 30</option>
                                    <option value="20">TOP 20</option>
                                    <option value="10">TOP 10</option>
                                </select>
                            </div>
                        </div>
                        <div class="cabinet-mon-top100-field cabinet-mon-top100-field--display">
                            <span class="cabinet-mon-top100-field__label">{{ __('Display') }}</span>
                            <div class="cabinet-mon-top100-field__control">
                                <div class="cabinet-mon-top-presets__buttons" role="group" aria-label="{{ __('Display') }}">
                                    <button type="button" class="btn btn-sm btn-outline-secondary active change-filter-name" data-action="URL" disabled>URL</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary change-filter-name" data-action="domain" disabled>{{ __('Domain') }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="cabinet-mon-top100-field cabinet-mon-top100-field--filter">
                            <label class="cabinet-mon-top100-field__label" for="filter">{{ __('Filter by') }} <span id="filter-target">URL</span></label>
                            <div class="cabinet-mon-top100-field__control">
                                <input type="text" id="filter" name="filter" class="form-control form-control-sm" autocomplete="off" disabled>
                            </div>
                        </div>
                        <div class="cabinet-mon-top100-field cabinet-mon-top100-field--highlights">
                            <span class="cabinet-mon-top100-field__label">{{ __('Monitoring top100 highlights') }}</span>
                            <div class="cabinet-mon-top100-field__control">
                                <div class="cabinet-mon-top100-highlight-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-action="color" id="select-my-project"
                                            data-target="{{ $project->url }}" disabled>
                                        {{ __('Select the project domain') }}
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-action="color" id="select-my-competitors" disabled>
                                        {{ __('Select my competitors') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cabinet-mon-top100-panel cabinet-mon-top100-panel--results">
                    <div class="cabinet-mon-top100-panel__head cabinet-mon-top100-panel__head--results d-none" id="top100-results-head">
                        <h3 class="h6 mb-0">{{ __('Monitoring top100 results title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring top100 results lead') }}</p>
                    </div>
                    <div class="cabinet-mon-top100-workspace__body" id="top100-workspace-body">
                        <div class="cabinet-mon-top100-empty" id="top100-empty-state">
                            <div class="cabinet-mon-top100-empty__icon" aria-hidden="true"><i class="bi bi-layout-three-columns"></i></div>
                            <h3 class="cabinet-mon-top100-empty__title h6">{{ __('Monitoring top100 empty title') }}</h3>
                            <p class="cabinet-mon-top100-empty__text">{{ __('Monitoring top100 empty text') }}</p>
                            <button type="button" class="btn btn-primary btn-sm" id="top100-empty-analyse">
                                <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('Monitoring top100 step run label') }}
                            </button>
                        </div>

                        <div class="cabinet-mon-top100-progress d-none" id="progress" role="status" aria-live="polite">
                            @include('monitoring.partials.show.loader', ['size' => 'sm', 'label' => __('Monitoring top100 progress')])
                            <span class="cabinet-mon-top100-progress__text">
                                {{ __('Monitoring top100 progress done') }} <span id="analysed-days">0</span> {{ __('of') }} <span id="total-days">0</span>
                            </span>
                        </div>

                        <div class="cabinet-mon-top100-workspace__scroll d-none" id="top100-result-wrap"></div>
                        <div class="cabinet-mon-top100-empty d-none" id="top100-no-data">
                            <div class="cabinet-mon-top100-empty__icon" aria-hidden="true"><i class="bi bi-inbox"></i></div>
                            <h3 class="cabinet-mon-top100-empty__title h6">{{ __('Monitoring top100 no data title') }}</h3>
                            <p class="cabinet-mon-top100-empty__text" id="top100-no-data-text">{{ __('Monitoring top100 no data text') }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div id="top100-toast-error" class="toast-container position-fixed top-0 end-0 p-3 d-none" style="z-index: 1080;">
        <div class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toast-message"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>
    </div>

    <div id="top100-toast-success" class="toast-container position-fixed top-0 end-0 p-3 d-none" style="z-index: 1080;">
        <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body toast-message-success"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/moment/moment-with-locales.min.js') }}"></script>
        <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            window.cabinetMonTop100Config = {
                projectId: {{ (int) $project->id }},
                projectUrl: @json($project->url),
                competitors: @json($project->competitors->toArray()),
                csrf: @json(csrf_token()),
                routes: {
                    getTopSites: @json(route('monitoring.get.top.sites')),
                    calendarPositions: @json(url('/monitoring/projects/get-positions-for-calendars')),
                    textAnalyzerRedirect: @json(url('/redirect-to-text-analyzer')),
                },
                i18n: {
                    selectPhrase: @json(__('Monitoring top100 select phrase')),
                    selectRegion: @json(__('Select a search region')),
                    copied: @json(__('Monitoring top100 copied')),
                    analyse: @json(__('Analyse')),
                    copyUrl: @json(__('Copy URL')),
                    copyDomain: @json(__('Copy domain')),
                    viewPositions: @json(__('View positions')),
                    openSite: @json(__('Monitoring top100 open site')),
                    noMatches: @json(__('No matches found')),
                    deleteLink: @json(__('Delete a link of positions')),
                    selectProject: @json(__('Select the project domain')),
                    removeProject: @json(__('Remove project selection')),
                    domainNotFound: @json(__('Domain not found')),
                    selectCompetitors: @json(__('Select my competitors')),
                    removeCompetitors: @json(__('Remove the selection of competitors')),
                    domainsNotFound: @json(__('Domains not found')),
                    calendarError: @json(__('Something is going wrong')),
                    selectDates: @json(__('Monitoring top100 select dates')),
                    noSnapshot: @json(__('Monitoring top100 no snapshot')),
                    noData: @json(__('Monitoring top100 no data text')),
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
                    modeRange: @json(__('Monitoring show date mode range')),
                    modeDatesFind: @json(__('Monitoring show date mode dates find')),
                    modeDates: @json(__('Monitoring show date mode dates')),
                    modeRandWeek: @json(__('Monitoring show date mode rand week')),
                    modeRandMonth: @json(__('Monitoring show date mode rand month')),
                    daysOfWeek: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    monthNames: [
                        'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                        'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
                    ],
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-date-range.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-date-range.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-monitoring-top100.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-top100.js')) ?: time() }}"></script>
    @endslot
@endcomponent
