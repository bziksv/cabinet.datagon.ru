{{-- Config + Select2 + cabinet-relevance-search-engine.js for ПС/регион --}}
<script>
    window.cabinetRelevanceSe = {
        routes: { regions: @json(route('cluster.regions')) },
        defaultRegions: {
            yandex: @json(\App\Support\CompetitorSearchRegions::defaultRegion('yandex')),
            google: @json(\App\Support\CompetitorSearchRegions::defaultRegion('google')),
        },
        i18n: {
            regionPlaceholder: @json(__('Search city or region')),
            regionSearchMin: @json(__('Enter at least 1 character to search')),
            regionNotFound: @json(__('No regions found')),
            regionSearching: @json(__('Searching…')),
        },
    };
</script>
@if(empty($skipSelect2Assets))
    <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
@endif
<script src="{{ asset('js/cabinet-relevance-search-engine.js') }}?v={{ @filemtime(public_path('js/cabinet-relevance-search-engine.js')) ?: time() }}"></script>
