@extends('layouts.app')

@section('title', __('Monitoring admin page title'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-admin.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-mon-admin-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-sliders text-primary" aria-hidden="true"></i>
                    <span>{{ __('Monitoring admin page title') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 42rem;">
                    {{ __('Monitoring admin page lead') }}
                </p>
            </div>
        </div>

        @include('monitoring.admin.partials.nav', ['active' => 'settings'])

        @include('monitoring.admin.partials.stale-schedules', ['staleMonitoring' => $staleMonitoring])

        {!! Form::open(['route' => ['monitoring.admin.settings.update'], 'class' => 'cabinet-mon-admin-form']) !!}
            @foreach($sections as $sectionKey => $section)
                @include('monitoring.admin.settings.section', [
                    'sectionKey' => $sectionKey,
                    'section' => $section,
                    'values' => $values,
                ])
            @endforeach

            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
                    <p class="small text-secondary mb-0">{{ __('Monitoring admin save hint') }}</p>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Save settings') }}
                    </button>
                </div>
            </div>
        {!! Form::close() !!}
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/inputmask/jquery.inputmask.bundle.js') }}"></script>
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('js/cabinet-button-busy.js') }}?v={{ @filemtime(public_path('js/cabinet-button-busy.js')) ?: time() }}"></script>
    <script>
        window.cabinetMonitoringStaleSchedulesConfig = {
            idPrefix: 'cabinet-mon-admin-stale',
            staleInactiveDays: {{ (int) ($staleMonitoring['inactive_days'] ?? 90) }},
            staleListUrl: @json(route('monitoring.admin.stale-schedules.list')),
            staleDisableUrl: @json(route('monitoring.admin.stale-schedules.disable')),
            usersEditUrlTemplate: @json(url('/users/__ID__/edit')),
            reloadUsersTable: false,
            i18n: {
                staleEmpty: @json(__('Users stale monitoring empty')),
                never: @json(__('Never')),
                disableProject: @json(__('Users stale monitoring disable project')),
                disableUser: @json(__('Users stale monitoring disable user')),
                confirmDisable: @json(__('Users stale monitoring confirm disable')),
                disabled: @json(__('Users stale monitoring disabled toast')),
                disabling: @json(__('Monitoring admin stale schedules disabling')),
                error: @json(__('An error has occurred')),
            },
        };
        toastr.options = {preventDuplicates: true, timeOut: 5000};
        $('.cabinet-mon-admin-time').inputmask('hh:mm', {placeholder: moment().format('H:mm')});
        $(document).on('click', '.cabinet-mon-admin-reset-field', function (e) {
            var msg = $(this).data('confirm') || '';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    </script>
    <script src="{{ asset('js/cabinet-monitoring-stale-schedules.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-stale-schedules.js')) ?: time() }}"></script>
@endsection
