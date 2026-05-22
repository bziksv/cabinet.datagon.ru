@extends('support.layout')

@section('support-header')
    {{ __('New ticket') }}
@endsection

@section('support-header-tools')
    <a href="{{ route('support.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back to list') }}
    </a>
@endsection

@section('support-main')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">
                <i class="bi bi-pencil-square me-1"></i>{{ __('Describe your issue') }}
            </h3>
        </div>
        {!! Form::open(['route' => 'support.store', 'method' => 'POST']) !!}
        <div class="card-body">
            <div class="alert alert-light border mb-3 small mb-3">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                {{ __('We will respond in this ticket. Only support staff can post official answers.') }}
            </div>
            <div class="mb-3">
                {!! Form::label('subject', __('Subject'), ['class' => 'form-label']) !!}
                {!! Form::text('subject', old('subject'), [
                    'class' => 'form-control' . ($errors->has('subject') ? ' is-invalid' : ''),
                    'required' => true,
                    'maxlength' => 255,
                    'autofocus' => true,
                    'placeholder' => __('Short summary of the problem'),
                ]) !!}
                @error('subject')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-0">
                {!! Form::label('body', __('Message'), ['class' => 'form-label']) !!}
                {!! Form::textarea('body', old('body'), [
                    'class' => 'form-control' . ($errors->has('body') ? ' is-invalid' : ''),
                    'rows' => 8,
                    'required' => true,
                    'placeholder' => __('Describe the issue in detail'),
                ]) !!}
                @error('body')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send me-1"></i>{{ __('Send ticket') }}
            </button>
            <a href="{{ route('support.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
        {!! Form::close() !!}
    </div>
@endsection
