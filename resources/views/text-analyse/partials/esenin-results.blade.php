@php $e = $esenin ?? null; @endphp
@if(!empty($e))
    <div class="card shadow-sm mb-3 cabinet-ta-esenin cabinet-ta-esenin-like" id="cabinet-ta-esenin-results">
        <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h3 class="card-title h6 mb-0">
                <i class="bi bi-feather me-1 text-primary"></i>{{ __('Esenin text check') }}
            </h3>
            @if(!empty($e['module_url']) && empty($e['error']))
                <a href="{{ $e['module_url'] }}" class="small" target="_blank" rel="noopener">{{ __('Text analyzer esenin open full') }}</a>
            @endif
        </div>
        <div class="card-body">
            @if(!empty($e['error']))
                <div class="alert alert-warning mb-0">{{ $e['message'] ?? __('Text analyzer esenin failed') }}</div>
            @else
                @php
                    $blockLabels = [
                        'risk' => __('Text analyzer esenin risk'),
                        'frequency' => __('Text analyzer esenin block repeats'),
                        'style' => __('Text analyzer esenin block style'),
                        'keywords' => __('Text analyzer esenin block queries'),
                        'formality' => __('Text analyzer esenin water'),
                        'readability' => __('Text analyzer esenin readability'),
                    ];
                    $detailsByBlock = [];
                    foreach ($e['details'] ?? [] as $row) {
                        $code = (string) ($row['block'] ?? $row['code'] ?? '');
                        if ($code !== '') {
                            $detailsByBlock[$code] = $row;
                        }
                    }
                    $scoreFor = function (string $code) use ($e, $detailsByBlock): int {
                        if ($code === 'risk') {
                            return (int) ($e['risk'] ?? 0);
                        }
                        if (isset($e['blocks'][$code]['score'])) {
                            return (int) $e['blocks'][$code]['score'];
                        }
                        if (isset($detailsByBlock[$code])) {
                            return (int) ($detailsByBlock[$code]['sum'] ?? $detailsByBlock[$code]['local_sum'] ?? 0);
                        }

                        return 0;
                    };
                    $badgeClass = function (int $score): string {
                        if ($score >= 8) {
                            return 'danger';
                        }
                        if ($score >= 5) {
                            return 'warning';
                        }

                        return 'success';
                    };
                    $navOrder = ['risk', 'frequency', 'style', 'keywords', 'formality', 'readability'];
                    $highlights = $e['highlights'] ?? [];
                    $activeHtml = $highlights['risk'] ?? ($e['highlighted_html'] ?? '');
                @endphp

                <div class="row g-3 cabinet-ta-esenin-grid" data-cabinet-ta-esenin-panel>
                    <div class="col-lg-2">
                        <div class="cabinet-esenin-score-nav">
                            @foreach($navOrder as $code)
                                @php $sc = $scoreFor($code); @endphp
                                <button type="button"
                                        class="cabinet-esenin-score-btn {{ $code === 'risk' ? 'active' : '' }}"
                                        data-esenin-tab="{{ $code }}"
                                        aria-pressed="{{ $code === 'risk' ? 'true' : 'false' }}">
                                    <span class="cabinet-esenin-score-btn__title">{{ $blockLabels[$code] ?? $code }}</span>
                                    <span class="cabinet-esenin-score-btn__value">
                                        {{ $sc }}
                                        <span class="badge text-bg-{{ $badgeClass($sc) }} cabinet-esenin-score-btn__badge">
                                            {{ $code === 'risk' ? ($e['level'] ?? '') : $sc }}
                                        </span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="cabinet-esenin-text-view card shadow-sm mb-0">
                            <div class="card-body">
                                <div class="cabinet-esenin-text-view__wrap">
                                    <div class="cabinet-esenin-legend small text-secondary mb-3">
                                        {{ __('Text analyzer esenin text legend') }}
                                    </div>
                                    <div class="cabinet-esenin-text-view__content cabinet-esenin-text-view__content--readonly"
                                         data-ta-esenin-highlight>
                                        {!! $activeHtml !== '' ? $activeHtml : nl2br(e($e['text'] ?? '')) !!}
                                    </div>
                                    @if(!empty($e['stats']))
                                        <div class="small text-secondary mt-3">
                                            {{ __('Number of characters') }}: {{ number_format((int) ($e['stats']['chars'] ?? 0), 0, ',', ' ') }}
                                            · {{ __('Number of words') }}: {{ number_format((int) ($e['stats']['words'] ?? 0), 0, ',', ' ') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <script type="application/json" id="cabinet-ta-esenin-highlights">{!! json_encode($highlights, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
                    </div>

                    <div class="col-lg-3">
                        <div class="card shadow-sm h-100 mb-0">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-2" data-ta-esenin-side-title>{{ $blockLabels['risk'] }}</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody data-ta-esenin-params>
                                        @if(isset($e['metrics']['academic_nausea']))
                                            <tr>
                                                <td>{{ __('Text analyzer esenin nausea') }}</td>
                                                <td class="text-end">{{ $e['metrics']['academic_nausea'] }}</td>
                                            </tr>
                                        @endif
                                        @if(isset($e['metrics']['wateriness']))
                                            <tr>
                                                <td>{{ __('Text analyzer esenin water') }}</td>
                                                <td class="text-end">{{ $e['metrics']['wateriness'] }}</td>
                                            </tr>
                                        @endif
                                        @if(isset($e['metrics']['readability_index']))
                                            <tr>
                                                <td>{{ __('Text analyzer esenin readability') }}</td>
                                                <td class="text-end">{{ $e['metrics']['readability_index'] }}</td>
                                            </tr>
                                        @endif
                                        @if(isset($e['metrics']['informative_share']))
                                            <tr>
                                                <td>{{ __('Text analyzer esenin informative') }}</td>
                                                <td class="text-end">{{ $e['metrics']['informative_share'] }}</td>
                                            </tr>
                                        @endif
                                        @foreach($e['details'] ?? [] as $row)
                                            <tr>
                                                <td>{{ $row['label'] ?? ($row['block'] ?? '—') }}</td>
                                                <td class="text-end">{{ $row['sum'] ?? ($row['local_sum'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
