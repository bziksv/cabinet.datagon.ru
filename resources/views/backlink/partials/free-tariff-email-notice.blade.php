@if($onFreeTariff ?? false)
    <div class="alert alert-warning border-warning mb-0 cabinet-bl-free-email-notice" role="note">
        <p class="mb-1 fw-semibold">
            <i class="bi bi-envelope-x me-1" aria-hidden="true"></i>{{ __('Backlink free tariff email notice title') }}
        </p>
        <p class="mb-0 small">
            {{ __('Backlink free tariff email notice body') }}
            <a href="{{ route('profile.index') }}#telegram">{{ __('Backlink free tariff email notice profile') }}</a>.
            {{ __('Backlink free tariff email notice paid') }}
            <a href="{{ route('tariff.index') }}">{{ __('Tariff') }}</a>.
        </p>
        @if(!($telegramConnected ?? false))
            <p class="mb-0 small mt-2 text-secondary">
                <i class="bi bi-telegram me-1" aria-hidden="true"></i>{{ __('Backlink telegram not connected hint') }}
            </p>
        @endif
    </div>
@endif
