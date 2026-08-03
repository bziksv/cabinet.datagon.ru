@component('mail::message')
# {{ __('SEO report client reaction mail title') }}

**{{ $project->domain }}**  
{{ optional($report->period_from)->format('d.m.Y') }} — {{ optional($report->period_to)->format('d.m.Y') }}

**{{ __('Section') }}:** {{ $sectionTitle }}  
**{{ __('Reaction') }}:** {{ $typeLabel }}

@if(!empty($text))
> {{ $text }}
@endif

{{ __('SEO report client reaction mail next') }}

@component('mail::button', ['url' => $cabinetUrl])
{{ __('Open report in cabinet') }}
@endcomponent

@endcomponent
