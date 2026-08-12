<form method="GET"
      action="{{ $filterAction }}"
      class="cabinet-sa-filters mb-3"
      id="sa-report-filters"
      @if(in_array(($code ?? ''), ['crawl_pages', 'crawl_images'], true)) data-sa-filters-customize data-sa-filter-code="{{ $code }}" @endif>
    @if(!empty($groupable) && !empty($viewMode))
        <input type="hidden" name="view" value="{{ $viewMode }}">
    @endif
    @if(!empty($crawlPagesSort))
        <input type="hidden" name="sort" value="{{ $crawlPagesSort }}">
        <input type="hidden" name="dir" value="{{ $crawlPagesDir ?? 'asc' }}">
    @endif
    @php
        $isCrawlPagesFilters = ($code ?? '') === 'crawl_pages';
        $isCrawlImagesFilters = ($code ?? '') === 'crawl_images';
        $isInventoryCustomize = $isCrawlPagesFilters || $isCrawlImagesFilters;
        $mainFields = [];
        $moreFields = [];
        foreach ($filterFields as $field) {
            if ($isInventoryCustomize && ($field['group'] ?? 'main') === 'more') {
                $moreFields[] = $field;
            } else {
                $mainFields[] = $field;
            }
        }
        $allFilterFields = array_merge($mainFields, $moreFields);
        $defaultMainKeys = array_values(array_map(static function ($f) {
            return (string) ($f['key'] ?? '');
        }, $mainFields));
        $fieldIsActive = static function (array $field) use ($filterValues): bool {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');
            if ($type === 'range') {
                return ($filterValues[$key . '_min'] ?? '') !== '' || ($filterValues[$key . '_max'] ?? '') !== '';
            }

            return ($filterValues[$key] ?? '') !== '';
        };
        $moreActiveCount = 0;
        foreach ($moreFields as $mf) {
            if ($fieldIsActive($mf)) {
                $moreActiveCount++;
            }
        }
        $moreActive = $moreActiveCount > 0;
    @endphp

    @if($isInventoryCustomize && $allFilterFields !== [])
        <div class="cabinet-sa-filters__head">
            <details class="cabinet-sa-filters-gear" @if($moreActive) open @endif>
                <summary class="btn btn-sm btn-outline-secondary cabinet-sa-filters-gear__btn{{ $moreActive ? ' is-active' : '' }}">
                    <i class="fa fa-cog" aria-hidden="true"></i>
                    <span>Фильтры</span>
                    @if($moreActive)
                        <span class="cabinet-sa-filters-gear__badge">{{ number_format($moreActiveCount, 0, '', ' ') }}</span>
                    @endif
                </summary>
                <div class="cabinet-sa-filters-gear__panel">
                    <div class="cabinet-sa-filters-gear__title">На основном экране</div>
                    <div class="cabinet-sa-filters-gear__pins" data-sa-filter-pins>
                        @foreach($allFilterFields as $field)
                            @php $fkey = (string) ($field['key'] ?? ''); @endphp
                            <label class="cabinet-sa-filters-gear__pin">
                                <input type="checkbox"
                                       data-sa-filter-pin="{{ $fkey }}"
                                       @if(in_array($fkey, $defaultMainKeys, true)) checked @endif>
                                <span>{{ $field['label'] ?? $fkey }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="cabinet-sa-filters-gear__title mt-2">Дополнительные поля</div>
                    <div class="cabinet-sa-filters-gear__grid" data-sa-filter-extra>
                        @foreach($moreFields as $field)
                            <div class="cabinet-sa-filters__wrap" data-sa-filter-wrap="{{ $field['key'] }}">
                                @include('pages.partials.site-audit-report-filter-field', ['field' => $field, 'filterValues' => $filterValues])
                            </div>
                        @endforeach
                    </div>
                    <div class="cabinet-sa-filters-gear__actions">
                        <button type="submit" class="btn btn-sm btn-primary">Применить</button>
                    </div>
                </div>
            </details>
        </div>
    @endif

    <div class="cabinet-sa-filters__row" data-sa-filter-main>
        @foreach($mainFields as $field)
            <div class="cabinet-sa-filters__wrap" data-sa-filter-wrap="{{ $field['key'] }}">
                @include('pages.partials.site-audit-report-filter-field', ['field' => $field, 'filterValues' => $filterValues])
            </div>
        @endforeach
        <div class="cabinet-sa-filters__actions" data-sa-filter-actions>
            <button type="submit" class="btn btn-sm btn-outline-primary">Найти</button>
            @if(!empty($filtersActive))
                @php
                    $clearQs = [];
                    if (!empty($groupable) && !empty($viewMode)) {
                        $clearQs['view'] = $viewMode;
                    }
                    if (!empty($crawlPagesSort)) {
                        $clearQs['sort'] = $crawlPagesSort;
                        $clearQs['dir'] = $crawlPagesDir ?? 'asc';
                    }
                @endphp
                <a href="{{ $filterClearUrl }}{{ $clearQs !== [] ? ('?' . http_build_query($clearQs)) : '' }}"
                   class="btn btn-sm btn-link">Сбросить</a>
            @endif
        </div>
    </div>

    @if($isInventoryCustomize)
        <script type="application/json" id="sa-filter-defaults-json">@json($defaultMainKeys)</script>
    @endif
    @if($isCrawlPagesFilters)
        @php $batchText = (string) ($filterValues['batch'] ?? ''); @endphp
        <details class="cabinet-sa-filters-batch mt-2" @if($batchText !== '') open @endif>
            <summary class="cabinet-sa-filters-batch__summary">Пакетный поиск URL</summary>
            <div class="cabinet-sa-filters-batch__body mt-2">
                <label class="cabinet-sa-filters__label" for="sa-f-batch">
                    Список страниц
                    @include('pages.partials.site-audit-tip', [
                        'tip' => "По одной ссылке (или пути) на строку.\nНапример:\nhttps://site.ru/page\n/catalog/\nНайденные покажем с данными проверки.\nНенайденные — тоже в таблице, с пометкой «нет в проверке».\nДо " . number_format(\App\Services\SiteAudit\SiteAuditBatchUrlLookup::MAX_LINES, 0, '', ' ') . " строк.",
                    ])
                </label>
                <textarea id="sa-f-batch"
                          name="q_batch"
                          class="form-control form-control-sm cabinet-sa-filters-batch__textarea"
                          rows="6"
                          placeholder="https://example.com/page-1&#10;/about/&#10;https://example.com/page-2">{{ $batchText }}</textarea>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="submit" class="btn btn-sm btn-primary">Искать список</button>
                    @if($batchText !== '')
                        @php
                            $clearBatchQs = request()->except(['q_batch', 'page']);
                        @endphp
                        <a href="{{ $filterClearUrl }}{{ $clearBatchQs !== [] ? ('?' . http_build_query($clearBatchQs)) : '' }}"
                           class="btn btn-sm btn-outline-secondary">Убрать пакетный поиск</a>
                    @endif
                </div>
            </div>
        </details>
    @endif

    <div class="cabinet-sa-filters__hint">
        @if(!empty($isRedirectReport))
            Тип «Другая страница» — редирект не из‑за слэша (/old → /new). «Только слэш» — /about → /about/.
        @elseif(($code ?? '') === 'index_count_mismatch')
            Все строки — страницы из этой проверки, которых нет в списке «в поиске» Вебмастера.
            Фильтр «Откуда в обходе» — sitemap / ссылка / посев. «С ?параметрами» — URL с query.
        @elseif($isCrawlPagesFilters)
            Галочки в шестерёнке — какие фильтры показывать в основной строке. Пакетный поиск — отдельно.
        @elseif($isCrawlImagesFilters)
            По умолчанию — группировка по URL картинки (сквозные пиксели/иконки не дублируются). Код и размер — после HEAD.
        @else
            Просто введите часть URL или текста — список сузится.
            Раскладка не важна (можно набрать «йцукен» вместо «qwerty»).
        @endif
    </div>
</form>

@if(!empty($batchStats) && ($code ?? '') === 'crawl_pages')
    <div class="cabinet-sa-batch-summary mb-3">
        <div class="cabinet-sa-batch-summary__item">
            В списке: <strong>{{ number_format((int) ($batchStats['input'] ?? 0), 0, '', ' ') }}</strong>
        </div>
        <div class="cabinet-sa-batch-summary__item cabinet-sa-batch-summary__item--ok">
            Найдено в проверке: <strong>{{ number_format((int) ($batchStats['found'] ?? 0), 0, '', ' ') }}</strong>
        </div>
        <div class="cabinet-sa-batch-summary__item cabinet-sa-batch-summary__item--miss">
            Нет в проверке: <strong>{{ number_format((int) ($batchStats['missing'] ?? 0), 0, '', ' ') }}</strong>
        </div>
    </div>
@endif
