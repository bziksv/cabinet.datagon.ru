<span class="query-string">
    {{ $key->query }}
</span>

@if($key->page)
    <a href="{{ $key->page }}"
       data-bs-toggle="popover"
       data-bs-title="Целевой URL"
       data-bs-html="true"
       data-bs-content="{{ view('monitoring.partials.show.popover.url', ['url' => $key->page])->render() }}">
        <span class="badge badge-light"><i class="fas fa-link"></i></span>
    </a>
@endif


