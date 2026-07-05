@extends('layouts.app')

@section('title', __('Monitoring perm page title'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-admin.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-mon-admin-page cabinet-mon-perm-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-shield-lock text-primary" aria-hidden="true"></i>
                    <span>{{ __('Monitoring perm page title') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 46rem;">
                    {{ __('Monitoring perm page lead') }}
                </p>
            </div>
        </div>

        @include('monitoring.admin.partials.nav', ['active' => 'permissions'])

        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-person-badge"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring perm kpi roles') }}</span>
                        <span class="info-box-number">{{ $roles->count() }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-key"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring perm kpi permissions') }}</span>
                        <span class="info-box-number">{{ $permissions->count() }}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-secondary shadow-sm"><i class="bi bi-diagram-3"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitoring perm kpi scope') }}</span>
                        <span class="info-box-number small">{{ __('Monitoring perm kpi scope value') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @include('monitoring.permissions.partials.guide')

        <form action="{{ route('monitoring-permissions.store') }}" method="post" id="mon-perm-form" class="cabinet-mon-perm-form">
            @csrf
            <section class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h3 class="h6 mb-0">{{ __('Monitoring perm matrix title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring perm matrix lead') }}</p>
                    </div>
                    <span class="small text-secondary" id="mon-perm-save-status" aria-live="polite"></span>
                </div>
                <div class="card-body p-2 p-md-3">
                    <div class="accordion cabinet-mon-perm-accordion" id="mon-perm-accordion">
                        @foreach($roles as $role)
                            @include('monitoring.permissions.partials.role-accordion', [
                                'role' => $role,
                                'loop' => $loop,
                                'enabledCount' => \App\Support\MonitoringPermissionsCatalog::roleEnabledCount($role, $permissions),
                            ])
                        @endforeach
                    </div>
                </div>
            </section>
        </form>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('js/cabinet-monitoring-permissions.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-permissions.js')) ?: time() }}"></script>
    <script>
        window.cabinetMonitoringPermissionsConfig = {
            saveUrl: @json(route('monitoring-permissions.store')),
            i18n: {
                saved: @json(__('Monitoring perm saved')),
                saving: @json(__('Monitoring perm saving')),
                error: @json(__('Monitoring perm save error')),
                enabledCount: @json(__('Monitoring perm enabled count', ['enabled' => ':enabled', 'total' => ':total'])),
            },
        };
    </script>
@endsection
