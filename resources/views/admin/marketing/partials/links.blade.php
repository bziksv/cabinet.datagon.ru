{{-- Список ссылок/путей построчно: http(s) — кликабельно, остальное — code --}}
@foreach(\App\MarketingContentItem::splitLines((string) $links) as $link)
    @if(preg_match('~^https?://~i', $link))
        <a href="{{ $link }}" target="_blank" rel="noopener" class="d-inline-block me-2 text-break"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ preg_replace('~^https?://(www\.)?~i', '', $link) }}</a>
    @else
        <code class="me-2 text-break">{{ $link }}</code>
    @endif
@endforeach
