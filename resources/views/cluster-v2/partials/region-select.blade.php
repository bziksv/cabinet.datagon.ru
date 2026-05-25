@php
    $selectedRegion = $selectedRegion ?? null;
    $selectId = $id ?? 'clv2-region';
@endphp
<select id="{{ $selectId }}"
        name="{{ $name ?? 'region' }}"
        class="cabinet-cluster-v2-region-select"
        data-placeholder="{{ __('Search city or region') }}">
    @if(!empty($selectedRegion))
        <option value="{{ $selectedRegion['id'] }}" selected>{{ $selectedRegion['name'] ?? $selectedRegion['text'] }}</option>
    @endif
</select>
