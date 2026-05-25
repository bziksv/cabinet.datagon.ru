@php
    $limit = $competitorModuleLimit ?? null;
@endphp
@if(!empty($limit))
    <div class="alert alert-light border cabinet-ca-limit-banner mb-3 py-2 px-3 d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-pie-chart text-primary" aria-hidden="true"></i>
        <span class="cabinet-ca-limit-banner__month">
            @if($limit['unlimited'])
                {{ __('No restrictions on this module') }}
            @elseif($limit['exhausted'])
                <span class="text-danger fw-semibold">{{ __('Your limits are exhausted this month') }}</span>
                <span class="text-muted">({{ $limit['used'] }} / {{ $limit['limit'] }})</span>
            @else
                <span class="fw-semibold">{{ __('Left') }} {{ $limit['left'] }}</span>
                <span class="text-muted">{{ __('from') }} {{ $limit['limit'] }} {{ __('limits') }}</span>
                <span class="text-muted">· {{ __('used') }} {{ $limit['used'] }}</span>
            @endif
        </span>
        @if(!$limit['unlimited'])
            <span class="text-muted cabinet-ca-limit-banner__estimate" id="cabinet-ca-tariff-estimate-wrap">
                · {{ __('It will be written off') }}
                <strong id="cabinet-ca-tariff-estimate">0</strong>
                {{ __('limits') }}
            </span>
        @endif
    </div>
@endif
