@extends('layouts.public-module')

@section('title', __('Monitoring public share report title'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-v2.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-v2.css')) ?: time() }}">
@endsection

@section('content')
    <div class="alert alert-info cabinet-mon-v2-public-banner mb-3">
        <div class="fw-semibold mb-1">{{ __('Public project access') }}</div>
        <div class="small mb-0">
            @if($share->expires_at)
                {{ __('View-only access without registration. Link expires on') }}
                <strong>{{ $share->expires_at->format('d.m.Y H:i') }}</strong>.
            @else
                {{ __('View-only access without registration.') }}
                <strong>{{ __('Monitoring share ttl unlimited') }}</strong>.
            @endif
            @if(!empty($shareMeta['source_label']))
                <span class="d-block mt-1 text-secondary">{{ __('Source') }}: {{ $shareMeta['source_label'] }}</span>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
            <h1 class="card-title h5 mb-0">
                <i class="bi bi-graph-up me-1 text-primary"></i>{{ __('Monitoring position') }}
            </h1>
            <span class="badge text-bg-secondary">v{{ $shareMeta['version'] ?? config('cabinet-monitoring.version', '1.0') }}</span>
            @if(!empty($shareMeta['generated_at']))
                <span class="small text-secondary ms-auto">{{ __('Generated') }}: {{ $shareMeta['generated_at'] }}</span>
            @endif
        </div>
        <div class="card-body cabinet-mon-v2-public-page p-3">
            <p class="mb-3">
                <strong>{{ $report['project']['name'] ?? '—' }}</strong><br>
                <a href="{{ $report['project']['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer">{{ $report['project']['url'] ?? '' }}</a>
            </p>
            @include('monitoring-v2.partials.public-stats-report-body', [
                'report' => $report,
                'isPublicView' => true,
            ])
        </div>
    </div>

    <div class="text-center mb-4">
        <a href="{{ \App\MonitoringPublicShare::registerUrl() }}" class="btn btn-primary">
            {{ __('Register for free') }}
        </a>
    </div>
@endsection
