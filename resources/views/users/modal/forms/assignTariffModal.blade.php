<div class="mb-3">
    {!! Form::label('users', __('Select users'), ['class' => 'form-label']) !!}
    {!! Form::select('users[]', [], null, [
        'class' => 'form-select',
        'id' => 'select-users',
        'multiple' => 'multiple',
        'data-placeholder' => __('Start typing email (min 2 characters)'),
    ]) !!}
    @error('users') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    {!! Form::label('tariff', __('Select tariff'), ['class' => 'form-label']) !!}
    {!! Form::select('tariff', $tariffSelect['tariff'], null, ['class' => 'form-select']) !!}
    @error('tariff') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-0">
    {!! Form::label('period', __('Select period'), ['class' => 'form-label']) !!}
    {!! Form::select('period', $tariffSelect['period'], null, ['class' => 'form-select']) !!}
    @error('period') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
