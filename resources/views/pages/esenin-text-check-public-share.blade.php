@extends('layouts.public-module')

@section('title', __('Esenin text check'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-esenin-text-check.css') }}?v={{ @filemtime(public_path('css/cabinet-esenin-text-check.css')) ?: time() }}">
@endsection

@section('content')
    <div class="alert alert-info cabinet-esenin-public-banner mb-3">
        <div class="fw-semibold mb-1">{{ __('Public project access') }}</div>
        <div class="small mb-0">
            @if($share->expires_at)
                {{ __('View-only access without registration. Link expires on') }}
                <strong>{{ $share->expires_at->format('d.m.Y H:i') }}</strong>.
            @else
                {{ __('View-only access without registration.') }}
                <strong>{{ __('Site monitoring share ttl unlimited') }}</strong>.
            @endif
            @if(!empty($shareMeta['source_label']))
                <span class="d-block mt-1 text-secondary">{{ __('Source') }}: {{ $shareMeta['source_label'] }}</span>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
            <h1 class="card-title h5 mb-0">
                <i class="bi bi-shield-check me-1 text-primary"></i>{{ __('Esenin text check') }}
            </h1>
            <span class="badge text-bg-secondary">v{{ $shareMeta['version'] ?? config('cabinet-esenin-text-check.version', '1.0') }}</span>
            @if(!empty($shareMeta['generated_at']))
                <span class="small text-secondary ms-auto">{{ __('Generated') }}: {{ $shareMeta['generated_at'] }}</span>
            @endif
        </div>
        <div class="card-body cabinet-esenin-page cabinet-esenin-page--public p-3">
            @if($taskName !== '')
                <p class="fw-semibold mb-3">{{ $taskName }}</p>
            @endif
            <div class="row g-3">
                <div class="col-lg-2">
                    <div class="cabinet-esenin-score-nav" data-esenin-score-nav></div>
                </div>
                <div class="col-lg-7">
                    <div class="cabinet-esenin-text-view card shadow-sm">
                        <div class="card-body">
                            <div class="cabinet-esenin-legend small text-secondary mb-2 d-none" data-esenin-legend></div>
                            <div class="cabinet-esenin-text-view__content cabinet-esenin-text-view__content--readonly" data-esenin-highlight></div>
                            <div class="small text-secondary mt-3" data-esenin-stats></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h6 class="fw-semibold mb-2" data-esenin-panel-title>{{ __('Esenin text check params title') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody data-esenin-params></tbody>
                                </table>
                            </div>
                            <div class="cabinet-esenin-frequency-lists mt-3 d-none flex-grow-1" data-esenin-frequency-lists>
                                <ul class="nav nav-pills nav-fill mb-2 cabinet-esenin-frequency-tabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button type="button" class="nav-link active py-1 px-2" data-esenin-frequency-tab="words">{{ __('Esenin text check words tab') }}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button type="button" class="nav-link py-1 px-2" data-esenin-frequency-tab="phrases">{{ __('Esenin text check phrases tab') }}</button>
                                    </li>
                                </ul>
                                <div class="cabinet-esenin-frequency-panel" data-esenin-frequency-panel="words"></div>
                                <div class="cabinet-esenin-frequency-panel d-none" data-esenin-frequency-panel="phrases"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mb-4">
        <a href="{{ \App\EseninTextCheckPublicShare::registerUrl() }}" class="btn btn-primary">
            {{ __('Register for free') }}
        </a>
    </div>

    <script type="application/json" id="cabinet-esenin-public-config">{!! json_encode([
        'result' => $result,
        'modes' => $modes,
    ], JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('js')
    <script src="{{ asset('js/cabinet-esenin-text-check-public.js') }}?v={{ @filemtime(public_path('js/cabinet-esenin-text-check-public.js')) ?: time() }}"></script>
@endsection
