{{-- «?» справа на кнопке; подсказка слева от всей полоски, не под иконкой --}}
@php
    $tipText = $tip ?? '';
@endphp
@if($tipText !== '')
    <span class="cabinet-sa-act__help" tabindex="0" role="button" aria-label="Подсказка: {{ $tipText }}">
        <i class="fa fa-question-circle" aria-hidden="true"></i>
    </span>
    <span class="cabinet-sa-act__popover" role="tooltip">
        {!! nl2br(e($tipText)) !!}
    </span>
@endif
