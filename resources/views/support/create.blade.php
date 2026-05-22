@component('component.card', ['title' => __('New ticket')])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-support.css') }}">
    @endslot

    <div class="cabinet-support-page">
        <div class="row g-3">
            @include('support.partials.sidebar')

            <div class="col-lg-9">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-pencil-square me-1"></i>{{ __('Describe your issue') }}
                        </h3>
                    </div>
                    {!! Form::open(['route' => 'support.store', 'method' => 'POST']) !!}
                    <div class="card-body">
                        <p class="text-secondary small">
                            {{ __('We will respond in this ticket. Only support staff can post official answers.') }}
                        </p>
                        <div class="mb-3">
                            {!! Form::label('subject', __('Subject'), ['class' => 'form-label']) !!}
                            {!! Form::text('subject', old('subject'), [
                                'class' => 'form-control' . ($errors->has('subject') ? ' is-invalid' : ''),
                                'required' => true,
                                'maxlength' => 255,
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
            </div>
        </div>
    </div>
@endcomponent
