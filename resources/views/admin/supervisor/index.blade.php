@extends('layouts.app')

@section('title', __('Supervisor management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-supervisor-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-supervisor-admin.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-supervisor-admin-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-cpu text-primary" aria-hidden="true"></i>
                    <span>{{ __('Supervisor management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-supervisor-admin'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 52rem;">
                    {{ __('Supervisor admin intro') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.queue.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-list-task me-1" aria-hidden="true"></i>{{ __('Queue management') }}
                </a>
                <a href="{{ route('admin.supervisor.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Refresh') }}
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

        @if(app()->environment('local') && !in_array(request()->getHost(), ['cabinet.titlo.ru', 'www.cabinet.titlo.ru'], true))
            <div class="alert alert-info">
                <strong>{{ __('Supervisor local notice title') }}</strong>
                <p class="mb-0 small">
                    {{ __('Supervisor local notice body') }}
                    <a href="https://cabinet.titlo.ru/admin/supervisor" target="_blank" rel="noopener">cabinet.titlo.ru/admin/supervisor</a>.
                    {{ __('Supervisor local notice dev') }}
                </p>
            </div>
        @endif

        @if(!($probe['enabled'] ?? false))
            <div class="alert alert-warning">
                <strong>{{ __('Supervisor not configured') }}</strong>
                <p class="mb-2 small">{{ $probe['message'] ?? '' }}</p>
                <ul class="small mb-0">
                    <li>{{ __('Supervisor setup step env') }}</li>
                    <li>{{ __('Supervisor setup step conf', ['path' => $probe['config_hint'] ?? '']) }}</li>
                    <li>{{ __('Supervisor setup step fastpanel') }}</li>
                </ul>
            </div>
        @elseif(!($probe['ok'] ?? false))
            <div class="alert alert-danger">
                <strong>{{ __('Supervisor unavailable') }}</strong>
                <p class="mb-1 small">{{ $probe['message'] ?? '' }}</p>
                <p class="mb-0 small text-secondary">{{ __('Supervisor ctl path') }}: <code>{{ $probe['supervisorctl'] ?? '' }}</code></p>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header py-2">
                <strong>{{ __('Supervisor processes') }}</strong>
            </div>
            <div class="card-body p-0">
                @if(($probe['ok'] ?? false) && count($processes) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 cabinet-supervisor-admin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Supervisor program') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('PID') }}</th>
                                    <th>{{ __('Uptime') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($processes as $proc)
                                    @php
                                        $status = strtoupper($proc['status'] ?? '');
                                        $badge = $status === 'RUNNING' ? 'success' : ($status === 'STOPPED' ? 'secondary' : 'warning');
                                    @endphp
                                    <tr>
                                        <td class="font-monospace">{{ $proc['name'] }}</td>
                                        <td><span class="badge bg-{{ $badge }}">{{ $status }}</span></td>
                                        <td>{{ $proc['pid'] ?: '—' }}</td>
                                        <td>{{ $proc['uptime'] ?: '—' }}</td>
                                        <td class="text-end text-nowrap">
                                            @if($proc['controllable'] ?? false)
                                                @foreach(['start' => __('Supervisor action start'), 'stop' => __('Supervisor action stop'), 'restart' => __('Supervisor action restart')] as $action => $actionLabel)
                                                    <form action="{{ route('admin.supervisor.action') }}" method="post" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="program" value="{{ $proc['name'] }}">
                                                        <input type="hidden" name="action" value="{{ $action }}">
                                                        <button type="submit" class="btn btn-xs btn-outline-secondary btn-sm"
                                                                onclick="return confirm(@json(__('Supervisor confirm action', ['action' => $action, 'program' => $proc['name']])))">
                                                            {{ $actionLabel }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                                <a href="{{ route('admin.supervisor.index', ['log' => $proc['name']]) }}" class="btn btn-sm btn-link">
                                                    {{ __('Log') }}
                                                </a>
                                            @else
                                                <span class="text-secondary small">{{ __('Supervisor read only') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-secondary small p-3 mb-0">{{ __('Supervisor no processes') }}</p>
                @endif
            </div>
        </div>

        @if($logTail)
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>{{ __('Supervisor log tail') }}: {{ $logProgram }}</strong>
                    <a href="{{ route('admin.supervisor.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Close') }}</a>
                </div>
                <div class="card-body p-0">
                    @if($logTail['exists'] ?? false)
                        <pre class="cabinet-supervisor-admin-log mb-0">{{ $logTail['tail'] }}</pre>
                        <p class="small text-secondary px-3 pb-2 mb-0">{{ $logTail['path'] }}</p>
                    @else
                        <p class="text-secondary small p-3 mb-0">{{ __('Supervisor log missing') }}</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="alert alert-light border small mb-0">
            <strong>{{ __('Supervisor fastpanel note title') }}</strong>
            <p class="mb-0">{{ __('Supervisor fastpanel note body') }}</p>
        </div>
    </div>
@endsection
