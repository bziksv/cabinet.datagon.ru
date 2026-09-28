@php
    $avUser = $user ?? null;
    $avName = trim((string) ($name ?? ''));
    $avInitials = collect(preg_split('/\s+/u', $avName) ?: [])
        ->filter()
        ->take(2)
        ->map(function ($p) { return mb_strtoupper(mb_substr($p, 0, 1)); })
        ->implode('') ?: '?';
@endphp
@if($avUser && !empty($avUser->image))
    <span class="cabinet-sc-note-avatar has-photo {{ $class ?? '' }}" aria-hidden="true">
        <img src="{{ $avUser->image }}" alt="" loading="lazy" decoding="async">
    </span>
@else
    <span class="cabinet-sc-note-avatar {{ $class ?? '' }}" aria-hidden="true">{{ $avInitials }}</span>
@endif
