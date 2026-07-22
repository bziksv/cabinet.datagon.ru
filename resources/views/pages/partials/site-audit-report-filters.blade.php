<form method="GET" action="{{ $filterAction }}" class="cabinet-sa-filters mb-3" id="sa-report-filters">
    @if(!empty($groupable) && !empty($viewMode))
        <input type="hidden" name="view" value="{{ $viewMode }}">
    @endif
    <div class="cabinet-sa-filters__row">
        @foreach($filterFields as $field)
            <div class="cabinet-sa-filters__field">
                <label class="cabinet-sa-filters__label" for="sa-f-{{ $field['key'] }}">
                    {{ $field['label'] }}
                    @include('pages.partials.site-audit-tip', ['tip' => "Введите кусочек текста, чтобы оставить в таблице только подходящие строки.\nМожно на русской или английской раскладке — найдёт и так, и так."])
                </label>
                <input type="search"
                       class="form-control form-control-sm"
                       id="sa-f-{{ $field['key'] }}"
                       name="{{ $field['param'] }}"
                       value="{{ $filterValues[$field['key']] ?? '' }}"
                       placeholder="Найти в списке… например /catalog или 404"
                       title="Фильтр по этой колонке: оставит только строки, где есть ваш текст"
                       autocomplete="off">
            </div>
        @endforeach
        <div class="cabinet-sa-filters__actions">
            <button type="submit" class="btn btn-sm btn-outline-primary" title="Применить фильтр">Найти</button>
            @if(!empty($filtersActive))
                <a href="{{ $filterClearUrl }}{{ !empty($groupable) && !empty($viewMode) ? ('?view=' . urlencode($viewMode)) : '' }}"
                   class="btn btn-sm btn-link"
                   title="Показать весь список снова">Сбросить</a>
            @endif
        </div>
    </div>
    <div class="cabinet-sa-filters__hint">
        Просто введите часть URL или текста — список сузится.
        Раскладка не важна (можно набрать «йцукен» вместо «qwerty»).
    </div>
</form>
