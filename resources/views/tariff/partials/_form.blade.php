<div class="mb-3">
    {!! Form::label('tariff', __('Tariff'), ['class' => 'form-label']) !!}
    {!! Form::select('tariff', $select['tariffs'], null, ['class' => 'form-select', 'id' => 'tariff']) !!}
    @error('tariff')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="mb-0">
    {!! Form::label('period', __('Period'), ['class' => 'form-label']) !!}
    <select name="period" id="period" class="form-select">
        @foreach($select['periods'] as $key => $value)
            <option value="{{ $key }}">{{ __($value) }}</option>
        @endforeach
    </select>
    @error('period')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
