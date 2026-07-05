@extends('layouts.app')

@section('title', __('Monitoring offset page title'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-admin.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-mon-admin-page cabinet-mon-offset-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-pencil-square text-primary" aria-hidden="true"></i>
                    <span>{{ __('Monitoring offset page title') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 46rem;">
                    {{ __('Monitoring offset page lead') }}
                </p>
            </div>
        </div>

        @include('monitoring.admin.partials.nav', ['active' => 'offset_positions'])

        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-folder2"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring offset kpi projects') }}</span>
                        <span class="info-box-number">{{ $projectCount }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-file-earmark-arrow-down"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring offset kpi scope') }}</span>
                        <span class="info-box-number small">{{ __('Monitoring offset kpi scope value') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-sliders"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring offset kpi rules') }}</span>
                        <span class="info-box-number">3</span>
                    </div>
                </div>
            </div>
        </div>

        @include('monitoring.admin.offset_positions.partials.guide')

        <div class="row g-3">
            <div class="col-lg-5">
                <section class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h3 class="h6 mb-0">{{ __('Monitoring offset form title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring offset form lead') }}</p>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="mon-offset-project">{{ __('Monitoring offset field project') }}</label>
                            <select class="form-select select2" id="mon-offset-project" data-placeholder="{{ __('Monitoring offset project placeholder') }}">
                                <option value="">{{ __('Monitoring offset project placeholder') }}</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }} — {{ $project->url }} [{{ $project->id }}]</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="button" class="btn btn-primary w-100" id="mon-offset-open-export" disabled>
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('Monitoring offset open export') }}
                        </button>
                    </div>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="h6 mb-0">{{ __('Monitoring offset rules title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring offset rules lead') }}</p>
                    </div>
                    <div class="card-body">
                        @include('monitoring.admin.offset_positions.partials.offset-rules')

                        <div class="alert alert-light border small mb-0 mt-2">
                            <i class="bi bi-lightbulb me-1 text-warning"></i>
                            {{ __('Monitoring offset rules example') }}
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('monitoring.keywords.modal.main')
@endsection

@section('js')
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('plugins/moment/moment-with-locales.min.js') }}"></script>
    <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="{{ asset('js/cabinet-button-busy.js') }}?v={{ @filemtime(public_path('js/cabinet-button-busy.js')) ?: time() }}"></script>
    <script src="{{ asset('js/cabinet-monitoring-offset-positions.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-offset-positions.js')) ?: time() }}"></script>
    <script>
        window.cabinetMonitoringOffsetConfig = {
            exportEditUrlTemplate: @json(url('/monitoring/__ID__/export/edit')),
            i18n: {
                projectRequired: @json(__('Monitoring offset project required')),
                exportLoading: @json(__('Monitoring offset export loading')),
                exportLoadError: @json(__('Monitoring offset export load error')),
                exportSubmit: @json(__('Monitoring offset open export')),
                exportSubmitBusy: @json(__('Monitoring offset export busy')),
            },
        };
    </script>
@endsection
