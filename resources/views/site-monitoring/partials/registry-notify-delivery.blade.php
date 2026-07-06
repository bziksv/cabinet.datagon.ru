@php
    $mode = $row['notify_delivery_mode'] ?? 'off';
    $hint = $row['notify_delivery_hint'] ?? '';
@endphp
<div class="cabinet-sm-registry-notify" title="{{ $hint }}">
    @if($mode === 'off')
        <span class="badge rounded-pill text-bg-secondary cabinet-sm-registry-notify-badge">
            <i class="bi bi-bell-slash me-1" aria-hidden="true"></i>{{ __('Site monitoring registry notify off') }}
        </span>
    @elseif($mode === 'none')
        <span class="badge rounded-pill text-bg-light text-dark border border-warning cabinet-sm-registry-notify-badge">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('Site monitoring registry notify none') }}
        </span>
    @else
        <div class="cabinet-sm-registry-notify-channels">
            @if(!empty($row['notify_telegram']))
                <span class="badge rounded-pill cabinet-sm-registry-notify-badge cabinet-sm-registry-notify-badge--telegram">
                    <i class="bi bi-telegram me-1" aria-hidden="true"></i>TG
                </span>
            @endif
            @if(!empty($row['notify_email']))
                <span class="badge rounded-pill cabinet-sm-registry-notify-badge cabinet-sm-registry-notify-badge--email">
                    <i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ __('Email') }}
                </span>
            @endif
        </div>
    @endif
</div>
