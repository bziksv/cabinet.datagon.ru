@php
    $locales = ['ru', 'en'];
@endphp

@if(!empty($row['can_preview_modal']))
    <div class="cabinet-notify-lang-row mb-1">
        <span class="cabinet-notify-lang-channel" title="{{ __('Users notify btn preview modal') }}">
            <i class="bi bi-window" aria-hidden="true"></i>
        </span>
        @foreach($locales as $locale)
            <button type="button"
                    class="btn btn-sm btn-outline-primary cabinet-notify-btn-preview-modal cabinet-notify-btn-lang"
                    data-event-id="{{ $row['id'] }}"
                    data-lang="{{ $locale }}"
                    title="{{ $locale === 'ru' ? __('Users notify test lang ru') : __('Users notify test lang en') }}">
                <img src="{{ asset('img/flags/'.$locale.'.png') }}" class="img-flag" alt="{{ strtoupper($locale) }}">
            </button>
        @endforeach
    </div>
@endif

@if(!empty($row['can_test_telegram']))
    <div class="cabinet-notify-lang-row mb-1">
        <span class="cabinet-notify-lang-channel" title="Telegram">
            <i class="bi bi-telegram" aria-hidden="true"></i>
        </span>
        @foreach($locales as $locale)
            <button type="button"
                    class="btn btn-sm btn-info text-white cabinet-notify-btn-test-tg cabinet-notify-btn-lang {{ empty($row['telegram_ready']) ? 'disabled' : '' }}"
                    data-event-id="{{ $row['id'] }}"
                    data-lang="{{ $locale }}"
                    @if(empty($row['telegram_ready'])) disabled @endif
                    title="{{ $locale === 'ru' ? __('Users notify test lang ru') : __('Users notify test lang en') }}">
                <img src="{{ asset('img/flags/'.$locale.'.png') }}" class="img-flag" alt="{{ strtoupper($locale) }}">
            </button>
        @endforeach
    </div>
@endif

@if(!empty($row['can_test_email']))
    <div class="cabinet-notify-lang-row">
        <span class="cabinet-notify-lang-channel" title="{{ __('Email') }}">
            <i class="bi bi-envelope" aria-hidden="true"></i>
        </span>
        @foreach($locales as $locale)
            <div class="btn-group btn-group-sm cabinet-notify-email-lang-group" role="group">
                <a href="{{ route('admin.notifications.preview.email', ['eventId' => $row['id'], 'lang' => $locale]) }}"
                   class="btn btn-outline-cabinet-email cabinet-notify-btn-preview-email cabinet-notify-btn-lang"
                   target="_blank"
                   rel="noopener"
                   title="{{ __('Users notify btn preview email') }} · {{ $locale === 'ru' ? __('Users notify test lang ru') : __('Users notify test lang en') }}">
                    <img src="{{ asset('img/flags/'.$locale.'.png') }}" class="img-flag" alt="{{ strtoupper($locale) }}">
                </a>
                <button type="button"
                        class="btn btn-cabinet-email cabinet-notify-btn-test-email cabinet-notify-btn-lang"
                        data-event-id="{{ $row['id'] }}"
                        data-lang="{{ $locale }}"
                        title="{{ __('Users notify btn send email') }} · {{ $locale === 'ru' ? __('Users notify test lang ru') : __('Users notify test lang en') }}">
                    <i class="bi bi-send" aria-hidden="true"></i>
                </button>
            </div>
        @endforeach
    </div>
@endif

@if(empty($row['can_preview_modal']) && empty($row['can_test_telegram']) && empty($row['can_test_email']))
    <span class="text-secondary small">—</span>
@endif
