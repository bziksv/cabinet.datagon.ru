@php
    $asStaff = !empty($asStaff);
    $title = $asStaff ? __('Reply as support') : __('Your message');
    $placeholder = $asStaff ? __('Your answer to the user') : __('Add details or a follow-up question');
@endphp
<div class="card-footer border-top cabinet-support-reply">
    <h6 class="fw-semibold mb-2">
        <i class="bi bi-reply me-1"></i>{{ $title }}
    </h6>
    {!! Form::open(['route' => ['support.messages.store', $ticket], 'method' => 'POST']) !!}
    @if($asStaff)
        <input type="hidden" name="as_staff" value="1">
    @endif
    <div class="mb-3">
        {!! Form::textarea('body', old('body'), [
            'class' => 'form-control' . ($errors->has('body') ? ' is-invalid' : ''),
            'rows' => 5,
            'required' => true,
            'placeholder' => $placeholder,
        ]) !!}
        @error('body')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-send me-1"></i>{{ __('Send reply') }}
    </button>
    {!! Form::close() !!}
</div>
