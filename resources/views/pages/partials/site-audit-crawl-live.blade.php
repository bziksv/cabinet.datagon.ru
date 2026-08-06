{{-- Живой прогресс краула. Ожидает: $crawl --}}
@php
    $stClass = $crawl->statusCssClass();
    $fetchedN = (int) $crawl->pages_fetched;
    $totalN = (int) $crawl->pages_total;
    $progressPct = $totalN > 0 ? (int) round(100 * $fetchedN / $totalN) : 0;
    $unchanged = (int) (($crawl->progress_json['pages_unchanged'] ?? 0));
    $statusUrl = $statusUrl ?? route('pages.site-audit.crawl.status', $crawl->id);
@endphp
@if(! $crawl->isFinished())
    <div class="cabinet-sa-crawl-live mb-4"
         id="sa-progress-wrap"
         data-sa-status-url="{{ $statusUrl }}"
         data-sa-reload-on-finish="{{ !empty($reloadOnFinish) ? '1' : '0' }}">
        <div class="cabinet-sa-crawl-live__top">
            <span class="cabinet-sa-status cabinet-sa-status--{{ $stClass }} cabinet-sa-crawl-live__pill" id="sa-status-pill-live">
                <span id="sa-status-text">{{ $crawl->statusLabelRu() }}</span>
                <span class="cabinet-sa-crawl-live__counts" id="sa-progress-label">{{ $fetchedN }} / {{ $totalN }}</span>
            </span>
            <span class="cabinet-sa-crawl-live__pct" id="sa-progress-pct">{{ $progressPct }}%</span>
        </div>
        <div class="cabinet-sa-progress" role="progressbar"
             aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPct }}"
             aria-label="Прогресс сканирования">
            <div class="cabinet-sa-progress__bar" id="sa-progress-bar" style="width: {{ $progressPct }}%"></div>
        </div>
        <div class="cabinet-sa-crawl-live__hint" id="sa-progress-hint">
            @if($unchanged > 0)
                Без изменений: {{ $unchanged }} стр.
            @else
                Идёт обход страниц — счётчики обновляются по мере сканирования
            @endif
        </div>
    </div>
@endif
