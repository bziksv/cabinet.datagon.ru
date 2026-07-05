@php
    $reportTitle = ($monitoringProject->name ?? __('Project')) . ' — ' . $changeRecord->range;
    $positionsUrl = $monitoringProject
        ? route('monitoring.competitors.dynamics', ['project' => $monitoringProject->id, 'region' => $request['region'] ?? null])
        : route('monitoring.v2');
    $snapshotCount = count($resultData);
    $domainCount = 0;
    if ($snapshotCount > 0) {
        $domainCount = count(reset($resultData));
    }
@endphp
@component('component.card', [
    'title' => $reportTitle,
    'titleHtml' => '<span class="cabinet-mon-dates-result-page-title">' . e($reportTitle) . '</span>',
])
    @slot('css')
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-dates-result-page" id="cabinet-mon-dates-result-root">
        <header class="cabinet-mon-dates-result-head">
            <div class="cabinet-mon-dates-result-head__intro">
                <p class="cabinet-mon-dates-result-head__hint text-secondary small mb-2">
                    {{ __('Monitoring comp dates result hint') }}
                </p>
                <dl class="cabinet-mon-dates-result-meta mb-0">
                    <div class="cabinet-mon-dates-result-meta__row">
                        <dt>{{ __('Date range') }}</dt>
                        <dd>{{ $changeRecord->range }}</dd>
                    </div>
                    <div class="cabinet-mon-dates-result-meta__row">
                        <dt>{{ __('Region') }}</dt>
                        <dd>{{ $regionLabel }}</dd>
                    </div>
                    <div class="cabinet-mon-dates-result-meta__row">
                        <dt>{{ __('Monitoring comp dates result snapshots') }}</dt>
                        <dd>{{ number_format($snapshotCount, 0, ',', ' ') }} · {{ number_format($domainCount, 0, ',', ' ') }} {{ __('domains') }}</dd>
                    </div>
                </dl>
            </div>
            <div class="cabinet-mon-dates-result-head__actions">
                @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])
                <a href="{{ $positionsUrl }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('Monitoring comp dates result back') }}
                </a>
            </div>
        </header>

        @if($snapshotCount === 0)
            <div class="cabinet-mon-dates-result-empty alert alert-secondary mb-0" role="status">
                {{ __('There are no positions for the selected date ranges') }}
            </div>
        @else
            <div class="cabinet-mon-dates-result-toolbar">
                <div class="cabinet-mon-dates-result-toolbar__group" role="group" aria-label="{{ __('Monitoring comp dates result metric') }}">
                    <span class="cabinet-mon-dates-result-toolbar__label">{{ __('Monitoring comp dates result metric') }}</span>
                    <div class="btn-group btn-group-sm" id="dates-result-metric">
                        <button type="button" class="btn btn-outline-secondary" data-metric="avg">{{ __('Average position') }}</button>
                        <button type="button" class="btn btn-outline-secondary" data-metric="top_3">{{ __('Top') }} 3</button>
                        <button type="button" class="btn btn-outline-secondary active" data-metric="top_10">{{ __('Top') }} 10</button>
                        <button type="button" class="btn btn-outline-secondary" data-metric="top_100">{{ __('Top') }} 100</button>
                    </div>
                </div>
                <div class="cabinet-mon-dates-result-toolbar__group" role="group" aria-label="{{ __('Monitoring comp dates result view') }}">
                    <span class="cabinet-mon-dates-result-toolbar__label">{{ __('Monitoring comp dates result view') }}</span>
                    <div class="btn-group btn-group-sm" id="dates-result-view">
                        <button type="button" class="btn btn-outline-secondary active" data-view="both">{{ __('Monitoring comp dates result view both') }}</button>
                        <button type="button" class="btn btn-outline-secondary" data-view="chart">{{ __('Monitoring comp dates result view chart') }}</button>
                        <button type="button" class="btn btn-outline-secondary" data-view="table">{{ __('Monitoring comp dates result view table') }}</button>
                    </div>
                </div>
            </div>

            <section class="cabinet-mon-dates-result-chart card" id="dates-result-chart-section" aria-label="{{ __('Monitoring comp dates result chart aria') }}">
                <div class="cabinet-mon-dates-result-chart__head">
                    <h2 class="h6 mb-0" id="dates-result-chart-title">{{ __('Percentage of getting into the top') }} 10</h2>
                    <p class="text-secondary small mb-0">{{ __('Monitoring comp dates result chart hint') }}</p>
                </div>
                <div class="cabinet-mon-dates-result-chart__body">
                    <canvas id="dates-result-chart" height="120" aria-labelledby="dates-result-chart-title"></canvas>
                </div>
                <div class="cabinet-mon-dates-result-legend" id="dates-result-legend" role="group" aria-label="{{ __('Monitoring comp dates result legend aria') }}"></div>
            </section>

            <section class="cabinet-mon-dates-result-table card" id="dates-result-table-section" aria-label="{{ __('Monitoring comp dates result table aria') }}">
                <div class="cabinet-mon-dates-result-table__head">
                    <h2 class="h6 mb-0">{{ __('Monitoring comp dates result table title') }}</h2>
                    <p class="text-secondary small mb-0">{{ __('Monitoring comp dates result table hint') }}</p>
                </div>
                <div class="cabinet-mon-dates-result-table__host table-responsive">
                    <table class="table table-sm table-hover mb-0 cabinet-mon-dates-result-table" id="dates-result-table"></table>
                </div>
            </section>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/chart.js/2.7.3/chart.min.js') }}"></script>
        <script>
            window.cabinetMonDatesResultConfig = {
                ownDomain: @json($ownDomain),
                resultData: @json($resultData),
                i18n: {
                    avg: @json(__('Average position')),
                    top_3: @json(__('Percentage of getting into the top') . ' 3'),
                    top_10: @json(__('Percentage of getting into the top') . ' 10'),
                    top_100: @json(__('Percentage of getting into the top') . ' 100'),
                    date: @json(__('Date')),
                    yourSite: @json(__('Your website')),
                    leader: @json(__('Monitoring comp dates result leader')),
                    chartYAvg: @json(__('Monitoring comp dates result chart y avg')),
                    chartYTop: @json(__('Monitoring comp dates result chart y top')),
                },
                metrics: {
                    avg: { label: @json(__('Average position')), higherBetter: false },
                    top_3: { label: @json(__('Percentage of getting into the top') . ' 3'), higherBetter: true },
                    top_10: { label: @json(__('Percentage of getting into the top') . ' 10'), higherBetter: true },
                    top_100: { label: @json(__('Percentage of getting into the top') . ' 100'), higherBetter: true },
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-competitors-dates-result.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-competitors-dates-result.js')) ?: time() }}"></script>
    @endslot
@endcomponent
