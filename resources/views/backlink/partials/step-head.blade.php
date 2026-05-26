@php
    $n = $n ?? 1;
    $anchor = $anchor ?? 'cabinet-bl-step-' . $n;
    $title = $title ?? '';
    $hint = $hint ?? null;
@endphp
<header class="cabinet-bl-step__head">
    <span class="cabinet-bl-step__badge" aria-hidden="true">{{ $n }}</span>
    <div class="cabinet-bl-step__titles">
        <h3 class="cabinet-bl-step__title" id="{{ $anchor }}-label">{{ $title }}</h3>
        @if(!empty($hint))
            <p class="cabinet-bl-step__desc">{{ $hint }}</p>
        @endif
    </div>
</header>
