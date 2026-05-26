@php
    $fieldClass = implode(' ', array_merge($class ?? ['form-select'], ['monitoring-options']));
    $wrapperClass = $wrapperClass ?? 'mb-3';
    $fieldId = $fieldId ?? 'monitoring_project_id';
@endphp
<div class="{{ $wrapperClass }} cabinet-bl-monitoring-field" @isset($projectId) data-project-id="{{ $projectId }}" @endisset>
    <label class="form-label" for="{{ $fieldId }}">{{ __('Backlink bind monitoring') }}</label>
    {!! Form::select('monitoring_project_id', $options, $value ?? null, [
        'class' => $fieldClass,
        'id' => $fieldId,
    ]) !!}
    <div class="form-text">{{ __('Backlink bind monitoring hint') }}</div>
</div>
