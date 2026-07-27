@php
    $defaultSearchEngine = $defaultSearchEngine ?? 'yandex';
    $defaultRegion = $defaultRegion ?? \App\Support\CompetitorSearchRegions::defaultRegion($defaultSearchEngine);
    $engineId = $engineId ?? 'relevance-search-engine';
    $regionId = $regionId ?? 'relevance-region';
    $showGoogleHint = $showGoogleHint ?? true;
@endphp
<div class="form-group required">
    <label for="{{ $engineId }}">{{ __('Search Engine') }}</label>
    <select name="searchEngine" id="{{ $engineId }}" class="form-select rounded-0 js-relevance-search-engine">
        <option value="yandex" @if($defaultSearchEngine === 'yandex') selected @endif>{{ __('Yandex') }}</option>
        <option value="google" @if($defaultSearchEngine === 'google') selected @endif>{{ __('Google') }}</option>
    </select>
</div>
<div class="form-group required cabinet-relevance-region-field">
    <label for="{{ $regionId }}">{{ __('Region') }}</label>
    <select id="{{ $regionId }}"
            name="region"
            class="form-select rounded-0 js-relevance-region"
            data-placeholder="{{ __('Search city or region') }}">
        @if(!empty($defaultRegion))
            <option value="{{ $defaultRegion['id'] }}" selected>
                {{ $defaultRegion['name'] ?? $defaultRegion['text'] }}
            </option>
        @endif
    </select>
    @if($showGoogleHint)
        <p class="form-text mb-0 mt-1 d-none js-relevance-google-limits-hint">
            {{ __('Monitoring google depth limits hint') }}
        </p>
    @endif
    <p class="form-text mb-0 mt-1 cabinet-relevance-limit-cost">
        {{ __('It will be written off') }}
        <strong class="js-relevance-limit-cost">1</strong>
        {{ __('limits') }}
    </p>
</div>
