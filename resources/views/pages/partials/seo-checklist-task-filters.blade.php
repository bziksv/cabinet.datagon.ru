{{--
  Единый тулбар фильтров задач: «Мои задачи» и страница проекта.
  Правки статусов / поиска — только здесь.

  Обязательные:
    $context — 'plan' | 'show'
  Опциональные:
    $reviewCount (int)
    $showReview (bool)
    $searchId (string)
    $rootExtraClass (string)
    $rootExtraAttrs (string) — сырой HTML-атрибутный хвост
    $barExtraClass (string)
    $barTip (string|null)
    $filtersExtraClass (string)
    $leading (HtmlString|string|null) — селекты слева (проект / роль)
    $trailing (HtmlString|string|null) — кнопки справа (развернуть и т.п.)
--}}
@php
    $context = $context ?? 'plan';
    $reviewCount = (int) ($reviewCount ?? 0);
    $showReview = (bool) ($showReview ?? ($reviewCount > 0));
    $searchId = $searchId ?? ($context === 'show' ? 'cabinetScShowSearch' : 'cabinetScPlanSearch');
    $rootExtraClass = trim((string) ($rootExtraClass ?? ''));
    $rootExtraAttrs = trim((string) ($rootExtraAttrs ?? ''));
    $barExtraClass = trim((string) ($barExtraClass ?? ''));
    $barTip = $barTip ?? null;
    $filtersExtraClass = trim((string) ($filtersExtraClass ?? ''));
    $leading = $leading ?? null;
    $trailing = $trailing ?? null;

    $rootClass = trim('cabinet-sc-plan-filters cabinet-sc-plan-filters--sticky '.$rootExtraClass);
    $contextAttr = $context === 'show' ? 'data-sc-show-filters' : 'data-sc-plan-filters';
    $barClass = trim('cabinet-sc-plan-filters__bar '.$barExtraClass);
    $filtersClass = trim('cabinet-sc-filters '.($filtersExtraClass !== '' ? $filtersExtraClass : ($context === 'show' ? 'cabinet-sc-filters--status' : '')));

    $statusFilters = [
        ['key' => 'all', 'label' => __('All'), 'tip' => null],
        ['key' => 'overdue', 'label' => __('Overdue'), 'tip' => __('Overdue filter hint')],
        ['key' => 'due-soon', 'label' => __('Due soon'), 'tip' => __('Due soon filter hint')],
        ['key' => 'no-review', 'label' => __('Without review'), 'tip' => __('Without review filter hint')],
        ['key' => 'important', 'label' => __('Important'), 'tip' => __('Important filter hint')],
        ['key' => 'unread-notes', 'label' => __('Unread notes'), 'tip' => __('Unread notes filter hint')],
    ];
@endphp
<div class="{{ $rootClass }}"
     data-sc-task-filters
     {{ $contextAttr }}
     @if($rootExtraAttrs !== '') {!! $rootExtraAttrs !!} @endif>
    <div class="{{ $barClass }}"
         @if($barTip) data-tip="{{ $barTip }}" @endif>
        {!! $leading !!}

        <button type="button"
                class="cabinet-sc-plan-search__toggle"
                data-sc-search-toggle
                data-tip="{{ __('Search tasks and checklist items') }}"
                aria-label="{{ __('Search tasks and checklist items') }}"
                aria-expanded="false"
                aria-controls="{{ $searchId }}">
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>

        <div class="{{ $filtersClass }}">
            @foreach($statusFilters as $sf)
                <button type="button"
                        class="btn btn-sm btn-outline-secondary @if($sf['key'] === 'all') active @endif"
                        data-sc-status-filter="{{ $sf['key'] }}"
                        @if($sf['tip']) data-tip="{{ $sf['tip'] }}" @endif
                        aria-pressed="{{ $sf['key'] === 'all' ? 'true' : 'false' }}">{{ $sf['label'] }}</button>
            @endforeach
            @if($showReview && $reviewCount > 0)
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-sc-status-filter="review"
                        data-tip="{{ __('For review filter hint') }}"
                        aria-pressed="false">
                    {{ __('Status review') }}
                    <span class="cabinet-sc-filters__badge">{{ number_format($reviewCount, 0, '', ' ') }}</span>
                </button>
            @endif
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-sc-status-filter="done"
                    data-tip="{{ __('Completed tasks filter hint') }}"
                    aria-pressed="false">{{ __('Completed tasks') }}</button>
        </div>

        {!! $trailing !!}
    </div>
    <div class="cabinet-sc-plan-search-panel" data-sc-search-panel hidden>
        <label class="visually-hidden" for="{{ $searchId }}">{{ __('Search tasks') }}</label>
        <input type="search"
               id="{{ $searchId }}"
               class="form-control form-control-sm cabinet-sc-plan-search__input"
               data-sc-search-input
               placeholder="{{ __('Search tasks') }}…"
               autocomplete="off">
    </div>
</div>
