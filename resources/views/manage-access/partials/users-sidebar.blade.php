@php
    $total = $userStats['total'] ?? 0;
    $verified = $userStats['verified'] ?? 0;
    $telegram = $userStats['telegram'] ?? 0;
@endphp

<div class="card shadow-sm cabinet-ma-users-side">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-people me-1"></i>{{ __('Users') }}
        </h3>
    </div>
    <div class="card-body p-0">
        <a href="{{ route('users.index', ['verify' => 'verified']) }}"
           class="cabinet-ma-user-stat d-flex align-items-center gap-3 px-3 py-3 text-decoration-none text-body border-bottom">
            <span class="cabinet-ma-user-stat__icon text-bg-success">
                <i class="bi bi-patch-check" aria-hidden="true"></i>
            </span>
            <span class="flex-grow-1 min-w-0">
                <span class="d-block small text-secondary">{{ __('Verified') }}</span>
                <span class="d-block fw-semibold fs-5 tabular-nums">{{ number_format($verified, 0, ',', ' ') }}</span>
                @if($total > 0)
                    <span class="d-block small text-secondary">{{ $userStats['verified_percent'] }}% {{ __('of all users') }}</span>
                @endif
            </span>
            <i class="bi bi-chevron-right text-secondary" aria-hidden="true"></i>
        </a>

        <a href="{{ route('users.index', ['telegram' => '1']) }}"
           class="cabinet-ma-user-stat d-flex align-items-center gap-3 px-3 py-3 text-decoration-none text-body">
            <span class="cabinet-ma-user-stat__icon text-bg-info">
                <i class="bi bi-telegram" aria-hidden="true"></i>
            </span>
            <span class="flex-grow-1 min-w-0">
                <span class="d-block small text-secondary">{{ __('Telegram connected') }}</span>
                <span class="d-block fw-semibold fs-5 tabular-nums">{{ number_format($telegram, 0, ',', ' ') }}</span>
                @if($total > 0)
                    <span class="d-block small text-secondary">{{ $userStats['telegram_percent'] }}% {{ __('of all users') }}</span>
                @endif
            </span>
            <i class="bi bi-chevron-right text-secondary" aria-hidden="true"></i>
        </a>
    </div>
    <div class="card-footer py-2 small text-secondary">
        <div class="d-flex justify-content-between mb-2">
            <span>{{ __('Total users') }}</span>
            <span class="fw-semibold tabular-nums">{{ number_format($total, 0, ',', ' ') }}</span>
        </div>
        <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary w-100">
            <i class="bi bi-people me-1"></i>{{ __('Open users list') }}
        </a>
    </div>
</div>
