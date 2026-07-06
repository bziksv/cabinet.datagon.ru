@php
    $notifyHintHidden = (bool) old('notify_telegram') || (bool) old('notify_email');
    if (!old('_token')) {
        $notifyHintHidden = !($onFreeTariff ?? false) && ($defaultNotify ?? true);
    }
@endphp
<p class="form-text mb-0 cabinet-sm-notify-off-hint {{ $notifyHintHidden ? 'd-none' : '' }}"
   id="cabinet-sm-notify-off-hint">
    {{ __('Site monitoring notify off cabinet hint') }}
</p>
