<section class="cabinet-home-modules mb-4" id="cabinet-home-modules" aria-labelledby="cabinet-home-modules-title">
    <div class="cabinet-home-modules__head">
        <button type="button"
                class="cabinet-home-modules__toggle"
                id="cabinet-home-modules-toggle"
                aria-expanded="true"
                aria-controls="cabinet-home-modules-body">
            <span class="cabinet-home-modules__toggle-main">
                <i class="bi bi-columns-gap text-primary" aria-hidden="true"></i>
                <span>
                    <span class="cabinet-home-modules__title" id="cabinet-home-modules-title">{{ __('Tools and modules') }}</span>
                    <span class="badge text-bg-light text-body-secondary border ms-1">{{ count($modules) }}</span>
                </span>
            </span>
            <span class="cabinet-home-modules__toggle-meta">
                <i class="bi bi-chevron-up cabinet-home-modules__chevron" aria-hidden="true"></i>
            </span>
        </button>
    </div>

    <div class="cabinet-home-modules__body" id="cabinet-home-modules-body">
        @if(count($modules) === 0)
            <div class="cabinet-home-empty text-center text-secondary py-5 px-3">
                <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                <p class="mb-0">{{ __('No modules are available for your account.') }}</p>
            </div>
        @else
            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                <div class="input-group input-group-sm cabinet-home-cards-v2-search" style="max-width: 18rem;">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search"
                           class="form-control"
                           id="cabinet-home-module-search"
                           placeholder="{{ __('Find a module') }}…"
                           autocomplete="off"
                           aria-label="{{ __('Find a module') }}">
                </div>
            </div>

            <div class="row g-3" id="cabinet-home-modules-grid">
                @foreach($modules as $module)
                    @php
                        $itemsCount = array_key_exists('items_count', $module) ? $module['items_count'] : null;
                        $itemsKind = $module['items_kind'] ?? null;
                        $hasItemsMeta = $itemsCount !== null;
                        $isEmpty = $hasItemsMeta && (int) $itemsCount === 0;
                    @endphp
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3 d-flex"
                         data-cabinet-module-title="{{ $module['title'] }}"
                         data-project-id="{{ $module['id'] }}">
                        <article class="cabinet-home-cards-v2-card flex-fill {{ $isEmpty ? 'is-empty' : '' }}"
                                 style="--cabinet-module-accent: {{ $module['color'] }};">
                            <div class="cabinet-home-cards-v2-card__glow" aria-hidden="true"></div>
                            <div class="cabinet-home-cards-v2-card__body">
                                <div class="d-flex align-items-start gap-3 mb-2">
                                    <span class="cabinet-home-cards-v2-card__icon" aria-hidden="true">
                                        {!! $module['icon'] !!}
                                    </span>
                                    <div class="min-w-0 flex-grow-1">
                                        <h3 class="cabinet-home-cards-v2-card__title">
                                            {{ $module['title'] }}
                                            @if(!empty($module['beta']))
                                                @include('partials.cabinet-module-beta-badge')
                                            @endif
                                        </h3>
                                        @if($module['external'])
                                            <span class="badge text-bg-light border">{{ __('External') }}</span>
                                        @endif
                                    </div>
                                </div>

                                @if($module['description'])
                                    <p class="cabinet-home-cards-v2-card__desc">
                                        {{ $module['description'] }}
                                    </p>
                                @endif

                                <div class="cabinet-home-cards-v2-card__meta mt-auto">
                                    @if($hasItemsMeta)
                                        @if($isEmpty)
                                            <div class="cabinet-home-cards-v2-empty" role="status">
                                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                <span>
                                                    @if($itemsKind === 'saved')
                                                        {{ __('No saved items yet') }}
                                                    @else
                                                        {{ __('No projects yet') }}
                                                    @endif
                                                </span>
                                            </div>
                                        @else
                                            <div class="cabinet-home-cards-v2-count">
                                                <span class="cabinet-home-cards-v2-count__num">{{ (int) $itemsCount }}</span>
                                                <span class="cabinet-home-cards-v2-count__label">
                                                    @if($itemsKind === 'saved')
                                                        {{ trans_choice('home.cards_v2.saved_items', (int) $itemsCount) }}
                                                    @else
                                                        {{ trans_choice('home.cards_v2.projects', (int) $itemsCount) }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    @else
                                        <div class="cabinet-home-cards-v2-count cabinet-home-cards-v2-count--muted">
                                            <span class="cabinet-home-cards-v2-count__label">{{ __('Utility tool') }}</span>
                                        </div>
                                    @endif

                                    <a href="{{ $module['link'] }}"
                                       class="cabinet-home-cards-v2-open"
                                       data-track="open_module_cards_v2"
                                       @if($module['external']) target="_blank" rel="noopener noreferrer" @endif>
                                        @if($isEmpty)
                                            {{ __('Open and start') }}
                                        @else
                                            {{ __('Open') }}
                                        @endif
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <div id="cabinet-home-modules-empty" class="cabinet-home-empty text-center text-secondary py-5 px-3 d-none">
                <i class="bi bi-search display-6 d-block mb-2 opacity-50"></i>
                <p class="mb-0">{{ __('No modules match your search.') }}</p>
            </div>
        @endif
    </div>
</section>
