@extends('layouts.app')

@section('title', __('SMTP management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-smtp-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-smtp-admin.css')) ?: time() }}">
@endsection

@section('content')
    @php
        $s = $settings ?? [];
        $st = $status ?? [];
    @endphp

    <div class="cabinet-smtp-admin-page"
         data-test-email-url="{{ route('admin.smtp.test-email') }}">

        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-envelope-at text-primary" aria-hidden="true"></i>
                    <span>{{ __('SMTP management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-smtp-admin'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 44rem;">
                    {{ __('SMTP admin lead') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if(Route::has('admin.notifications.index'))
                    <a href="{{ route('admin.notifications.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-megaphone me-1"></i>{{ __('Notifications management') }}
                    </a>
                @endif
                <form action="{{ route('admin.smtp.import-env') }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-in-down me-1"></i>{{ __('SMTP admin import env') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="info-box mb-0">
                            <span class="info-box-icon text-bg-{{ !empty($s['enabled']) ? 'success' : 'secondary' }} shadow-sm">
                                <i class="bi bi-toggle-on" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ __('SMTP admin source') }}</span>
                                <span class="info-box-number small">
                                    @if(!empty($s['enabled']))
                                        {{ __('SMTP admin source db') }}
                                    @else
                                        {{ __('SMTP admin source env') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="info-box mb-0">
                            <span class="info-box-icon text-bg-primary shadow-sm">
                                <i class="bi bi-hdd-network" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ __('SMTP admin host label') }}</span>
                                <span class="info-box-number small text-break">
                                    @if(!empty($st['host']))
                                        <code>{{ $st['host'] }}</code>
                                    @else
                                        {{ __('Not configured') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="info-box mb-0">
                            <span class="info-box-icon text-bg-info shadow-sm">
                                <i class="bi bi-person-badge" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ __('SMTP admin from label') }}</span>
                                <span class="info-box-number small text-break">
                                    @if(!empty($st['from_address']))
                                        <code>{{ $st['from_address'] }}</code>
                                    @else
                                        {{ __('Not configured') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title h6 mb-0">{{ __('SMTP admin settings title') }}</h3>
                    </div>
                    <form action="{{ route('admin.smtp.update') }}" method="post">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       id="smtp-enabled"
                                       name="enabled"
                                       value="1"
                                       {{ !empty($s['enabled']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="smtp-enabled">
                                    {{ __('SMTP admin use db settings') }}
                                </label>
                                <div class="form-text">{{ __('SMTP admin use db hint') }}</div>
                            </div>

                            <div class="mb-3">
                                <label for="smtp-provider" class="form-label">{{ __('SMTP admin provider') }}</label>
                                <input type="text"
                                       class="form-control"
                                       id="smtp-provider"
                                       name="provider_label"
                                       value="{{ old('provider_label', $s['provider_label'] ?? '') }}"
                                       placeholder="SendGrid, Mailgun, Amazon SES…">
                                <div class="form-text">{{ __('SMTP admin provider hint') }}</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="smtp-host" class="form-label">{{ __('SMTP admin host label') }}</label>
                                    <input type="text"
                                           class="form-control @error('host') is-invalid @enderror"
                                           id="smtp-host"
                                           name="host"
                                           value="{{ old('host', $s['host'] ?? '') }}"
                                           placeholder="smtp.example.com">
                                    @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="smtp-port" class="form-label">{{ __('SMTP admin port') }}</label>
                                    <input type="number"
                                           class="form-control @error('port') is-invalid @enderror"
                                           id="smtp-port"
                                           name="port"
                                           min="1"
                                           max="65535"
                                           value="{{ old('port', $s['port'] ?? 587) }}">
                                    @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-md-4">
                                    <label for="smtp-encryption" class="form-label">{{ __('SMTP admin encryption') }}</label>
                                    <select class="form-select" id="smtp-encryption" name="encryption">
                                        @php
                                            $enc = old('encryption', $s['encryption'] ?? '');
                                            $enc = $enc === null ? '' : strtolower((string) $enc);
                                        @endphp
                                        <option value="" {{ $enc === '' ? 'selected' : '' }}>{{ __('SMTP admin encryption none') }}</option>
                                        <option value="tls" {{ $enc === 'tls' ? 'selected' : '' }}>TLS</option>
                                        <option value="ssl" {{ $enc === 'ssl' ? 'selected' : '' }}>SSL</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="smtp-username" class="form-label">{{ __('SMTP admin username') }}</label>
                                    <input type="text"
                                           class="form-control"
                                           id="smtp-username"
                                           name="username"
                                           autocomplete="off"
                                           value="{{ old('username', $s['username'] ?? '') }}">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="smtp-password" class="form-label">{{ __('SMTP admin password') }}</label>
                                <input type="password"
                                       class="form-control"
                                       id="smtp-password"
                                       name="password"
                                       autocomplete="new-password"
                                       placeholder="{{ __('SMTP admin password placeholder') }}">
                                <div class="form-text">
                                    @if(!empty($s['password_set']))
                                        {{ __('SMTP admin password current') }}: <code>{{ $s['password_masked'] ?? '—' }}</code>
                                    @else
                                        {{ __('SMTP admin password empty') }}
                                    @endif
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label for="smtp-from-address" class="form-label">{{ __('SMTP admin from label') }}</label>
                                    <input type="email"
                                           class="form-control @error('from_address') is-invalid @enderror"
                                           id="smtp-from-address"
                                           name="from_address"
                                           value="{{ old('from_address', $s['from_address'] ?? '') }}"
                                           placeholder="info@titlo.ru">
                                    @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-5">
                                    <label for="smtp-from-name" class="form-label">{{ __('SMTP admin from name ru') }}</label>
                                    <input type="text"
                                           class="form-control"
                                           id="smtp-from-name"
                                           name="from_name"
                                           value="{{ old('from_name', $s['from_name'] ?? '') }}"
                                           placeholder="{{ __('SMTP admin from name ru placeholder') }}">
                                </div>
                                <div class="col-12">
                                    <label for="smtp-from-name-en" class="form-label">{{ __('SMTP admin from name en') }}</label>
                                    <input type="text"
                                           class="form-control"
                                           id="smtp-from-name-en"
                                           name="from_name_en"
                                           value="{{ old('from_name_en', $s['from_name_en'] ?? '') }}"
                                           placeholder="{{ __('SMTP admin from name en placeholder') }}">
                                    <div class="form-text">{{ __('SMTP admin from name en hint') }}</div>
                                </div>
                            </div>

                            <input type="hidden" name="driver" value="smtp">
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>{{ __('Save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card card-outline card-secondary shadow-sm h-100">
                    <div class="card-header">
                        <h3 class="card-title h6 mb-0">{{ __('SMTP admin test title') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">{{ __('SMTP admin test hint') }}</p>
                        <div class="mb-3">
                            <label for="smtp-test-email" class="form-label">{{ __('Email') }}</label>
                            <input type="email"
                                   class="form-control"
                                   id="smtp-test-email"
                                   value="{{ auth()->user()->email ?? '' }}">
                        </div>
                        <button type="button" class="btn btn-outline-success" id="smtp-test-send">
                            <i class="bi bi-send me-1"></i>{{ __('SMTP admin test button') }}
                        </button>
                        <p class="small text-muted mt-3 mb-0" id="smtp-test-result" aria-live="polite"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script>
        (function () {
            var root = document.querySelector('.cabinet-smtp-admin-page');
            if (!root) return;

            var btn = document.getElementById('smtp-test-send');
            var emailInput = document.getElementById('smtp-test-email');
            var resultEl = document.getElementById('smtp-test-result');
            var url = root.getAttribute('data-test-email-url');
            var token = document.querySelector('meta[name="csrf-token"]');
            var csrf = token ? token.getAttribute('content') : '';

            if (!btn || !url) return;

            btn.addEventListener('click', function () {
                btn.disabled = true;
                if (resultEl) resultEl.textContent = '';

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ email: emailInput ? emailInput.value : '' })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (typeof toastr !== 'undefined') {
                            toastr[data.ok ? 'success' : 'error'](data.message || '');
                        }
                        if (resultEl) {
                            resultEl.textContent = data.message || '';
                            resultEl.className = 'small mt-3 mb-0 ' + (data.ok ? 'text-success' : 'text-danger');
                        }
                    })
                    .catch(function (e) {
                        if (typeof toastr !== 'undefined') {
                            toastr.error(e.message || 'Error');
                        }
                    })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
        })();
    </script>
@endsection
