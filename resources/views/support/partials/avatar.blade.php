@php
    $name = $name ?? __('User');
    $initials = mb_strtoupper(mb_substr($name, 0, 1));
    $size = (int) ($size ?? 40);
@endphp
@if(!empty($src))
    <img src="{{ $src }}"
         alt="{{ $name }}"
         class="rounded-circle flex-shrink-0 cabinet-support-avatar"
         width="{{ $size }}"
         height="{{ $size }}">
@else
    <div class="flex-shrink-0 rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center cabinet-support-avatar cabinet-support-avatar--placeholder"
         style="width: {{ $size }}px; height: {{ $size }}px"
         aria-hidden="true">{{ $initials }}</div>
@endif
