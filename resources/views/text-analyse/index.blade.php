@php $cabinetTaPageVersion = config('cabinet-text-analyzer.version', '1.0'); @endphp
@component('component.card', [
    'title' => __('Text Analyse'),
    'titleHtml' => e(__('Text Analyse')) . view('text-analyse.partials.version-badge', ['version' => $cabinetTaPageVersion])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-text-analyzer.css') }}?v={{ @filemtime(public_path('css/cabinet-text-analyzer.css')) ?: time() }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-esenin-text-check.css') }}?v={{ @filemtime(public_path('css/cabinet-esenin-text-check.css')) ?: time() }}">
    @endslot

    <div class="cabinet-text-analyzer-page">
        <p class="text-secondary small cabinet-ta-intro mb-3">
            {{ __('Word statistics, Zipf distribution, phrase analysis, uniqueness check and word clouds for page text or URL.') }}
        </p>

        @include('text-analyse.partials.form', [
            'request' => $request ?? [],
            'url' => $url ?? null,
            'canSaveUniquenessHistory' => $canSaveUniquenessHistory ?? false,
            'uniquenessLimit' => $uniquenessLimit ?? null,
            'uniquenessRemaining' => $uniquenessRemaining ?? null,
            'canCheckEsenin' => $canCheckEsenin ?? false,
            'eseninRemaining' => $eseninRemaining ?? null,
            'eseninLimit' => $eseninLimit ?? null,
            'batchMax' => $batchMax ?? 20,
        ])

        @if(!empty($canSaveUniquenessHistory) && !empty($uniquenessHistories) && count($uniquenessHistories))
            @php
                $histCount = (int) ($uniquenessHistoryCount ?? count($uniquenessHistories));
                $histLimit = (int) ($uniquenessHistoryLimit ?? 0);
            @endphp
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h3 class="card-title h6 mb-0">{{ __('Text uniqueness history title') }}</h3>
                    @if($histLimit > 0)
                        <span class="small text-muted">{{ $histCount }} / {{ $histLimit }}</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Title') }}</th>
                                <th class="text-nowrap">{{ __('Text uniqueness') }}</th>
                                <th class="text-nowrap">{{ __('Esenin text check') }}</th>
                                <th class="text-nowrap">{{ __('Number of characters') }}</th>
                                <th class="text-nowrap">{{ __('Number of words') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($uniquenessHistories as $h)
                                @php
                                    $hp = is_array($h->params) ? $h->params : [];
                                    $hChars = $hp['chars'] ?? ($hp['general']['textLength'] ?? null);
                                    $hWords = $hp['words'] ?? ($hp['general']['countWordsAll'] ?? null);
                                    $hEsenin = $hp['esenin_risk'] ?? null;
                                    $hEseninLevel = $hp['esenin_level'] ?? '';
                                    $hUniqNd = !empty($hp['no_significant_matches']);
                                    $hHadUniq = array_key_exists('had_uniqueness', $hp)
                                        ? (bool) $hp['had_uniqueness']
                                        : true; // старые записи — всегда с уникальностью
                                    $hUniqPct = $hp['uniqueness_pct'] ?? $h->uniqueness_pct;
                                @endphp
                                <tr data-id="{{ $h->id }}">
                                    <td class="text-nowrap">{{ optional($h->created_at)->format('d.m.Y H:i') }}</td>
                                    <td>{{ $h->title }}</td>
                                    <td class="text-nowrap">
                                        @if(! $hHadUniq)
                                            —
                                        @elseif($hUniqNd)
                                            {{ __('Text analyzer uniqueness nd') }}
                                        @else
                                            {{ number_format((float) $hUniqPct, 1, ',', ' ') }}%
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if($hEsenin === null || $hEsenin === '')
                                            —
                                        @else
                                            {{ (int) $hEsenin }}@if($hEseninLevel !== '') <span class="text-muted small">{{ $hEseninLevel }}</span>@endif
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $hChars !== null ? number_format((int) $hChars, 0, ',', ' ') : '—' }}</td>
                                    <td class="text-nowrap">{{ $hWords !== null ? number_format((int) $hWords, 0, ',', ' ') : '—' }}</td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-xs btn-outline-primary cabinet-ta-uniq-history-open">{{ __('Open') }}</button>
                                        <button type="button" class="btn btn-xs btn-outline-danger cabinet-ta-uniq-history-del">{{ __('Delete') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @isset($response)
            @include('text-analyse.partials.results', [
                'response' => $response,
                'request' => $request ?? [],
                'publicShare' => $publicShare ?? null,
            ])
        @endisset

        <div class="d-none" id="cabinet-ta-uniq-history-panel"></div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        @include('partials.cabinet-html-editor-ckeditor')
        @include('text-analyse.partials.scripts')
    @endslot
@endcomponent
