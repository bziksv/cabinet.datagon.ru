@php
    $project = $report['project'] ?? [];
    $summary = $report['summary'] ?? [];
    $engines = $report['engines'] ?? [];
@endphp

@php
    $kpis = [
        ['label' => __('Words'), 'value' => $summary['words'] ?? '—'],
        ['label' => __('Position'), 'value' => isset($summary['middle']) ? number_format((float) $summary['middle'], 2, '.', '') : '—'],
        ['label' => __('TOP') . '-10', 'value' => isset($summary['top10']) ? $summary['top10'] . ($summary['diff_top10'] ?? '') : '—'],
        ['label' => __('Mastered'), 'value' => isset($summary['mastered']) ? number_format((float) $summary['mastered'], 2, ',', ' ') . ($summary['mastered_percent'] ? ' (' . $summary['mastered_percent'] . '%)' : '') : '—'],
    ];
@endphp

<div class="row g-2 mb-4">
    @foreach($kpis as $k)
        <div class="col-6 col-md-3">
            <div class="cabinet-mon-v2-public-kpi">
                <div class="cabinet-mon-v2-public-kpi__value">{{ $k['value'] }}</div>
                <div class="cabinet-mon-v2-public-kpi__label">{{ $k['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

@if(!empty($summary['snapshot_at']))
    <p class="small text-secondary mb-4">
        {{ __('Monitoring public share snapshot at') }}: {{ $summary['snapshot_at'] }}
    </p>
@endif

@if(empty($engines))
    <p class="text-secondary small">{{ __('Monitoring public share no regions') }}</p>
@else
    @foreach($engines as $engine)
        <div class="card card-outline card-success mb-3">
            <div class="card-header py-2">
                <h2 class="card-title h6 mb-0">
                    {{ ucfirst($engine['engine'] ?? '') }},
                    {{ $engine['location'] ?? '' }}
                    @if(!empty($engine['lr']))
                        [{{ $engine['lr'] }}]
                    @endif
                </h2>
                @if(!empty($engine['schedule']))
                    <div class="small text-secondary mt-1">{{ $engine['schedule'] }}</div>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0 cabinet-mon-v2-public-table">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('Update date') }}</th>
                            <th>{{ __('Position') }}</th>
                            <th>{{ __('TOP') }}-1</th>
                            <th>{{ __('TOP') }}-3</th>
                            <th>{{ __('TOP') }}-5</th>
                            <th>{{ __('TOP') }}-10</th>
                            <th>{{ __('TOP') }}-20</th>
                            <th>{{ __('TOP') }}-50</th>
                            <th>{{ __('TOP') }}-100</th>
                            <th>{{ __('Mastered') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($engine['rows'] ?? [] as $row)
                            <tr>
                                <td class="text-nowrap">
                                    {{ $row['date'] ?? '—' }}
                                    @if(!empty($row['period_label']))
                                        <br><small class="text-secondary">{{ $row['period_label'] }}</small>
                                    @endif
                                </td>
                                <td>{{ $row['middle'] ?? '—' }}</td>
                                <td @if(!empty($row['delta_vs_label'])) title="{{ $row['delta_vs_label'] }}" @endif>{{ $row['top_1'] ?? '—' }}</td>
                                <td @if(!empty($row['delta_vs_label'])) title="{{ $row['delta_vs_label'] }}" @endif>{{ $row['top_3'] ?? '—' }}</td>
                                <td @if(!empty($row['delta_vs_label'])) title="{{ $row['delta_vs_label'] }}" @endif>{{ $row['top_5'] ?? '—' }}</td>
                                <td @if(!empty($row['delta_vs_label'])) title="{{ $row['delta_vs_label'] }}" @endif>{{ $row['top_10'] ?? '—' }}</td>
                                <td @if(!empty($row['delta_vs_label'])) title="{{ $row['delta_vs_label'] }}" @endif>{{ $row['top_20'] ?? '—' }}</td>
                                <td @if(!empty($row['delta_vs_label'])) title="{{ $row['delta_vs_label'] }}" @endif>{{ $row['top_50'] ?? '—' }}</td>
                                <td @if(!empty($row['delta_vs_label'])) title="{{ $row['delta_vs_label'] }}" @endif>{{ $row['top_100'] ?? '—' }}</td>
                                <td>
                                    @if(isset($row['mastered']))
                                        {{ number_format((float) $row['mastered'], 2, ',', ' ') }}
                                        @if(!empty($row['mastered_percent']))
                                            <sup class="text-success">{{ $row['mastered_percent'] }}%</sup>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-secondary">{{ __('No data') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
@endif
