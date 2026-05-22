<li class="list-group-item py-1">
    @if(!empty($page['external']))
        <a href="{{ $page['url'] }}" target="_blank" rel="noopener">
            {{ $page['label'] }}
            <i class="fas fa-external-link-alt ml-1"></i>
        </a>
    @else
        <a href="{{ $page['url'] }}">
            <code class="text-muted">{{ $page['url'] }}</code>
            @if(isset($page['label']) && $page['label'] !== $page['url'])
                <span class="ml-1 text-muted">— {{ $page['label'] }}</span>
            @endif
        </a>
    @endif
</li>
