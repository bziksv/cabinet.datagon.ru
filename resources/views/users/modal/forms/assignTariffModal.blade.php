<div class="form-group">
    {!! Form::label('users', __('Select users')) !!}
    {!! Form::select('users[]', [], null, ['class' => 'custom-select', 'id' => 'select-users', 'multiple' => 'multiple', 'data-placeholder' => __('Start typing email (min 2 characters)')]) !!}
    @error('users') <span class="error invalid-feedback">{{ $message }}</span> @enderror
</div>

<div class="form-group">
    {!! Form::label('tariff', __('Select tariff')) !!}
    {!! Form::select('tariff', $tariffSelect['tariff'], null, ['class' => 'custom-select']) !!}
    @error('tariff') <span class="error invalid-feedback">{{ $message }}</span> @enderror
</div>

<div class="form-group">
    {!! Form::label('period', __('Select period')) !!}
    {!! Form::select('period', $tariffSelect['period'], null, ['class' => 'custom-select']) !!}
    @error('period') <span class="error invalid-feedback">{{ $message }}</span> @enderror
</div>
