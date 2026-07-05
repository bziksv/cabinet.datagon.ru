<section class="cabinet-mon-comp-dynamics card" id="dateRange" aria-labelledby="comp-dynamics-title">
    <div class="cabinet-mon-comp-dynamics__head">
        <h2 class="cabinet-mon-comp-dynamics__title h6 mb-0" id="comp-dynamics-title">
            {{ __('Monitoring comp positions history title') }}
        </h2>
        <p class="cabinet-mon-comp-dynamics__hint text-secondary small mb-0">
            {{ __('Monitoring comp positions history hint') }}
        </p>
    </div>

    <div class="cabinet-mon-comp-dynamics__toolbar">
        <div class="cabinet-mon-comp-dynamics__field">
            <label class="cabinet-mon-comp-dynamics__label" for="searchEngines">{{ __('Region') }}</label>
            <select name="region" class="form-select form-select-sm" id="searchEngines">
                @foreach($searchEngines as $search)
                    <option value="{{ $search->id }}" @if($search->id == request('region')) selected @endif>
                        {{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($search) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="cabinet-mon-comp-dynamics__field">
            <label class="cabinet-mon-comp-dynamics__label" for="date-range">{{ __('Date range') }}</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                <input type="text" class="form-control" id="date-range" autocomplete="off">
            </div>
        </div>
        <div class="cabinet-mon-comp-dynamics__field cabinet-mon-comp-dynamics__field--action">
            <span class="cabinet-mon-comp-dynamics__label cabinet-mon-comp-dynamics__label--placeholder" aria-hidden="true">{{ __('Monitoring comp positions history action') }}</span>
            <button type="button" class="btn btn-primary btn-sm" id="competitors-history-positions">
                <i class="bi bi-graph-up-arrow me-1" aria-hidden="true"></i>{{ __('Monitoring comp positions history action') }}
            </button>
        </div>
    </div>

    @if(!empty($competitorDomains))
    <div class="cabinet-mon-comp-dynamics__competitors">
        <label class="cabinet-mon-comp-dynamics__label" for="comp-dynamics-competitors">{{ __('Monitoring comp dynamics competitors label') }}</label>
        <select id="comp-dynamics-competitors" class="form-select form-select-sm" multiple
                data-placeholder="{{ __('Monitoring comp dynamics competitors placeholder') }}">
            @foreach($competitorDomains as $domain)
                <option value="{{ $domain }}" selected>{{ $domain }}</option>
            @endforeach
        </select>
        <p class="cabinet-mon-comp-dynamics__competitors-hint text-secondary small mb-0">
            {{ __('Monitoring comp dynamics competitors hint', ['own' => $project->url]) }}
        </p>
    </div>
    @endif

    <div id="comp-positions-history-estimate" class="cabinet-mon-comp-dynamics-estimate alert alert-warning d-none mb-0" role="status"></div>

    <div class="cabinet-mon-comp-dynamics__body" id="history-block">
        <h3 class="cabinet-mon-comp-dynamics__reports-title h6 text-secondary mb-0">
            {{ __('Monitoring comp positions history reports title') }}
        </h3>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 cabinet-mon-comp-dynamics-table">
                <colgroup>
                    <col class="cabinet-mon-comp-dynamics-table__col-range">
                    <col class="cabinet-mon-comp-dynamics-table__col-region">
                    <col class="cabinet-mon-comp-dynamics-table__col-actions">
                </colgroup>
                <thead>
                <tr>
                    <th scope="col">{{ __('Date range') }}</th>
                    <th scope="col">{{ __('Region') }}</th>
                    <th scope="col" class="text-end">{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody id="changeDatesTbody">
                @forelse($project->dates as $result)
                    @php($requestPayload = json_decode($result['request'], true) ?: [])
                    <tr @if(in_array($result['state'], ['pending', 'in queue', 'in process'], true)) class="need-check"
                        data-id="{{ $result['id'] }}"
                        id="analyse-in-queue-{{ $result['id'] }}" @endif
                        data-range="{{ $result['range'] }}"
                        data-region="{{ $requestPayload['region'] ?? '' }}"
                        data-competitors-key="{{ \App\MonitoringCompetitor::changesDateCompetitorsSelectionKey($requestPayload['competitors'] ?? null) }}">
                        <td class="cabinet-mon-comp-dynamics-table__range">
                            <div>{{ $result['range'] }}</div>
                            <div class="cabinet-mon-comp-dynamics-table__competitors small text-secondary">
                                {{ \App\MonitoringCompetitor::changesDateCompetitorsSummary($project->url, $requestPayload['competitors'] ?? null, count($competitorDomains)) }}
                            </div>
                        </td>
                        <td class="cabinet-mon-comp-dynamics-table__region">
                            @foreach($searchEngines as $engine)
                                @if($engine['id'] == json_decode($result['request'], true)['region'])
                                    <span class="cabinet-mon-comp-dynamics-table__region-text" title="{{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($engine) }}">
                                        {{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($engine) }}
                                    </span>
                                    @break
                                @endif
                            @endforeach
                        </td>
                        <td class="text-end">
                            <div class="cabinet-mon-comp-positions-history-actions">
                            @if($result['state'] === 'ready')
                                <a class="btn btn-outline-primary btn-sm"
                                   href="{{ route('monitoring.changes.dates.result', $result['id']) }}"
                                   target="_blank" rel="noopener noreferrer">{{ __('show') }}</a>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-error-results"
                                        data-id="{{ $result['id'] }}" title="{{ __('Remove') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            @elseif($result['state'] === 'pending')
                                @php($pendingRecord = $result instanceof \App\MonitoringChangesDate ? $result : null)
                                <span class="cabinet-mon-comp-positions-history-state text-secondary">
                                    <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                                    @if($pendingRecord)
                                        {{ __('Monitoring comp dynamics pending position', [
                                            'position' => \App\MonitoringCompetitor::pendingQueuePosition($pendingRecord),
                                            'total' => \App\MonitoringCompetitor::pendingQueueTotal((int) $project->id),
                                        ]) }}
                                    @else
                                        {{ __('Monitoring comp dynamics pending waiting') }}
                                    @endif
                                </span>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-error-results"
                                        data-id="{{ $result['id'] }}" title="{{ __('Remove') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            @elseif($result['state'] === 'in queue')
                                <span class="cabinet-mon-comp-positions-history-state">
                                    @include('monitoring.partials.show.loader', ['size' => 'sm', 'label' => __('In queue')])
                                </span>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-error-results"
                                        data-id="{{ $result['id'] }}" title="{{ __('Remove') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            @elseif($result['state'] === 'in process')
                                <span class="cabinet-mon-comp-positions-history-state">
                                    @include('monitoring.partials.show.loader', ['size' => 'sm', 'label' => __('In progress')])
                                </span>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-error-results"
                                        data-id="{{ $result['id'] }}" title="{{ __('Remove') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            @else
                                <span class="text-danger">{{ __('Fail') }}</span>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-error-results"
                                        data-id="{{ $result['id'] }}" title="{{ __('Remove') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr id="empty-row">
                        <td class="text-center text-secondary" colspan="3">{{ __('Monitoring comp positions history empty') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
