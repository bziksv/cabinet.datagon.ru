@extends('layouts.app')

@php $isNew = !$stage->exists; @endphp

@section('title', ($isNew ? __('Marketing new stage') : __('Marketing edit stage')) . ' — ' . __('Site marketing'))

@section('content')
    <div class="cabinet-marketing-page">
        @include('admin.marketing.partials.header', ['active' => 'plan'])

        <div class="card shadow-sm" style="max-width: 52rem;">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ $isNew ? __('Marketing new stage') : __('Marketing edit stage') }}</h3>
            </div>
            <div class="card-body">
                @include('admin.marketing.partials.errors')
                <form method="post" action="{{ $isNew ? route('admin.marketing.stages.store') : route('admin.marketing.stages.update', $stage->id) }}">
                    @csrf
                    @unless($isNew) @method('PUT') @endunless

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-title">{{ __('Marketing title') }}</label>
                        <input type="text" name="title" id="mk-title" class="form-control" required maxlength="191" value="{{ old('title', $stage->title) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-goal">{{ __('Marketing goal') }}</label>
                        <input type="text" name="goal" id="mk-goal" class="form-control" maxlength="191" value="{{ old('goal', $stage->goal) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="mk-desc">{{ __('Description') }}</label>
                        <textarea name="description" id="mk-desc" rows="4" class="form-control">{{ old('description', $stage->description) }}</textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold" for="mk-priority">{{ __('Marketing priority') }}</label>
                            <select name="priority" id="mk-priority" class="form-select">
                                @foreach($priorities as $p)
                                    <option value="{{ $p }}" @if(old('priority', $stage->priority) === $p) selected @endif>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label fw-semibold" for="mk-status">{{ __('Status') }}</label>
                            <select name="status" id="mk-status" class="form-select">
                                @foreach($taskStatuses as $code => $st)
                                    <option value="{{ $code }}" @if(old('status', $stage->status) === $code) selected @endif>{{ __($st['title']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label fw-semibold" for="mk-slug">{{ __('Marketing slug') }}</label>
                            <input type="text" name="slug" id="mk-slug" class="form-control" maxlength="64" pattern="[a-z0-9-]+" value="{{ old('slug', $stage->slug) }}">
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('Save') }}</button>
                        <a href="{{ route('admin.marketing.index') }}{{ $isNew ? '' : '#stage-' . $stage->id }}" class="btn btn-outline-secondary">{{ __('Marketing back') }}</a>
                    </div>
                </form>

                @unless($isNew)
                    <form method="post" action="{{ route('admin.marketing.stages.destroy', $stage->id) }}" class="mt-3 pt-3 border-top" onsubmit='return confirm(@json(__('Marketing delete stage confirm')))'>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete') }}</button>
                    </form>
                @endunless
            </div>
        </div>
    </div>
@endsection
