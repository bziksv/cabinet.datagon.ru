@foreach($urls as $u)
    @php
        $href = $u->url ?? '';
        $linkClass = '';
        if (!empty($page) && $href !== '') {
            $linkClass = ($href != $page) ? 'text-danger' : 'text-success';
        }
    @endphp
    <div class="cabinet-mon-url-popover-row">
        <span class="cabinet-mon-url-popover-date text-nowrap">{{ $u->created_at->format('d M Y H:i:s') }}</span>
        <a href="{{ $href }}" class="cabinet-mon-url-popover-link {{ $linkClass }}" target="_blank" rel="noopener noreferrer">{{ $href !== '' ? $href : 'Удалён' }}</a>
    </div>
@endforeach
