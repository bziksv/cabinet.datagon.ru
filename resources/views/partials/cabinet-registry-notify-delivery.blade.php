@php
    $mode = $mode ?? ($delivery['mode'] ?? 'off');
    $hint = $hint ?? '';
    $notifyTelegram = $notifyTelegram ?? ($delivery['telegram'] ?? false);
    $notifyEmail = $notifyEmail ?? ($delivery['email'] ?? false);
@endphp
<div class="cabinet-mod-registry-notify" title="{{ $hint }}">
    @if($mode === 'off')
        <span class="badge rounded-pill text-bg-secondary cabinet-mod-registry-notify-badge">
            <i class="bi bi-bell-slash me-1" aria-hidden="true"></i>{{ __('Site monitoring registry notify off') }}
        </span>
    @elseif($mode === 'none')
        <span class="badge rounded-pill text-bg-light text-dark border border-warning cabinet-mod-registry-notify-badge">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('Site monitoring registry notify none') }}
        </span>
    @else
        <div class="cabinet-mod-registry-notify-channels">
            @if($notifyTelegram)
                <span class="badge rounded-pill cabinet-mod-registry-notify-badge cabinet-mod-registry-notify-badge--telegram">
                    <i class="bi bi-telegram me-1" aria-hidden="true"></i>TG
                </span>
            @endif
            @if($notifyEmail)
                <span class="badge rounded-pill cabinet-mod-registry-notify-badge cabinet-mod-registry-notify-badge--email">
                    <i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ __('Notify toggle email') }}
                </span>
            @endif
        </div>
    @endif
</div>
