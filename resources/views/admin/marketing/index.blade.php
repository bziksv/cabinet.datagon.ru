@extends('layouts.app')

@section('title', __('Site marketing'))

@section('content')
    @php
        $tTotal = (int) $taskCounts->sum();
        $tDone = (int) ($taskCounts['done'] ?? 0);
        $tActive = $tTotal - (int) ($taskCounts['postponed'] ?? 0);
        $fmt = static function ($n) { return number_format((int) $n, 0, '', ' '); };
    @endphp

    <div class="cabinet-marketing-page">
        @include('admin.marketing.partials.header', [
            'active' => 'plan',
            'actions' => '<a href="' . e(route('admin.marketing.stages.create')) . '" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('Marketing new stage')) . '</a>',
        ])

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-secondary mb-1">{{ __('Marketing summary tasks') }}</div>
                        <div class="h4 mb-2">{{ $fmt($tDone) }} <span class="text-secondary fs-6">/ {{ $fmt($tActive) }}</span></div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($taskStatuses as $code => $st)
                                <span class="badge {{ $st['badge'] }}">{{ __($st['title']) }}: {{ $fmt($taskCounts[$code] ?? 0) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @foreach(['term' => __('Marketing summary terms'), 'article' => __('Marketing summary articles')] as $type => $label)
                @php $cc = $contentCounts[$type] ?? collect(); @endphp
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-secondary mb-1">
                                <a href="{{ route('admin.marketing.content', ['type' => $type]) }}" class="link-secondary">{{ $label }}</a>
                            </div>
                            <div class="h4 mb-2">{{ $fmt($cc['published'] ?? 0) }} <span class="text-secondary fs-6">/ {{ $fmt($cc->sum()) }}</span></div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($contentStatuses as $code => $st)
                                    <a href="{{ route('admin.marketing.content', ['type' => $type, 'status' => $code]) }}" class="badge {{ $st['badge'] }} text-decoration-none">{{ __($st['title']) }}: {{ $fmt($cc[$code] ?? 0) }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @forelse($stages as $stage)
            @php $pr = $stage->progress(); @endphp
            <div class="card shadow-sm mb-3 {{ $stage->status === 'postponed' ? 'opacity-75' : '' }}" id="stage-{{ $stage->id }}">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-dark">{{ $stage->priority }}</span>
                    <h3 class="card-title h6 mb-0 fw-semibold">{{ $stage->title }}</h3>
                    @include('admin.marketing.partials.badge', ['map' => $taskStatuses, 'value' => $stage->status])
                    <div class="ms-auto d-flex align-items-center gap-2">
                        @if($pr['total'] > 0)
                            <div class="progress" style="width: 8rem; height: .5rem;" role="progressbar" aria-label="{{ __('Marketing progress') }}" aria-valuenow="{{ $pr['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success" style="width: {{ $pr['percent'] }}%"></div>
                            </div>
                            <span class="small text-secondary text-nowrap">{{ $pr['done'] }}/{{ $pr['total'] }}</span>
                        @endif
                        <a href="{{ route('admin.marketing.tasks.create', ['stage_id' => $stage->id]) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Marketing new task') }}">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">{{ __('Marketing new task') }}</span>
                        </a>
                        <a href="{{ route('admin.marketing.stages.edit', $stage->id) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}">
                            <i class="bi bi-pencil" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
                @if($stage->goal || $stage->description)
                    <div class="card-body py-2 small border-bottom">
                        @if($stage->goal)
                            <div><span class="text-secondary">{{ __('Marketing goal') }}:</span> {{ $stage->goal }}</div>
                        @endif
                        @if($stage->description)
                            <div class="text-secondary" style="white-space: pre-line;">{{ $stage->description }}</div>
                        @endif
                    </div>
                @endif
                <div class="card-body p-0">
                    @if($stage->tasks->isEmpty())
                        <div class="p-3 small text-secondary">{{ __('Marketing no tasks') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>{{ __('Marketing tasks') }}</th>
                                    <th style="width: 4rem;">{{ __('Marketing priority') }}</th>
                                    <th style="width: 11rem;">{{ __('Status') }}</th>
                                    <th style="width: 7rem;">{{ __('Marketing done at') }}</th>
                                    <th style="width: 6rem;">{{ __('Marketing commit') }}</th>
                                    <th style="width: 6rem;"></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($stage->tasks as $task)
                                    <tr class="{{ $task->status === 'done' ? 'text-body-secondary' : '' }}">
                                        <td>
                                            <div class="fw-medium">
                                                @if($task->status === 'done')<i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>@endif
                                                {{ $task->title }}
                                            </div>
                                            @if($task->description)
                                                <div class="small text-secondary" style="white-space: pre-line;">{{ $task->description }}</div>
                                            @endif
                                            @if($task->links)
                                                <div class="small mt-1">@include('admin.marketing.partials.links', ['links' => $task->links])</div>
                                            @endif
                                            @if($task->notes)
                                                <div class="small mt-1 fst-italic" style="white-space: pre-line;"><i class="bi bi-sticky me-1" aria-hidden="true"></i>{{ $task->notes }}</div>
                                            @endif
                                        </td>
                                        <td><span class="badge text-bg-light border">{{ $task->priority }}</span></td>
                                        <td>
                                            <form method="post" action="{{ route('admin.marketing.tasks.status', $task->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Status') }}">
                                                    @foreach($taskStatuses as $code => $st)
                                                        <option value="{{ $code }}" @if($task->status === $code) selected @endif>{{ __($st['title']) }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="small text-nowrap">{{ $task->done_at ? $task->done_at->format('d.m.Y') : '—' }}</td>
                                        <td class="small">
                                            @if($task->commit_ref)<code>{{ $task->commit_ref }}</code>@else — @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('admin.marketing.tasks.edit', $task->id) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <form action="{{ route('admin.marketing.tasks.destroy', $task->id) }}" method="post" class="d-inline" onsubmit='return confirm(@json(__('Marketing delete confirm')))'>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="alert alert-info">{{ __('Marketing no tasks') }}</div>
        @endforelse
    </div>
@endsection
