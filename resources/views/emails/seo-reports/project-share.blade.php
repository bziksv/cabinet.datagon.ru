@component('mail::message')
# {{ __('Reports') }}

{{ __('You have been given access to SEO report project') }}: **{{ $project->domain }}**

{{ __('Access role') }}: {{ $role === 'edit' ? __('Editor') : __('Read only') }}

@component('mail::button', ['url' => $url])
{{ __('Open project') }}
@endcomponent

{{ __('Thanks') }},<br>
{{ config('app.name') }}
@endcomponent
