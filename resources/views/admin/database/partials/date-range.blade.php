@php
    /** @var array $t */
    $dateCol = $t['date_column'] ?? null;
    $method = $t['date_probe_method'] ?? null;
@endphp
@if(!empty($t['data_min']) && !empty($t['data_max']))
    <div class="db-date-range">
        <div class="fw-medium">
            {{ db_admin_date_column_label($dateCol) }}
            @if($method === 'light_pk')
                <span class="badge text-bg-info ms-1" title="{{ __('Date scan light pk hint') }}">{{ __('Date range approx badge') }}</span>
            @elseif($method === 'minmax')
                <span class="badge text-bg-secondary ms-1" title="{{ __('Date range exact badge hint') }}">{{ __('Date range exact badge') }}</span>
            @endif
        </div>
        <div>{{ __('Date range from') }} {{ db_admin_format_datetime($t['data_min']) }}</div>
        <div class="text-secondary">{{ __('Date range to') }} {{ db_admin_format_datetime($t['data_max']) }}</div>
        @if(!empty($t['data_max_extra']) && !empty($t['data_max_extra_column']))
            <div class="small mt-1">
                <span class="text-secondary">{{ db_admin_date_column_label($t['data_max_extra_column']) }}:</span>
                {{ db_admin_format_datetime($t['data_max_extra']) }}
            </div>
        @endif
    </div>
@elseif(!empty($dateCol))
    <span class="text-secondary">{{ db_admin_date_column_label($dateCol) }} — {{ __('not scanned') }}</span>
@else
    <span class="text-secondary">—</span>
@endif
@if(!empty($t['date_error']))
    <div class="text-danger small">{{ Str::limit($t['date_error'], 80) }}</div>
@endif
