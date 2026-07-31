@php
    $sitesPayload = $userSites ?? [];
    $sites = $sitesPayload['sites'] ?? [];
    $archivedSites = $sitesPayload['archived'] ?? [];
    $hiddenSites = $sitesPayload['hidden'] ?? [];
    $catalog = $sitesPayload['catalog'] ?? \App\Support\HomeUserSites::moduleCatalog();
    $sitesTotal = (int) ($sitesPayload['total'] ?? 0);
    $archivedTotal = (int) ($sitesPayload['archived_total'] ?? count($archivedSites));
    $hiddenTotal = (int) ($sitesPayload['hidden_total'] ?? count($hiddenSites));
    $sitesShown = (int) ($sitesPayload['shown'] ?? count($sites));
    $modulesTotal = (int) ($sitesPayload['modules_total'] ?? 0);
    if ($modulesTotal < 1) {
        foreach ($catalog as $catalogItem) {
            if (($catalogItem['kind'] ?? 'module') === 'module') {
                $modulesTotal++;
            }
        }
    }
    $hasAnySites = $sitesTotal > 0 || $archivedTotal > 0 || $hiddenTotal > 0
        || count($sites) > 0 || count($archivedSites) > 0 || count($hiddenSites) > 0;
@endphp

<section class="cabinet-home-sites mb-4" id="cabinet-home-sites" aria-labelledby="cabinet-home-sites-title"
         data-archive-url="{{ route('home.sites.archive') }}"
         data-hide-url="{{ route('home.sites.hide') }}"
         data-restore-url="{{ route('home.sites.restore') }}"
         data-metrika-connect-url="{{ route('yandex-metrika.connect') }}"
         data-metrika-status-url="{{ route('yandex-metrika.status') }}"
         data-metrika-counters-url="{{ route('yandex-metrika.counters') }}"
         data-metrika-binding-url="{{ route('yandex-metrika.binding') }}"
         data-metrika-bind-url="{{ route('yandex-metrika.bind') }}"
         data-metrika-unbind-url="{{ route('yandex-metrika.unbind') }}"
         data-metrika-return="{{ url()->current() }}">
    <div class="cabinet-home-sites__head">
        <button type="button"
                class="cabinet-home-sites__toggle"
                id="cabinet-home-sites-toggle"
                aria-expanded="true"
                aria-controls="cabinet-home-sites-body">
            <span class="cabinet-home-sites__toggle-main">
                <i class="bi bi-globe2 text-primary" aria-hidden="true"></i>
                <span>
                    <span class="cabinet-home-sites__title" id="cabinet-home-sites-title">{{ __('Your sites') }}</span>
                    <span class="badge text-bg-light text-body-secondary border ms-1" id="cabinet-home-sites-total-badge">{{ $sitesTotal }}</span>
                </span>
            </span>
            <span class="cabinet-home-sites__toggle-meta">
                <span class="text-secondary small d-none d-sm-inline">{{ __('Sites across modules') }}</span>
                <i class="bi bi-chevron-up cabinet-home-sites__chevron" aria-hidden="true"></i>
            </span>
        </button>
    </div>

    <div class="cabinet-home-sites__body" id="cabinet-home-sites-body">
        @if(!$hasAnySites)
            <div class="cabinet-home-sites-empty text-center text-secondary py-4 px-3">
                <i class="bi bi-globe display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No sites yet') }}</p>
                <p class="small mb-0">{{ __('No sites yet hint') }}</p>
            </div>
        @else
            <div class="cabinet-home-sites-toolbar mb-3">
                <div class="cabinet-home-sites-legend small text-secondary">
                    <span class="cabinet-home-sites-legend__item">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--on" aria-hidden="true"></span>
                        {{ __('Site in module') }}
                    </span>
                    <span class="cabinet-home-sites-legend__item">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--off" aria-hidden="true"></span>
                        {{ __('Site not in module') }}
                    </span>
                    <span class="cabinet-home-sites-legend__item">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-on" aria-hidden="true"></span>
                        {{ __('Metrika synced') }}
                    </span>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-metrika="off"
                            aria-pressed="false"
                            title="{{ __('Show sites without Metrika') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-off" aria-hidden="true"></span>
                        {{ __('Metrika not synced') }}
                    </button>
                </div>
                <div class="cabinet-home-sites-toolbar__controls">
                    <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="{{ __('Sites list mode') }}">
                        <button type="button"
                                class="btn btn-outline-secondary active"
                                id="cabinet-home-sites-mode-active"
                                data-sites-mode="active">
                            {{ __('Active sites') }}
                            <span class="badge text-bg-light text-dark border ms-1" data-sites-active-count>{{ $sitesTotal }}</span>
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="cabinet-home-sites-mode-archive"
                                data-sites-mode="archive">
                            {{ __('Archive') }}
                            <span class="badge text-bg-light text-dark border ms-1" data-sites-archived-count>{{ $archivedTotal }}</span>
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="cabinet-home-sites-mode-hidden"
                                data-sites-mode="hidden">
                            {{ __('Hidden sites') }}
                            <span class="badge text-bg-light text-dark border ms-1" data-sites-hidden-count>{{ $hiddenTotal }}</span>
                        </button>
                    </div>
                    <div class="input-group input-group-sm cabinet-home-sites-toolbar__search">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search"
                               class="form-control"
                               id="cabinet-home-sites-search"
                               placeholder="{{ __('Find a site') }}…"
                               autocomplete="off"
                               aria-label="{{ __('Find a site') }}">
                    </div>
                </div>
            </div>

            @foreach([
                ['key' => 'active', 'rows' => $sites, 'mode' => 'active', 'empty' => __('No active sites'), 'icon' => 'globe'],
                ['key' => 'archive', 'rows' => $archivedSites, 'mode' => 'archive', 'empty' => __('Archive is empty'), 'icon' => 'archive'],
                ['key' => 'hidden', 'rows' => $hiddenSites, 'mode' => 'hidden', 'empty' => __('Hidden list is empty'), 'icon' => 'eye-slash'],
            ] as $panel)
                <div class="cabinet-home-sites-panel {{ $panel['key'] === 'active' ? '' : 'd-none' }}"
                     data-sites-panel="{{ $panel['key'] }}">
                    @if(count($panel['rows']) === 0)
                        <div class="cabinet-home-sites-empty text-center text-secondary py-4 px-3">
                            <i class="bi bi-{{ $panel['icon'] }} display-6 d-block mb-2 opacity-50"></i>
                            <p class="mb-0">{{ $panel['empty'] }}</p>
                        </div>
                    @else
                        <div class="cabinet-home-sites-table-wrap">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 cabinet-home-sites-table">
                                    <thead>
                                    <tr>
                                        <th scope="col" class="cabinet-home-sites-col-domain">{{ __('Domain') }}</th>
                                        @foreach($catalog as $catalogItem)
                                            @php
                                                $isIntegration = ($catalogItem['kind'] ?? 'module') === 'integration';
                                                $supportsSync = (bool) ($catalogItem['supports_sync'] ?? false);
                                            @endphp
                                            <th scope="col"
                                                class="cabinet-home-sites-col-mod text-center {{ $isIntegration ? 'cabinet-home-sites-col-mod--integration' : '' }} {{ $supportsSync ? 'cabinet-home-sites-col-mod--sync' : '' }}"
                                                title="{{ $catalogItem['title'] }}{{ $supportsSync ? (' — ' . __('Metrika sync planned')) : '' }}">
                                                <span class="cabinet-home-sites-th-label">
                                                    <span class="cabinet-home-sites-th-text">{{ $catalogItem['short'] }}</span>
                                                    @if($supportsSync)
                                                        <i class="bi bi-arrow-repeat cabinet-home-sites-sync-mark"
                                                           title="{{ __('Metrika sync planned') }}"
                                                           aria-hidden="true"></i>
                                                    @endif
                                                </span>
                                            </th>
                                        @endforeach
                                        <th scope="col" class="text-nowrap d-none d-lg-table-cell">{{ __('Last activity') }}</th>
                                        <th scope="col" class="cabinet-home-sites-col-actions text-end">{{ __('Actions') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody class="cabinet-home-sites-tbody">
                                    @foreach($panel['rows'] as $site)
                                        @php
                                            $metrikaSynced = false;
                                            foreach (($site['matrix'] ?? []) as $matrixCell) {
                                                if (($matrixCell['key'] ?? '') === 'yandex-metrika') {
                                                    $metrikaSynced = !empty($matrixCell['synced']);
                                                    break;
                                                }
                                            }
                                        @endphp
                                        <tr data-cabinet-site-domain="{{ $site['domain'] }}"
                                            data-cabinet-site-mode="{{ $panel['mode'] }}"
                                            data-metrika-synced="{{ $metrikaSynced ? '1' : '0' }}">
                                            <td class="cabinet-home-sites-domain">
                                                <a href="https://{{ $site['domain'] }}"
                                                   class="cabinet-home-sites-domain__host"
                                                   target="_blank"
                                                   rel="noopener noreferrer">{{ $site['domain'] }}</a>
                                                <span class="badge text-bg-light border ms-1">{{ $site['modules_count'] }}/{{ $site['modules_total'] ?? $modulesTotal }}</span>
                                            </td>
                                            @foreach($site['matrix'] as $cell)
                                                @php
                                                    $isIntegration = ($cell['kind'] ?? 'module') === 'integration';
                                                    $supportsSync = (bool) ($cell['supports_sync'] ?? false);
                                                    if ($isIntegration) {
                                                        $dotClass = !empty($cell['synced'])
                                                            ? 'cabinet-home-sites-dot--sync-on'
                                                            : 'cabinet-home-sites-dot--sync-off';
                                                        $statusText = !empty($cell['synced'])
                                                            ? __('Metrika synced')
                                                            : __('Metrika not synced');
                                                    } else {
                                                        $dotClass = !empty($cell['present'])
                                                            ? 'cabinet-home-sites-dot--on'
                                                            : 'cabinet-home-sites-dot--off';
                                                        $statusText = !empty($cell['present'])
                                                            ? __('Site in module')
                                                            : __('Site not in module');
                                                        if ($supportsSync && !empty($cell['present'])) {
                                                            $statusText .= !empty($cell['synced'])
                                                                ? (' · ' . __('Metrika synced'))
                                                                : (' · ' . __('Metrika sync planned'));
                                                        }
                                                    }
                                                @endphp
                                                <td class="text-center cabinet-home-sites-col-mod {{ $isIntegration ? 'cabinet-home-sites-col-mod--integration' : '' }}">
                                                    @if(($cell['key'] ?? '') === 'yandex-metrika')
                                                        <button type="button"
                                                                class="cabinet-home-sites-dot {{ $dotClass }}"
                                                                data-cabinet-metrika-dot
                                                                data-domain="{{ $site['domain'] }}"
                                                                data-counter-id="{{ (int) ($cell['counter_id'] ?? 0) }}"
                                                                data-synced="{{ !empty($cell['synced']) ? '1' : '0' }}"
                                                                title="{{ $cell['title'] }} — {{ $statusText }}{{ $cell['label'] !== '' ? (': '.$cell['label']) : '' }}">
                                                            <span class="visually-hidden">{{ $cell['short'] }}</span>
                                                        </button>
                                                    @else
                                                        <a href="{{ $cell['url'] }}"
                                                           class="cabinet-home-sites-dot {{ $dotClass }} {{ ($supportsSync && !$isIntegration && !empty($cell['present']) && empty($cell['synced'])) ? 'cabinet-home-sites-dot--sync-ready' : '' }}"
                                                           title="{{ $cell['title'] }} — {{ $statusText }}{{ $cell['label'] !== '' ? (': '.$cell['label']) : '' }}">
                                                            <span class="visually-hidden">{{ $cell['short'] }}</span>
                                                        </a>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="text-secondary small text-nowrap d-none d-lg-table-cell">
                                                {{ $site['last_at_human'] ?: '—' }}
                                            </td>
                                            <td class="text-end cabinet-home-sites-col-actions">
                                                @if($panel['mode'] === 'active')
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button"
                                                                class="btn btn-outline-secondary"
                                                                data-cabinet-site-hide
                                                                title="{{ __('Hide site') }}">
                                                            <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                                            <span class="d-none d-xxl-inline">{{ __('Hide') }}</span>
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-outline-secondary"
                                                                data-cabinet-site-archive
                                                                title="{{ __('Move to archive') }}">
                                                            <i class="bi bi-archive" aria-hidden="true"></i>
                                                            <span class="d-none d-xxl-inline">{{ __('To archive') }}</span>
                                                        </button>
                                                    </div>
                                                @else
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary"
                                                            data-cabinet-site-restore
                                                            title="{{ __('Restore site') }}">
                                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                                        <span class="d-none d-xl-inline">{{ __('Restore') }}</span>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            <div id="cabinet-home-sites-filter-empty" class="cabinet-home-sites-empty text-center text-secondary py-4 px-3 d-none">
                <i class="bi bi-search display-6 d-block mb-2 opacity-50"></i>
                <p class="mb-0">{{ __('No sites match your search.') }}</p>
            </div>

            <div class="cabinet-home-sites-pager mt-3 d-none" data-sites-pager data-page-size="50">
                <p class="cabinet-home-sites-pager__info text-secondary small mb-0" data-sites-pager-info></p>
                <div class="cabinet-home-sites-pager__nav btn-group btn-group-sm" role="group" aria-label="{{ __('Sites pagination') }}">
                    <button type="button" class="btn btn-outline-secondary" data-sites-page="prev" disabled>
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">{{ __('Previous') }}</span>
                    </button>
                    <span class="btn btn-outline-secondary disabled px-3" data-sites-pager-label>—</span>
                    <button type="button" class="btn btn-outline-secondary" data-sites-page="next" disabled>
                        <span class="d-none d-sm-inline">{{ __('Next') }}</span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            @if($sitesTotal > $sitesShown)
                <p class="text-secondary small mt-2 mb-0" data-sites-active-hint>
                    {{ __('Showing sites of', ['shown' => $sitesShown, 'total' => $sitesTotal]) }}
                    · {{ __('Sites list hard limit hint') }}
                </p>
            @endif
        @endif
    </div>

    <div class="modal fade" id="cabinet-sites-confirm-modal" tabindex="-1" aria-labelledby="cabinet-sites-confirm-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinet-sites-confirm-title">{{ __('Confirm') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-sites-confirm-text></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary" data-sites-confirm-ok>{{ __('Confirm') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cabinet-metrika-modal" tabindex="-1" aria-labelledby="cabinet-metrika-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinet-metrika-modal-title">{{ __('Yandex Metrika') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2">
                        {{ __('Choose Metrika counter for domain') }}:
                        <strong data-metrika-domain-label>—</strong>
                    </p>
                    <div data-metrika-current class="alert alert-light border py-2 px-3 small d-none mb-3"></div>
                    <div data-metrika-loading class="text-secondary small py-3 d-none">{{ __('Loading counters') }}…</div>
                    <div data-metrika-error class="alert alert-danger py-2 px-3 small d-none"></div>
                    <div data-metrika-auth class="text-center py-3 d-none">
                        <p class="mb-3">{{ __('Connect Yandex Metrika to pick a counter') }}</p>
                        <a href="#" class="btn btn-primary" data-metrika-auth-link>
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            {{ __('Authorize Yandex Metrika') }}
                        </a>
                    </div>
                    <div data-metrika-search-wrap class="mb-2 d-none">
                        <input type="search"
                               class="form-control form-control-sm"
                               data-metrika-search
                               placeholder="{{ __('Search by site or counter ID') }}"
                               autocomplete="off">
                    </div>
                    <div class="list-group list-group-flush border rounded" data-metrika-list style="max-height: 22rem; overflow: auto;"></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" data-metrika-unbind>
                        {{ __('Unbind counter') }}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
</section>
