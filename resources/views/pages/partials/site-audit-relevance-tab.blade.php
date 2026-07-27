{{-- Посадочные мониторинга ↔ анализатор релевантности --}}
@php
    $relevanceRows = $relevanceRows ?? [];
    $relevanceRowsLazy = !empty($relevanceRowsLazy);
    $relevanceRowsUrl = $relevanceRowsUrl ?? '';
@endphp
<div class="tab-pane fade" id="sa-pane-relevance" role="tabpanel"
     data-rows-url="{{ $relevanceRowsUrl }}"
     data-rows-lazy="{{ $relevanceRowsLazy ? '1' : '0' }}">
    <h5 class="mb-2">Релевантность посадочных</h5>
    <p class="text-secondary small mb-3">
        По запросам и URL из мониторинга. Аудит сам TF не считает — подтягивает последний расчёт
        из модуля «Анализатор релевантности» или предлагает запустить проверку.
        Грубый чеклист мета — отчёт «Несоответствие запроса посадочной».
    </p>

    @if($crawl->status !== 'done')
        <div class="alert alert-light border">Доступно после завершения краула.</div>
    @else
        <div id="sa-relevance-body">
            @if($relevanceRowsLazy)
                <div class="alert alert-light border mb-0" id="sa-relevance-loading">Загрузка посадочных…</div>
            @elseif(empty($relevanceRows))
                <div class="alert alert-light border mb-0">
                    Нет посадочных из мониторинга для этого домена.
                    Добавьте URL страницы к запросам в модуле мониторинга — появятся здесь.
                </div>
            @else
                @include('pages.partials.site-audit-relevance-rows', ['relevanceRows' => $relevanceRows])
            @endif
        </div>
    @endif
</div>
