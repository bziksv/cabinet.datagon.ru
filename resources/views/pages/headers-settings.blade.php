@component('component.card', [
    'title' => __('Http headers'),
    'titleHtml' => e(__('Http headers')) . ' — ' . e(__('Settings'))
        . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-http-headers'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-http-headers.css') }}?v={{ @filemtime(public_path('css/cabinet-http-headers.css')) ?: time() }}">
    @endslot

    <div class="cabinet-hh-page cabinet-hh-settings-page">
        @include('pages.partials.http-headers-module-nav', ['active' => 'settings'])

        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif

        <div class="cabinet-hh-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-hh-lead__icon" aria-hidden="true">
                    <i class="bi bi-gear"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Http headers settings title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Http headers settings hint') }}</p>
                </div>
            </div>
        </div>

        <section class="cabinet-hh-panel card border shadow-sm">
            <div class="card-body">
                {!! Form::open(['route' => 'pages.headers.settings', 'method' => 'GET']) !!}
                <div class="row g-3">
                    <div class="col-md-8 col-lg-6">
                        <label class="form-label fw-medium" for="delete_records">{{ __('Http headers retention label') }}</label>
                        <div class="input-group">
                            <input type="number"
                                   name="delete_records"
                                   id="delete_records"
                                   min="0"
                                   class="form-control"
                                   value="{{ $delete_records }}">
                            <span class="input-group-text">{{ __('days') }}</span>
                        </div>
                        <div class="form-text">{{ __('Http headers retention hint') }}</div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Save') }}
                    </button>
                    <a href="{{ url('/http-headers') }}" class="btn btn-outline-secondary ms-2">{{ __('Cancel') }}</a>
                </div>
                {!! Form::close() !!}
            </div>
        </section>
    </div>
@endcomponent
