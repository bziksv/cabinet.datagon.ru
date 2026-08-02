@component('mail::message')
# {{ __('SEO report') }}: {{ $project->domain }}

{{ __('Period') }}: **{{ $period }}**

@if($senderName)
{{ __('From') }}: {{ $senderName }}
@endif

@if($messageText)
{{ $messageText }}
@endif

@component('mail::button', ['url' => $publicUrl])
{{ __('Open report') }}
@endcomponent

@if($hasPin)
{{ __('This report is protected with a PIN. Ask your manager if you do not have it.') }}
@endif

{{ __('Or open the link:') }} [{{ $publicUrl }}]({{ $publicUrl }})

{{ \App\Support\MailNotificationFooter::unsubscribeMarkdown() }}

@endcomponent
