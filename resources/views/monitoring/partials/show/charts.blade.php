@php
    $multiRegion = $project->searchengines->count() > 1 && empty(request('region'));
@endphp
<div class="card card-charts cabinet-mon-project-charts" data-mon-view-panel="overview">
    <div class="cabinet-mon-project-charts__intro">
        <h2 class="cabinet-mon-project-charts__title mb-1">
            @if($multiRegion)
                {{ __('Monitoring show chart title regions') }}
            @else
                {{ __('Monitoring show chart title project') }}
            @endif
        </h2>
        <p class="cabinet-mon-project-charts__hint mb-0 text-secondary">
            @if($multiRegion)
                {{ __('Monitoring show chart hint regions') }}
            @else
                {{ __('Monitoring show chart hint single') }}
            @endif
            {{ __('Monitoring show chart position axis note') }}
        </p>
    </div>

    <div class="card-header">
        <div class="card-title mb-0">
            <ul class="nav nav-pills">
                @if($multiRegion)
                    <li class="nav-item"><a class="nav-link active" href="#tab_1" data-bs-toggle="tab">{{ __('Monitoring show chart regions') }}</a></li>
                @else
                    <li class="nav-item"><a class="nav-link active" href="#tab_1" data-bs-toggle="tab">{{ __('Monitoring show chart top percent') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tab_2" data-bs-toggle="tab">{{ __('Monitoring show chart avg position') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tab_3" data-bs-toggle="tab">{{ __('Monitoring show chart distribution') }}</a></li>
                @endif
            </ul>
        </div>
        <select class="form-select form-select-sm" id="chartFilterPeriod" @if($multiRegion) hidden @endif>
            <option value="days" selected>{{ __('Monitoring show chart by days') }}</option>
            <option value="weeks">{{ __('Monitoring show chart by weeks') }}</option>
            <option value="month">{{ __('Monitoring show chart by months') }}</option>
        </select>
    </div>

    <div class="card-body position-relative">
        <div class="progress-spinner">
            @include('monitoring.partials.show.loader', ['label' => __('Monitoring show chart loading')])
        </div>

        <div class="tab-content">
            @if($multiRegion)
                <div class="tab-pane active" id="tab_1">
                    <div class="chart" style="position: relative; height: min(38vh, 320px); width: 100%">
                        <canvas id="middlePositionRegions"></canvas>
                    </div>
                </div>
            @else
                <div class="tab-pane active" id="tab_1">
                    @include('monitoring.partials.show.chart-top-presets')
                    <div class="chart" style="position: relative; height: min(38vh, 320px); width: 100%">
                        <canvas id="topPercent"></canvas>
                    </div>
                </div>
                <div class="tab-pane" id="tab_2">
                    <div class="chart" style="position: relative; height: min(38vh, 320px); width: 100%">
                        <canvas id="middlePosition"></canvas>
                    </div>
                </div>
                <div class="tab-pane" id="tab_3">
                    <div class="row g-3 cabinet-mon-distribution-row">
                        <div class="col-12" id="distributionColBase">
                            <div class="cabinet-mon-distribution__title text-center text-secondary small mb-1 d-none" id="distributionBaseTitle"></div>
                            <div class="chart" style="position: relative; height: min(38vh, 320px); width: 100%">
                                <canvas id="distributionByTop"></canvas>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 d-none" id="distributionColCompare">
                            <div class="cabinet-mon-distribution__title text-center text-secondary small mb-1" id="distributionCompareTitle"></div>
                            <div class="chart" style="position: relative; height: min(38vh, 320px); width: 100%">
                                <canvas id="distributionByTopCompare"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
