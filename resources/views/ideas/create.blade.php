@extends('ideas.layout')

@section('ideas-content')
    <div class="mb-3">
        <a href="{{ route('ideas.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to ideas') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm cabinet-ideas-create">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
            <h1 class="h4 mb-1">
                <i class="bi bi-lightbulb text-warning me-2"></i>{{ __('Suggest an idea') }}
            </h1>
            <p class="text-secondary small mb-0">
                {{ __('Describe the improvement clearly. After moderation the idea will appear in the board and others can vote.') }}
            </p>
        </div>
        {!! Form::open(['route' => 'ideas.store', 'method' => 'POST']) !!}
        <div class="card-body px-4">
            <div class="alert alert-light border small">
                <i class="bi bi-shield-check text-primary me-1"></i>
                {{ __('Ideas are checked before publication. Spam and duplicates may be declined.') }}
            </div>
            <div class="mb-3">
                {!! Form::label('title', __('Title'), ['class' => 'form-label']) !!}
                {!! Form::text('title', old('title'), [
                    'class' => 'form-control form-control-lg' . ($errors->has('title') ? ' is-invalid' : ''),
                    'required' => true,
                    'maxlength' => 160,
                    'autofocus' => true,
                    'placeholder' => __('For example: export monitoring report to Excel'),
                ]) !!}
                @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">{{ __('From 8 to 160 characters') }}</div>
            </div>
            <div class="mb-0">
                {!! Form::label('body', __('Idea description'), ['class' => 'form-label']) !!}
                {!! Form::textarea('body', old('body'), [
                    'class' => 'form-control' . ($errors->has('body') ? ' is-invalid' : ''),
                    'rows' => 8,
                    'required' => true,
                    'maxlength' => 4000,
                    'placeholder' => __('What problem does it solve? How should it work?'),
                ]) !!}
                @error('body')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">{{ __('From 24 to 4000 characters') }}</div>
            </div>
        </div>
        <div class="card-footer bg-transparent border-0 px-4 pb-4 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send me-1"></i>{{ __('Submit for moderation') }}
            </button>
            <a href="{{ route('ideas.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
        {!! Form::close() !!}
    </div>
@endsection
