@php
    $colClass = !empty($wide) ? 'col-12' : 'col-sm-6';
@endphp
<div class="{{ $colClass }}">
    <div class="cabinet-roi-metric cabinet-roi-metric--{{ $roi['theme'] }}">
        <div class="cabinet-roi-metric__head">
            <span class="cabinet-roi-metric__code box-name" id="{{ $roi['id_name'] }}">{{ $roi['name'] }}</span>
            <span class="cabinet-roi-metric__label box-text">{{ $roi['text'] }}</span>
        </div>
        <div class="cabinet-roi-metric__body text-center">
            <span class="cabinet-roi-metric__value" id="{{ $roi['id_value'] }}"></span><span class="cabinet-roi-metric__unit">{{ $roi['type'] }}</span>
        </div>
    </div>
</div>
