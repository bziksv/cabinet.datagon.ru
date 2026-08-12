{{-- Одно поле фильтра. Ждёт: $field, $filterValues --}}
@php
    $type = $field['type'] ?? 'text';
    $key = $field['key'];
    $fmtFilterNum = static function ($v) {
        if ($v === null || $v === '') {
            return '';
        }
        if (! is_numeric($v)) {
            return (string) $v;
        }

        return number_format((int) $v, 0, '', ' ');
    };
    $selectedMulti = [];
    if ($type === 'multiselect') {
        $rawSel = (string) ($filterValues[$key] ?? '');
        $selectedMulti = $rawSel === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $rawSel))));
    }
@endphp
<div class="cabinet-sa-filters__field{{ $type === 'select' ? ' cabinet-sa-filters__field--select' : '' }}{{ $type === 'range' ? ' cabinet-sa-filters__field--range' : '' }}{{ $type === 'multiselect' ? ' cabinet-sa-filters__field--multi' : '' }}">
    <label class="cabinet-sa-filters__label" for="sa-f-{{ $key }}{{ $type === 'range' ? '-min' : '' }}">
        {{ $field['label'] }}
        @include('pages.partials.site-audit-tip', [
            'tip' => $field['tip'] ?? "Введите кусочек текста, чтобы оставить в таблице только подходящие строки.\nМожно на русской или английской раскладке — найдёт и так, и так."
        ])
    </label>
    @if($type === 'select')
        <select class="form-control form-control-sm"
                id="sa-f-{{ $key }}"
                name="{{ $field['param'] }}">
            @foreach(($field['options'] ?? []) as $optVal => $optLabel)
                <option value="{{ $optVal }}" @if(($filterValues[$key] ?? '') === (string) $optVal) selected @endif>
                    {{ $optLabel }}
                </option>
            @endforeach
        </select>
    @elseif($type === 'multiselect')
        <select class="form-control form-control-sm"
                id="sa-f-{{ $key }}"
                name="{{ $field['param'] }}[]"
                multiple
                data-sa-select2-multi
                data-placeholder="{{ $field['placeholder'] ?? 'Выберите…' }}">
            @foreach(($field['options'] ?? []) as $optVal => $optLabel)
                @if((string) $optVal === '')
                    @continue
                @endif
                <option value="{{ $optVal }}" @if(in_array((string) $optVal, $selectedMulti, true)) selected @endif>
                    {{ $optLabel }}
                </option>
            @endforeach
        </select>
    @elseif($type === 'range')
        <div class="cabinet-sa-filters__range">
            <input type="text"
                   inputmode="numeric"
                   class="form-control form-control-sm"
                   id="sa-f-{{ $key }}-min"
                   name="{{ $field['param_min'] }}"
                   value="{{ $fmtFilterNum($filterValues[$key . '_min'] ?? '') }}"
                   placeholder="от"
                   autocomplete="off">
            <span class="cabinet-sa-filters__range-sep">—</span>
            <input type="text"
                   inputmode="numeric"
                   class="form-control form-control-sm"
                   id="sa-f-{{ $key }}-max"
                   name="{{ $field['param_max'] }}"
                   value="{{ $fmtFilterNum($filterValues[$key . '_max'] ?? '') }}"
                   placeholder="до"
                   autocomplete="off">
        </div>
    @else
        <input type="search"
               class="form-control form-control-sm"
               id="sa-f-{{ $key }}"
               name="{{ $field['param'] }}"
               value="{{ $filterValues[$key] ?? '' }}"
               placeholder="Найти в списке…"
               autocomplete="off">
    @endif
</div>
