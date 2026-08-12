{{-- Прочерк с поясняющим «?» (тёмный пузырь как у действий, не title=) --}}
@php
    $naTip = $tip ?? '';
@endphp
@if($naTip !== '')
    <span class="cabinet-sa-na">
        <span class="cabinet-sa-na__dash text-muted">—</span>
        <span class="cabinet-sa-na__help" tabindex="0" role="button" aria-label="Подсказка">
            <i class="fa fa-question-circle" aria-hidden="true"></i>
        </span>
        <span class="cabinet-sa-na__popover" role="tooltip">{!! nl2br(e($naTip)) !!}</span>
    </span>
@else
    <span class="text-muted">—</span>
@endif
