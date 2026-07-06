@component('mail::message')

@component('mail::panel')
    {{ $message }}
@endcomponent

Thanks,<br>
{{ __('Mail brand name') }}
@endcomponent
