@php $cabinetTaPageVersion = config('cabinet-text-analyzer.version', '1.0'); @endphp
@component('component.card', [
    'title' => __('Text Analyse'),
    'titleHtml' => e(__('Text Analyse')) . view('text-analyse.partials.version-badge', ['version' => $cabinetTaPageVersion])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-text-analyzer.css') }}?v={{ @filemtime(public_path('css/cabinet-text-analyzer.css')) ?: time() }}">
    @endslot

    <div class="cabinet-text-analyzer-page">
        <p class="text-secondary small cabinet-ta-intro mb-3">
            {{ __('Word statistics, Zipf distribution, phrase analysis and word clouds for page text or URL.') }}
        </p>

        @include('text-analyse.partials.form', [
            'request' => $request ?? [],
            'url' => $url ?? null,
        ])

        @isset($response)
            @include('text-analyse.partials.results', [
                'response' => $response,
                'request' => $request ?? [],
                'publicShare' => $publicShare ?? null,
            ])
        @endisset
    </div>

    @slot('js')
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        @include('text-analyse.partials.scripts')
    @endslot
@endcomponent
