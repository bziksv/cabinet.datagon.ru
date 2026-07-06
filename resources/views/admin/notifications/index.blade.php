@extends('layouts.app')

@section('title', __('Notifications management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-module-kpi.css') }}?v={{ @filemtime(public_path('css/cabinet-module-kpi.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-notifications-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-notifications-admin.css')) ?: time() }}">
@endsection

@section('content')
    @php
        $nStats = ($notifications ?? [])['stats'] ?? [];
        $rows = $tableRows ?? [];
        $dispatchTotals = $dispatchTotals ?? [
            'today' => ['telegram' => 0, 'email' => 0],
            'yesterday' => ['telegram' => 0, 'email' => 0],
            'month' => ['telegram' => 0, 'email' => 0],
        ];
    @endphp

    <div class="cabinet-notifications-admin-page"
         data-test-telegram-url="{{ route('admin.notifications.test.telegram') }}"
         data-test-email-url="{{ route('admin.notifications.test.email') }}"
         data-preview-modal-url="{{ url('/admin/notifications/preview/modal') }}"
         data-preview-email-url="{{ url('/admin/notifications/preview/email') }}">

        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-megaphone text-primary" aria-hidden="true"></i>
                    <span>{{ __('Notifications management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-notifications-admin'])
                </h2>
                <p class="text-secondary small mb-0 cabinet-notify-lead">{{ __('Users notify table lead') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if(Route::has('admin.smtp.index'))
                    <a href="{{ route('admin.smtp.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-envelope-at me-1"></i>{{ __('SMTP management') }}
                    </a>
                @endif
                @if(Route::has('admin.telegram-proxy.index'))
                    <a href="{{ route('admin.telegram-proxy.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-shield-lock me-1"></i>{{ __('Telegram proxy management') }}
                    </a>
                @endif
                @if(Route::has('profile.index'))
                    <a href="{{ route('profile.index') }}#telegram" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-telegram me-1"></i>{{ __('Profile') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="alert alert-info mb-3">
            <p class="fw-semibold mb-2">{{ __('Users notify send how title') }}</p>
            <p class="small mb-2">{{ __('Users notify send how body') }}</p>
            <p class="small mb-0">{{ __('Users notify lang test hint') }}</p>
        </div>

        @if(!$telegramConnected)
            <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <span>{{ __('Users notify test connect telegram') }}</span>
                <a href="{{ route('profile.index') }}#telegram" class="btn btn-sm btn-warning ms-auto">{{ __('Connect Telegram in profile') }}</a>
            </div>
        @endif

        <div class="row g-2 g-md-3 mb-3 cabinet-module-kpi cabinet-notify-stats">
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill shadow-sm">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-telegram"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Telegram connected') }}</span>
                        <span class="info-box-number">{{ number_format($nStats['telegram_users'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill shadow-sm">
                    <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-envelope-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Verified') }}</span>
                        <span class="info-box-number">{{ number_format($nStats['verified_email'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill shadow-sm">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-person-badge"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Users notify test your email') }}</span>
                        <span class="info-box-number small">{{ $adminEmail ?? '—' }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill shadow-sm">
                    <span class="info-box-icon text-bg-cabinet-lang shadow-sm">
                        <img src="{{ asset('img/flags/'.($adminLang ?? 'ru').'.png') }}" class="img-flag" alt="">
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Users notify admin profile lang') }}</span>
                        <span class="info-box-number small text-uppercase">{{ $adminLang ?? 'ru' }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill shadow-sm">
                    <span class="info-box-icon text-bg-secondary shadow-sm"><i class="bi bi-list-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Users notify events title') }}</span>
                        <span class="info-box-number">{{ count($rows) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill shadow-sm">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-send-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Users notify dispatch today total') }}</span>
                        <span class="info-box-number small">
                            <i class="bi bi-telegram me-1"></i>{{ number_format($dispatchTotals['today']['telegram'] ?? 0, 0, ',', ' ') }}
                            <span class="text-secondary mx-1">/</span>
                            <i class="bi bi-envelope me-1"></i>{{ number_format($dispatchTotals['today']['email'] ?? 0, 0, ',', ' ') }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill shadow-sm">
                    <span class="info-box-icon text-bg-dark shadow-sm"><i class="bi bi-calendar-month"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Users notify dispatch month total') }}</span>
                        <span class="info-box-number small">
                            <i class="bi bi-telegram me-1"></i>{{ number_format($dispatchTotals['month']['telegram'] ?? 0, 0, ',', ' ') }}
                            <span class="text-secondary mx-1">/</span>
                            <i class="bi bi-envelope me-1"></i>{{ number_format($dispatchTotals['month']['email'] ?? 0, 0, ',', ' ') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title">{{ __('Users notify table title') }}</h3>
                <div class="card-tools">
                    <input type="search" class="form-control form-control-sm cabinet-notify-table-search" placeholder="{{ __('Search') }}…" aria-label="{{ __('Search') }}">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle mb-0 cabinet-notify-table" id="cabinetNotifyTable">
                        <thead>
                        <tr>
                            <th>{{ __('Module') }}</th>
                            <th>{{ __('Users notify col event') }}</th>
                            <th>{{ __('Users notify when') }}</th>
                            <th>{{ __('Users notify who') }}</th>
                            <th>{{ __('Users notify channels row') }}</th>
                            <th class="cabinet-notify-col-dispatch">{{ __('Users notify col dispatch') }}</th>
                            <th class="text-end cabinet-notify-col-actions">{{ __('Users notify col test langs') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr data-event-id="{{ $row['id'] }}">
                                <td class="cabinet-notify-col-module">
                                    @if(!empty($row['module_url']))
                                        <a href="{{ $row['module_url'] }}" class="text-decoration-none fw-medium">{{ $row['module'] }}</a>
                                    @else
                                        <span class="fw-medium">{{ $row['module'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $row['title'] }}</div>
                                    <div class="small text-secondary">{{ $row['tariff'] }}</div>
                                    @if(!empty($row['cron']))
                                        <code class="small">{{ $row['cron'] }}</code>
                                    @endif
                                </td>
                                <td class="small">{{ $row['trigger'] }}</td>
                                <td class="small">{{ $row['audience'] }}</td>
                                <td>
                                    @forelse($row['channel_badges'] ?? [] as $badge)
                                        <span class="badge text-bg-{{ $badge['color'] ?? 'secondary' }} me-1 mb-1">
                                            <i class="bi {{ $badge['icon'] ?? 'bi-bell' }} me-1"></i>{{ $badge['label'] }}
                                        </span>
                                    @empty
                                        <span class="badge text-bg-light text-dark border">{{ __('Users notify channel in app only') }}</span>
                                    @endforelse
                                </td>
                                <td class="cabinet-notify-col-dispatch">
                                    @include('admin.notifications.partials.dispatch-stats', ['row' => $row])
                                </td>
                                <td class="text-end text-nowrap cabinet-notify-col-actions">
                                    @include('admin.notifications.partials.locale-test-buttons', ['row' => $row])
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-secondary">
                {{ __('Users notify table footnote') }}
            </div>
        </div>
    </div>

    <div class="modal fade" id="cabinetNotifyPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" id="cabinetNotifyPreviewModalContent">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinetNotifyPreviewModalTitle">{{ __('Users notify btn preview modal') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body p-0" id="cabinetNotifyPreviewModalBody"></div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('js/cabinet-notifications-admin.js') }}?v={{ @filemtime(public_path('js/cabinet-notifications-admin.js')) ?: time() }}"></script>
@endsection
