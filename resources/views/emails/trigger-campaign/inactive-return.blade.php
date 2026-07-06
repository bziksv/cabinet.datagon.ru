@component('mail::message')
@if($isTest)
@component('mail::panel')
**{{ __('Trigger campaign email test badge') }}**
@endcomponent
@endif

# {{ __('Trigger campaign email greeting', ['name' => $userName]) }}

{{ $mailIntro }}

@if(!empty($tariffContext))
@component('mail::panel')
**{{ __('Trigger campaign email tariff line', ['tariff' => $tariffContext['tariff_name'] ?? '']) }}**

@if(!empty($tariffContext['is_expired']))
{{ __('Trigger campaign email tariff expired', ['date' => $tariffContext['active_to'] ?? '', 'days' => $tariffContext['days_since_expiry'] ?? 0]) }}
@else
{{ __('Trigger campaign email tariff expires', ['date' => $tariffContext['active_to'] ?? '', 'days' => $tariffContext['days_left'] ?? 0]) }}
@endif
@endcomponent
@endif

@if(!empty($showActivationGuide))
@component('mail::panel')
**{{ __('Trigger campaign email activation guide title') }}**

1. {{ __('Trigger campaign email activation step balance') }}
2. {{ __('Trigger campaign email activation step monitoring') }}
3. {{ __('Trigger campaign email activation step backlinks') }}
@endcomponent
@endif

@if(!empty($showPromo) && !empty($promoCode))
@component('mail::panel')
## {{ __('Trigger campaign email your code') }}

**{{ $promoCode->code }}**

@if($promoCode->isPercent())
{{ __('Trigger campaign email bonus line percent', ['percent' => (int) $promoCode->bonus_value]) }}
@else
{{ __('Trigger campaign email bonus line', ['amount' => number_format((int) $promoCode->bonus_value, 0, '.', ' ')]) }}
@endif

@if($promoCode->expires_at)
{{ __('Trigger campaign email expires', ['date' => $promoCode->expires_at->locale(app()->getLocale())->isoFormat('LL')]) }}
@endif
@endcomponent
@endif

@foreach(preg_split('/\r\n|\r|\n/', (string) $mailBody) as $line)
@if(trim($line) !== '')
{{ $line }}

@endif
@endforeach

@component('mail::button', ['url' => $ctaUrl ?? url('/balance'), 'color' => 'success'])
{{ $ctaLabel ?? __('Trigger campaign email cta') }}
@endcomponent

@if(!empty($showPromo))
{{ __('Trigger campaign email footer') }}
@endif

{{ __('Trigger campaign email signoff', ['brand' => __('Mail brand name')]) }}

@if(!empty($trackingPixelUrl))
<img src="{{ $trackingPixelUrl }}" width="1" height="1" alt="" style="display:none;width:1px;height:1px;border:0;">
@endif

@if(!empty($manageNotificationsUrl))
{{ __('Mail notifications unsubscribe', ['url' => $manageNotificationsUrl]) }}
@endif
@endcomponent
