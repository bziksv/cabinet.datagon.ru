@php
    $fieldValue = $values[$field['name']] ?? null;
    if (($fieldValue === null || $fieldValue === '') && isset($field['default'])) {
        $fieldValue = $field['default'];
    }
    $col = (int) ($field['col'] ?? 12);
@endphp
<div class="col-md-{{ $col }}">
    <div class="mb-3">
        <label class="form-label fw-semibold" for="mon-admin-{{ $field['name'] }}">
            {{ __($field['label_key']) }}
        </label>
        <div class="input-group input-group-sm">
            @if($field['type'] === 'number')
                {!! Form::number(
                    $field['name'],
                    $fieldValue,
                    [
                        'class' => 'form-control',
                        'id' => 'mon-admin-' . $field['name'],
                        'placeholder' => $field['placeholder'] ?? '',
                        'min' => $field['min'] ?? 0,
                        'max' => $field['max'] ?? null,
                    ]
                ) !!}
            @elseif($field['type'] === 'time')
                {!! Form::text(
                    $field['name'],
                    $fieldValue,
                    [
                        'class' => 'form-control cabinet-mon-admin-time',
                        'id' => 'mon-admin-' . $field['name'],
                        'placeholder' => $field['placeholder'] ?? '00:00',
                    ]
                ) !!}
            @elseif($field['type'] === 'textarea')
                {!! Form::textarea(
                    $field['name'],
                    $fieldValue,
                    [
                        'class' => 'form-control',
                        'id' => 'mon-admin-' . $field['name'],
                        'rows' => $field['rows'] ?? 3,
                        'placeholder' => $field['placeholder'] ?? '',
                    ]
                ) !!}
            @else
                {!! Form::text(
                    $field['name'],
                    $fieldValue,
                    [
                        'class' => 'form-control',
                        'id' => 'mon-admin-' . $field['name'],
                        'placeholder' => $field['placeholder'] ?? '',
                    ]
                ) !!}
            @endif
            <a href="{{ route('monitoring.admin.settings.delete', $field['name']) }}"
               class="input-group-text text-danger cabinet-mon-admin-reset-field"
               title="{{ __('Monitoring admin reset field') }}"
               data-confirm="{{ __('Monitoring admin reset field confirm') }}">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            </a>
        </div>
        @if(!empty($field['hint_key']))
            <div class="form-text">{{ __($field['hint_key']) }}</div>
        @endif
        @if(!empty($field['hint_detail_key']))
            <div class="form-text cabinet-mon-admin-field-detail">{{ __($field['hint_detail_key']) }}</div>
        @endif
        @if(!empty($field['cmd']))
            <div class="form-text">
                {{ __('Monitoring admin dry run cmd') }}
                <code class="user-select-all">{{ $field['cmd'] }}</code>
            </div>
        @endif
    </div>
</div>
