@php
    $user = auth()->user();
    $telegramConnected = $user && $user->isTelegramConnected();
    $extraClass = $extraClass ?? '';
@endphp
@if($user && !$telegramConnected)
    <div class="alert alert-info border-info mb-0 cabinet-telegram-notify-notice {{ $extraClass }}" role="note">
        <p class="mb-1 fw-semibold">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('Cabinet telegram notify notice title') }}
        </p>
        <p class="mb-2 small">
            {{ __('Cabinet telegram notify notice body') }}
        </p>
        <a href="{{ route('profile.index') }}#telegram" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-telegram me-1" aria-hidden="true"></i>{{ __('Connect Telegram in profile') }}
        </a>
    </div>
@endif
