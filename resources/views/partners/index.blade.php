@component('component.card', ['title' => __('Partners')])
    @slot('css')
        @include('partners.partials.styles')
    @endslot

    <div class="cabinet-partners-page">
        @if($admin)
            @include('partners.partials.admin-nav', ['active' => 'users', 'admin' => true])
        @endif

        <div class="cabinet-partners-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-partners-lead__icon" aria-hidden="true">
                    <i class="bi bi-handshake"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Partners catalog lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Partners catalog lead hint') }}</p>
                </div>
            </div>
        </div>

        @php
            $groupsList = collect($groups);
            $partnersTotal = $groupsList->sum(fn ($g) => count($g['items'] ?? []));
        @endphp

        @if($partnersTotal === 0)
            <div class="cabinet-partners-empty">
                <i class="bi bi-inbox display-6 text-secondary opacity-50 d-block mb-2" aria-hidden="true"></i>
                <p class="mb-0">{{ __('Partners catalog empty') }}</p>
            </div>
        @else
            @foreach($groupsList as $elem)
                @php
                    $groupName = $elem['name_' . $lang] ?? $elem['name_ru'] ?? '';
                    $items = $elem['items'] ?? [];
                @endphp
                @if(count($items) > 0)
                    <section class="cabinet-partners-group" aria-labelledby="partners-group-{{ $elem['id'] ?? $loop->index }}">
                        <h2 id="partners-group-{{ $elem['id'] ?? $loop->index }}" class="cabinet-partners-group__title mb-3">
                            {{ $groupName }}
                            <span class="badge text-bg-secondary ms-1 fw-normal">{{ count($items) }}</span>
                        </h2>

                        <div class="cabinet-partners-grid">
                            @foreach($items as $item)
                                @php
                                    $itemName = $item['name_' . $lang] ?? $item['name_ru'] ?? '';
                                    $itemDesc = $item['description_' . $lang] ?? $item['description_ru'] ?? '';
                                    $shortLink = $item['short_link_' . $lang] ?? $item['short_link_ru'] ?? '';
                                @endphp
                                <article class="cabinet-partners-card">
                                    <div class="cabinet-partners-card__logo">
                                        <img src="{{ cabinet_storage_url($item['image']) }}"
                                             alt="{{ $itemName }}"
                                             loading="lazy"
                                             decoding="async">
                                    </div>
                                    <div class="cabinet-partners-card__body">
                                        <h3 class="cabinet-partners-card__title">{{ $itemName }}</h3>
                                        @if($itemDesc !== '')
                                            <p class="cabinet-partners-card__text">{{ $itemDesc }}</p>
                                        @endif
                                    </div>
                                    <div class="cabinet-partners-card__footer">
                                        <a href="{{ url('/partners/r/' . $shortLink) }}"
                                           class="btn btn-primary btn-sm cabinet-partners-card__link click_tracking"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           data-click="{{ $itemName }}">
                                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('Partners go to site') }}
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        @endif

        <div class="cabinet-partners-suggest px-4 py-3 small text-secondary">
            {{ __('If you know a good service and are ready to share it, then write to ') }}
            <a href="mailto:info@titlo.ru" class="fw-medium">info@titlo.ru</a>
        </div>
    </div>
@endcomponent
