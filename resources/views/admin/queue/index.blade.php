@extends('layouts.app')

@section('title', __('Queue management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-queue-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-queue-admin.css')) ?: time() }}">
@endsection

@section('content')
    @php
        $summary = $snapshot['summary'] ?? [];
        $queues = $snapshot['queues'] ?? [];
        $clusters = $snapshot['clusters'] ?? [];
        $monitoring = $snapshot['monitoring_reports'] ?? [];
        $failedJobs = $snapshot['failed_jobs'] ?? [];
        $filter = $filter ?? 'all';

        $filterQueues = array_filter($queues, static function (array $q) use ($filter) {
            if ($filter === 'warn') {
                return in_array($q['severity'] ?? '', ['warning', 'danger'], true);
            }
            if ($filter === 'reserved') {
                return ($q['reserved'] ?? 0) > 0;
            }

            return true;
        });

        $filterClusters = array_filter($clusters, static function (array $c) use ($filter) {
            if ($filter === 'stuck') {
                return ($c['status'] ?? '') === 'stuck';
            }
            if ($filter === 'orphan') {
                return ($c['status'] ?? '') === 'orphan';
            }
            if ($filter === 'abandoned') {
                return ($c['status'] ?? '') === 'abandoned';
            }
            if ($filter === 'running') {
                return ($c['status'] ?? '') === 'running';
            }

            return true;
        });
    @endphp

    <div class="cabinet-queue-admin-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-list-task text-primary" aria-hidden="true"></i>
                    <span>{{ __('Queue management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-queue-admin'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 48rem;">
                    {{ __('Queue admin intro') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form action="{{ route('admin.queue.refresh') }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Refresh snapshot') }}
                    </button>
                </form>
                <a href="{{ route('admin.queue.index', ['fresh' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                    {{ __('Hard refresh') }}
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card shadow-sm q-summary-card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('Jobs in queue') }}</div>
                        <div class="h4 mb-0">{{ number_format($summary['total_jobs'] ?? 0, 0, '.', ' ') }}</div>
                        <div class="small text-secondary">{{ __('Reserved') }}: {{ number_format($summary['reserved_jobs'] ?? 0, 0, '.', ' ') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm q-summary-card q-summary-card--warn h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('Orphan clusters') }}</div>
                        <div class="h4 mb-0 text-warning">{{ $summary['orphan_clusters'] ?? 0 }}</div>
                        <div class="small text-secondary">{{ __('Orphan cluster rows', ['rows' => number_format($summary['orphan_cluster_rows'] ?? 0, 0, '.', ' ')]) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm q-summary-card q-summary-card--danger h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('monitoring_helper backlog') }}</div>
                        <div class="h4 mb-0 text-danger">{{ number_format($summary['monitoring_helper_backlog'] ?? 0, 0, '.', ' ') }}</div>
                        <div class="small text-secondary">{{ __('Legacy dynamics jobs') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm q-summary-card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('Failed jobs (total)') }}</div>
                        <div class="h4 mb-0">{{ number_format($summary['failed_jobs_total'] ?? 0, 0, '.', ' ') }}</div>
                        <div class="small text-secondary">
                            {{ __('Oldest job') }}:
                            @if(!empty($summary['oldest_job_at']))
                                {{ \Carbon\Carbon::parse($summary['oldest_job_at'])->format('d.m.Y H:i') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="q-sticky-toolbar mb-2">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="btn-group q-filter-pills" role="group">
                    @foreach([
                        'all' => __('All sections'),
                        'warn' => __('Queues warn+'),
                        'stuck' => __('Stuck clusters'),
                        'orphan' => __('Orphan clusters'),
                        'abandoned' => __('Abandoned clusters'),
                        'running' => __('Running clusters'),
                        'reserved' => __('Reserved jobs'),
                    ] as $key => $label)
                        <a href="{{ route('admin.queue.index', ['filter' => $key]) }}"
                           class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div class="small text-secondary">
                    {{ __('Snapshot') }}: {{ $snapshot['generated_at'] ?? '—' }}
                    · {{ $snapshot['database'] ?? '' }} @ {{ $snapshot['host'] ?? '' }}
                    @if(!empty($snapshot['cluster_queue_prefix']))
                        · prefix <code>{{ $snapshot['cluster_queue_prefix'] }}</code>
                    @endif
                </div>
            </div>
        </div>

        @if(!in_array($filter, ['stuck', 'running', 'orphan'], true))
            <div class="card shadow-sm mb-4">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="bi bi-hdd-stack me-1"></i>{{ __('Queues overview') }}</span>
                    <span class="badge text-bg-secondary">{{ count($filterQueues) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('Queue') }}</th>
                            <th>{{ __('Module') }}</th>
                            <th class="text-end">{{ __('Total') }}</th>
                            <th class="text-end">{{ __('Reserved') }}</th>
                            <th class="text-end">{{ __('Available') }}</th>
                            <th>{{ __('Oldest') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($filterQueues as $q)
                            @php
                                $severity = $q['severity'] ?? 'ok';
                                $rowClass = $severity === 'danger' ? 'q-row-danger' : ($severity === 'warning' ? 'q-row-warn' : '');
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <div class="q-queue-name fw-semibold">{{ $q['queue'] }}</div>
                                    <div class="small text-secondary">{{ $q['label'] ?? '' }}</div>
                                </td>
                                <td>{{ $q['module'] ?? '—' }}</td>
                                <td class="text-end">{{ number_format($q['total'] ?? 0, 0, '.', ' ') }}</td>
                                <td class="text-end">{{ number_format($q['reserved'] ?? 0, 0, '.', ' ') }}</td>
                                <td class="text-end">{{ number_format($q['available'] ?? 0, 0, '.', ' ') }}</td>
                                <td class="text-nowrap small">
                                    @if(!empty($q['oldest_at']))
                                        {{ \Carbon\Carbon::parse($q['oldest_at'])->format('d.m.Y H:i') }}
                                        <span class="text-secondary">({{ $q['age_minutes'] }} {{ __('min') }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($severity === 'danger')
                                        <span class="badge text-bg-danger">{{ __('High load') }}</span>
                                    @elseif($severity === 'warning')
                                        <span class="badge text-bg-warning">{{ __('Elevated') }}</span>
                                    @else
                                        <span class="badge text-bg-success">{{ __('Normal') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(($q['total'] ?? 0) > 0)
                                        @php
                                            $purgeConfirm = __('Purge all jobs in queue :queue?', ['queue' => $q['queue']]);
                                        @endphp
                                        <form action="{{ route('admin.queue.purge') }}" method="post" class="d-inline"
                                              onsubmit='return confirm(@json($purgeConfirm));'>
                                            @csrf
                                            <input type="hidden" name="queue" value="{{ $q['queue'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Purge') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-secondary py-4">{{ __('No jobs in queues') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!in_array($filter, ['warn', 'reserved'], true))
            <div class="card shadow-sm mb-4">
                <div class="card-header py-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <span class="fw-semibold"><i class="bi bi-diagram-3 me-1"></i>{{ __('Cluster analyses') }}</span>
                            <span class="badge text-bg-secondary ms-1">{{ count($filterClusters) }}</span>
                        </div>
                        @if(($summary['orphan_clusters'] ?? 0) > 0)
                            @php
                                $purgeOrphansConfirm = __('Purge all orphan cluster rows?', [
                                    'progress' => $summary['orphan_clusters'] ?? 0,
                                    'rows' => number_format($summary['orphan_cluster_rows'] ?? 0, 0, '.', ' '),
                                ]);
                            @endphp
                            <form action="{{ route('admin.queue.purge-orphan-clusters') }}" method="post" class="d-inline"
                                  onsubmit='return confirm(@json($purgeOrphansConfirm));'>
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Purge orphan clusters') }}</button>
                            </form>
                        @endif
                    </div>
                    <p class="small text-secondary mb-0 mt-2">{{ __('Cluster section help') }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('Progress id') }}</th>
                            <th>{{ __('Host / user') }}</th>
                            <th class="text-end">{{ __('Phrases') }}</th>
                            <th>{{ __('Started') }}</th>
                            <th>{{ __('Last phrase') }}</th>
                            <th>{{ __('Jobs') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($filterClusters as $c)
                            @php
                                $status = $c['status'] ?? 'running';
                                $rowClass = in_array($status, ['stuck', 'orphan', 'abandoned'], true) ? 'q-row-danger' : ($status === 'running' ? 'q-row-warn' : '');
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <code class="q-progress-id" title="{{ $c['progress_id'] }}">{{ substr($c['progress_id'], 0, 10) }}…</code>
                                </td>
                                <td>
                                    <div>{{ $c['host'] ?? '—' }}</div>
                                    @if(!empty($c['user_email']))
                                        <div class="small text-secondary">{{ $c['user_email'] }}</div>
                                    @elseif(!empty($c['note']))
                                        <div class="small text-secondary">{{ $c['note'] }}</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <strong>{{ $c['phrases_done'] ?? 0 }}</strong>
                                    / {{ $c['phrases_total'] ?: '?' }}
                                    @if(($c['phrases_pending'] ?? 0) > 0)
                                        <div class="small text-secondary">+{{ $c['phrases_pending'] }} {{ __('in queue') }}</div>
                                    @endif
                                </td>
                                <td class="small text-nowrap">{{ ($c['started_at'] ?? null) ? \Carbon\Carbon::parse($c['started_at'])->format('d.m.Y H:i') : '—' }}</td>
                                <td class="small text-nowrap">{{ ($c['last_row_at'] ?? null) ? \Carbon\Carbon::parse($c['last_row_at'])->format('d.m.Y H:i') : '—' }}</td>
                                <td class="small">
                                    wait: {{ $c['wait_jobs'] ?? 0 }},
                                    child: {{ $c['child_jobs'] ?? 0 }}
                                </td>
                                <td>
                                    @if($status === 'stuck')
                                        <span class="badge text-bg-danger">{{ __('Stuck') }}</span>
                                    @elseif($status === 'orphan')
                                        <span class="badge text-bg-secondary">{{ __('Orphan') }}</span>
                                    @elseif($status === 'abandoned')
                                        <span class="badge text-bg-secondary">{{ __('Abandoned') }}</span>
                                    @elseif($status === 'running')
                                        <span class="badge text-bg-warning">{{ __('Running') }}</span>
                                    @elseif($status === 'failed')
                                        <span class="badge text-bg-secondary">{{ __('Cancelled') }}</span>
                                    @else
                                        <span class="badge text-bg-success">{{ __('Complete') }}</span>
                                    @endif
                                    @if(!empty($c['failed_message']))
                                        <div class="small text-danger mt-1">{{ Str::limit($c['failed_message'], 80) }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(in_array($status, ['stuck', 'running', 'orphan', 'abandoned'], true))
                                        @php
                                            $cancelClusterConfirm = __('Cancel cluster analysis :id?', [
                                                'id' => substr($c['progress_id'], 0, 8) . '…',
                                            ]);
                                        @endphp
                                        <form action="{{ route('admin.queue.cancel-cluster') }}" method="post" class="d-inline"
                                              onsubmit='return confirm(@json($cancelClusterConfirm));'>
                                            @csrf
                                            <input type="hidden" name="progress_id" value="{{ $c['progress_id'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-secondary py-4">{{ __('No active cluster analyses') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($filter === 'all')
            <div class="card shadow-sm mb-4">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="bi bi-graph-up-arrow me-1"></i>{{ __('Competitor dynamics reports') }}</span>
                    <span class="badge text-bg-secondary">{{ count($monitoring) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>{{ __('Project') }}</th>
                            <th>{{ __('Range') }}</th>
                            <th>{{ __('State') }}</th>
                            <th class="text-end">{{ __('Progress') }}</th>
                            <th>{{ __('Updated') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($monitoring as $r)
                            <tr class="{{ !empty($r['stale']) ? 'q-row-danger' : (in_array($r['state'], ['in queue', 'in process', 'pending'], true) ? 'q-row-warn' : '') }}">
                                <td>{{ $r['id'] }}</td>
                                <td>{{ $r['host'] ?? ('#' . ($r['project_id'] ?? '')) }}</td>
                                <td class="small">{{ $r['range'] ?? '—' }}</td>
                                <td>
                                    @if(!empty($r['stale']))
                                        <span class="badge text-bg-danger">{{ __('Stale') }}</span>
                                    @else
                                        <span class="badge text-bg-{{ in_array($r['state'], ['ready'], true) ? 'success' : (in_array($r['state'], ['fail'], true) ? 'secondary' : 'warning') }}">{{ $r['state'] }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(($r['progress_total'] ?? 0) > 0)
                                        {{ $r['progress_done'] }}/{{ $r['progress_total'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small text-nowrap">{{ $r['updated_at'] ? \Carbon\Carbon::parse($r['updated_at'])->format('d.m.Y H:i') : '—' }}</td>
                                <td class="text-end">
                                    @if(in_array($r['state'], ['in queue', 'in process', 'pending'], true) || !empty($r['stale']))
                                        @php
                                            $cancelReportConfirm = __('Cancel monitoring report #:id?', ['id' => $r['id']]);
                                        @endphp
                                        <form action="{{ route('admin.queue.cancel-monitoring-report') }}" method="post" class="d-inline"
                                              onsubmit='return confirm(@json($cancelReportConfirm));'>
                                            @csrf
                                            <input type="hidden" name="record_id" value="{{ $r['id'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary py-4">{{ __('No recent dynamics reports') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if(count($failedJobs) > 0)
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-2">
                        <span class="fw-semibold"><i class="bi bi-exclamation-octagon me-1"></i>{{ __('Recent failed jobs') }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>{{ __('Queue') }}</th>
                                <th>{{ __('Failed at') }}</th>
                                <th>{{ __('Exception') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($failedJobs as $fj)
                                <tr>
                                    <td>{{ $fj['id'] }}</td>
                                    <td><code>{{ $fj['queue'] }}</code></td>
                                    <td class="small text-nowrap">{{ $fj['failed_at'] }}</td>
                                    <td class="small">
                                        <div class="fw-semibold">{{ $fj['exception_class'] }}</div>
                                        <div class="text-secondary">{{ $fj['exception_preview'] }}</div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection
