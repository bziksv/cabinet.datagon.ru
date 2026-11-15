@extends('layouts.app')

@php $isNew = !$task->exists; @endphp

@section('title', ($isNew ? __('Marketing new task') : __('Marketing edit task')) . ' — ' . __('Site marketing'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endsection

@section('content')
    <div class="cabinet-marketing-page">
        @include('admin.marketing.partials.header', ['active' => 'plan'])

        <div class="card shadow-sm" style="max-width: 52rem;">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ $isNew ? __('Marketing new task') : __('Marketing edit task') }}</h3>
            </div>
            <div class="card-body">
                @include('admin.marketing.partials.errors')
                <form method="post" action="{{ $isNew ? route('admin.marketing.tasks.store') : route('admin.marketing.tasks.update', $task->id) }}">
                    @csrf
                    @unless($isNew) @method('PUT') @endunless

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-stage">{{ __('Marketing stage') }}</label>
                        <select name="stage_id" id="mk-stage" class="form-select" required data-mk-select2>
                            @foreach($stages as $s)
                                <option value="{{ $s->id }}" @if((int) old('stage_id', $task->stage_id) === (int) $s->id) selected @endif>{{ $s->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-title">{{ __('Marketing title') }}</label>
                        <input type="text" name="title" id="mk-title" class="form-control" required maxlength="191" value="{{ old('title', $task->title) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-desc">{{ __('Description') }}</label>
                        <textarea name="description" id="mk-desc" rows="4" class="form-control">{{ old('description', $task->description) }}</textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold" for="mk-priority">{{ __('Marketing priority') }}</label>
                            <select name="priority" id="mk-priority" class="form-select">
                                @foreach($priorities as $p)
                                    <option value="{{ $p }}" @if(old('priority', $task->priority) === $p) selected @endif>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold" for="mk-status">{{ __('Status') }}</label>
                            <select name="status" id="mk-status" class="form-select">
                                @foreach($taskStatuses as $code => $st)
                                    <option value="{{ $code }}" @if(old('status', $task->status) === $code) selected @endif>{{ __($st['title']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold" for="mk-done">{{ __('Marketing done at') }}</label>
                            <input type="date" name="done_at" id="mk-done" class="form-control" value="{{ old('done_at', $task->done_at ? $task->done_at->format('Y-m-d') : '') }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold" for="mk-commit">{{ __('Marketing commit') }}</label>
                            <input type="text" name="commit_ref" id="mk-commit" class="form-control" maxlength="191" value="{{ old('commit_ref', $task->commit_ref) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-links">{{ __('Marketing links') }}</label>
                        <textarea name="links" id="mk-links" rows="3" class="form-control font-monospace small">{{ old('links', $task->links) }}</textarea>
                        <div class="form-text">{{ __('Marketing links hint') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-notes">{{ __('Marketing notes') }}</label>
                        <textarea name="notes" id="mk-notes" rows="3" class="form-control">{{ old('notes', $task->notes) }}</textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('Save') }}</button>
                        <a href="{{ route('admin.marketing.index') }}{{ $task->stage_id ? '#stage-' . $task->stage_id : '' }}" class="btn btn-outline-secondary">{{ __('Marketing back') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    @include('admin.marketing.partials.select2')
@endsection
