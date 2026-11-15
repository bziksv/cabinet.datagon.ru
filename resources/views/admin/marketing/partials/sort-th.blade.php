{{-- Сортируемый заголовок контент-плана. $col, $label, $sort ['sort','dir'], $filters, [$style] --}}
@php
    $isActive = ($sort['sort'] ?? '') === $col;
    $nextDir = $isActive && ($sort['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
    $params = array_filter(array_merge($filters, ['sort' => $col, 'dir' => $nextDir]), static function ($v) { return $v !== ''; });
@endphp
<th @isset($style) style="{{ $style }}" @endisset @if($isActive) aria-sort="{{ $sort['dir'] === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ route('admin.marketing.content', $params) }}" class="text-reset text-decoration-none d-inline-flex align-items-center gap-1 text-nowrap">
        <span>{{ $label }}</span>
        @if($isActive)
            <i class="bi {{ $sort['dir'] === 'asc' ? 'bi-sort-up' : 'bi-sort-down' }} text-primary" aria-hidden="true"></i>
        @else
            <i class="bi bi-arrow-down-up text-secondary opacity-50 small" aria-hidden="true"></i>
        @endif
    </a>
</th>
