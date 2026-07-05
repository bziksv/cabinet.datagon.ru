@extends('layouts.app')

@section('title', __('Monitoring set pos page title'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/codemirror/codemirror.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/codemirror/theme/monokai.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-admin.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-mon-admin-page cabinet-mon-set-pos-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-plus-circle text-primary" aria-hidden="true"></i>
                    <span>{{ __('Monitoring set pos page title') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 46rem;">
                    {{ __('Monitoring set pos page lead') }}
                </p>
            </div>
        </div>

        @include('monitoring.admin.partials.nav', ['active' => 'set_positions'])

        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-folder2"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring set pos kpi projects') }}</span>
                        <span class="info-box-number">{{ $projectCount }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-shuffle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring set pos kpi mode') }}</span>
                        <span class="info-box-number small">{{ __('Monitoring set pos kpi mode value') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-broadcast"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring set pos kpi log') }}</span>
                        <span class="info-box-number small">{{ __('Monitoring set pos kpi log value') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @include('monitoring.admin.set_positions.partials.guide')

        <div class="row g-3">
            <div class="col-lg-5">
                <section class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="h6 mb-0">{{ __('Monitoring set pos form title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring set pos form lead') }}</p>
                    </div>
                    <div class="card-body cabinet-mon-set-pos-form">
                        <div class="mb-3">
                            <label class="form-label" for="mon-set-pos-project">{{ __('Monitoring set pos field project') }}</label>
                            <select class="form-select select2" id="mon-set-pos-project" data-placeholder="{{ __('Monitoring set pos project placeholder') }}">
                                <option value="">{{ __('Monitoring set pos project placeholder') }}</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }} — {{ $project->url }} [{{ $project->id }}]</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="mon-set-pos-engine">{{ __('Monitoring set pos field engine') }}</label>
                            <select class="form-select select2" id="mon-set-pos-engine" disabled data-placeholder="{{ __('Monitoring set pos engine placeholder') }}">
                                <option value="">{{ __('Monitoring set pos engine need project') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="mon-set-pos-range">{{ __('Monitoring set pos field dates') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                                <input type="text"
                                       class="form-control"
                                       id="mon-set-pos-range"
                                       placeholder="{{ __('Monitoring set pos dates placeholder') }}"
                                       disabled
                                       autocomplete="off">
                            </div>
                            <div class="form-text">{{ __('Monitoring set pos dates hint') }}</div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
                            <button type="button" class="btn btn-primary" id="mon-set-pos-run" disabled>
                                <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('Monitoring set pos run') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="mon-set-pos-clear-log">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Monitoring set pos clear log') }}
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="card shadow-sm border-0 cabinet-mon-set-pos-log-card h-100">
                    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h3 class="h6 mb-0">{{ __('Monitoring set pos log title') }}</h3>
                            <p class="small text-secondary mb-0">{{ __('Monitoring set pos log lead') }}</p>
                        </div>
                        <span class="badge text-bg-secondary" id="mon-set-pos-log-status">{{ __('Monitoring set pos log idle') }}</span>
                    </div>
                    <div class="card-body p-0">
                        <textarea id="mon-set-pos-log" class="d-none" aria-hidden="true"></textarea>
                        <div id="mon-set-pos-log-editor" class="cabinet-mon-set-pos-log-editor"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/codemirror.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/mode/css/css.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/mode/xml/xml.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/mode/htmlmixed/htmlmixed.js') }}"></script>
    <script src="{{ asset('js/cabinet-button-busy.js') }}?v={{ @filemtime(public_path('js/cabinet-button-busy.js')) ?: time() }}"></script>
    <script src="{{ asset('js/cabinet-monitoring-set-positions.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-set-positions.js')) ?: time() }}"></script>
    <script>
        window.cabinetMonitoringSetPositionsConfig = {
            enginesUrl: @json(route('get.search.engines')),
            runUrl: @json(route('insert.positions')),
            i18n: {
                engineLoading: @json(__('Monitoring set pos engine loading')),
                enginePlaceholder: @json(__('Monitoring set pos engine placeholder')),
                engineNeedProject: @json(__('Monitoring set pos engine need project')),
                runConfirm: @json(__('Monitoring set pos run confirm')),
                runStarted: @json(__('Monitoring set pos run started')),
                runDone: @json(__('Monitoring set pos run done')),
                runError: @json(__('Monitoring set pos run error')),
                logIdle: @json(__('Monitoring set pos log idle')),
                logRunning: @json(__('Monitoring set pos log running')),
                logAdded: @json(__('Monitoring set pos log added')),
                logSkipped: @json(__('Monitoring set pos log skipped')),
                logCleared: @json(__('Monitoring set pos log cleared')),
                runLabel: @json(__('Monitoring set pos run')),
                runBusy: @json(__('Monitoring set pos run busy')),
            },
        };
    </script>
@endsection
