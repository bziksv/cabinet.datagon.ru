{{-- Масштаб проверки + срочность. $bucketValues — critical/other/important/warning/info. --}}
@php
    $scale = $crawlScale ?? (isset($crawl) ? $crawl->scaleStats() : ['pages' => 0, 'images' => 0]);
    $pagesN = (int) ($scale['pages'] ?? 0);
    $imagesN = (int) ($scale['images'] ?? 0);
    $clickable = !empty($bucketsClickable);
    $liveAttr = !empty($bucketsLive);
    $isPublicBuckets = !empty($isPublic) && !empty($token);
    $pagesHref = null;
    $imagesHref = null;
    if (!empty($crawl)) {
        if ($isPublicBuckets) {
            $pagesHref = route('site-audit.public.share.report', [$token, 'crawl_pages']);
            $imagesHref = route('site-audit.public.share.report', [$token, 'crawl_images']);
        } else {
            $pagesHref = route('pages.site-audit.report.show', [$crawl->id, 'crawl_pages']);
            $imagesHref = route('pages.site-audit.report.show', [$crawl->id, 'crawl_images']);
        }
    }
@endphp
<div class="cabinet-sa-buckets mb-3"@if(!empty($bucketsId)) id="{{ $bucketsId }}"@endif>
    @if($pagesHref)
        <a href="{{ $pagesHref }}" class="cabinet-sa-bucket cabinet-sa-bucket--pages cabinet-sa-bucket--link"
           aria-label="Открыть таблицу всех страниц проверки">
            <div class="cabinet-sa-bucket__label">Страниц всего</div>
            <div class="cabinet-sa-bucket__value"@if($liveAttr) data-sa-live-pages @endif>{{ number_format($pagesN, 0, '', ' ') }}</div>
        </a>
    @else
        <div class="cabinet-sa-bucket cabinet-sa-bucket--pages">
            <div class="cabinet-sa-bucket__label">Страниц всего</div>
            <div class="cabinet-sa-bucket__value"@if($liveAttr) data-sa-live-pages @endif>{{ number_format($pagesN, 0, '', ' ') }}</div>
        </div>
    @endif
    @if($imagesHref)
        <a href="{{ $imagesHref }}" class="cabinet-sa-bucket cabinet-sa-bucket--images cabinet-sa-bucket--link"
           aria-label="Открыть таблицу картинок проверки">
            <div class="cabinet-sa-bucket__label">Картинок всего</div>
            <div class="cabinet-sa-bucket__value">{{ number_format($imagesN, 0, '', ' ') }}</div>
        </a>
    @else
        <div class="cabinet-sa-bucket cabinet-sa-bucket--images">
            <div class="cabinet-sa-bucket__label">Картинок всего</div>
            <div class="cabinet-sa-bucket__value">{{ number_format($imagesN, 0, '', ' ') }}</div>
        </div>
    @endif
    @foreach($bucketLabels as $key => $label)
        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}"
             @if($clickable) data-sa-bucket-preset="{{ $key }}" @endif>
            <div class="cabinet-sa-bucket__label">{{ $label }}</div>
            <div class="cabinet-sa-bucket__value"
                 data-bucket="{{ $key }}"
                 @if($liveAttr) data-sa-live-bucket="{{ $key }}" @endif>{{ number_format((int) (($bucketValues ?? [])[$key] ?? 0), 0, '', ' ') }}</div>
        </div>
    @endforeach
</div>
