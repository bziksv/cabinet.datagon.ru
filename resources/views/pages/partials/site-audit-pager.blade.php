{{-- Широкая пагинация: блок в начале, вокруг текущей, блок в конце (с предпоследними). --}}
@php
    $page = max(1, (int) ($page ?? 1));
    $pages = max(1, (int) ($pages ?? 1));
    $total = isset($total) ? (int) $total : null;
    $edge = max(2, (int) ($edge ?? 5));     // сколько номеров в начале и в конце
    $window = max(1, (int) ($window ?? 2)); // соседей слева/справа от текущей

    $want = [];
    for ($p = 1; $p <= min($edge, $pages); $p++) {
        $want[$p] = true;
    }
    for ($p = max(1, $pages - $edge + 1); $p <= $pages; $p++) {
        $want[$p] = true;
    }
    for ($p = max(1, $page - $window); $p <= min($pages, $page + $window); $p++) {
        $want[$p] = true;
    }

    ksort($want, SORT_NUMERIC);
    $nums = array_keys($want);

    $items = [];
    $prev = null;
    foreach ($nums as $n) {
        if ($prev !== null && $n > $prev + 1) {
            $items[] = '…';
        }
        $items[] = $n;
        $prev = $n;
    }
@endphp
@if($pages > 1)
    <nav class="cabinet-sa-pager mt-3 d-flex flex-wrap align-items-center justify-content-between gap-2"
         title="Листать страницы списка"
         aria-label="Пагинация">
        <div class="small text-secondary">
            Стр. {{ number_format($page, 0, '', ' ') }} из {{ number_format($pages, 0, '', ' ') }}
            @if($total !== null)
                · {{ number_format($total, 0, '', ' ') }} всего
            @endif
        </div>
        <ul class="pagination pagination-sm mb-0 flex-wrap">
            <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                @if($page <= 1)
                    <span class="page-link">‹</span>
                @else
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}" rel="prev">‹</a>
                @endif
            </li>
            @foreach($items as $item)
                @if($item === '…')
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                @else
                    <li class="page-item {{ (int) $item === $page ? 'active' : '' }}">
                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => (int) $item]) }}">{{ $item }}</a>
                    </li>
                @endif
            @endforeach
            <li class="page-item {{ $page >= $pages ? 'disabled' : '' }}">
                @if($page >= $pages)
                    <span class="page-link">›</span>
                @else
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}" rel="next">›</a>
                @endif
            </li>
        </ul>
    </nav>
@endif
