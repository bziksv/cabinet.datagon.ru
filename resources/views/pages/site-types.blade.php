@component('component.card', [
    'title' => __('Site types'),
    'titleHtml' => e(__('Site types')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-site-types'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-types.css') }}?v={{ @filemtime(public_path('css/cabinet-site-types.css')) ?: time() }}">
    @endslot

    @php
        $catalogPresets = $catalogPresets ?? [];
        $maxCatalogPresets = (int) ($maxCatalogPresets ?? config('cabinet-site-types.max_catalog_presets', 20));
    @endphp
    <div class="cabinet-st-page" id="cabinetStPage"
         data-analyze-url="{{ route('pages.site-types.analyze') }}"
         data-export-url="{{ route('pages.site-types.export') }}"
         data-history-url="{{ url('/site-types/history') }}"
         data-presets-store-url="{{ route('pages.site-types.catalog-presets.store') }}"
         data-presets-update-url="{{ url('/site-types/catalog-presets') }}"
         data-presets-destroy-url="{{ url('/site-types/catalog-presets') }}"
         data-regions-url="{{ route('competitor.analysis.regions') }}"
         data-csrf="{{ csrf_token() }}"
         data-can-save="{{ $canSaveHistory ? '1' : '0' }}"
         data-cost-unit="{{ (int) $costUnit }}"
         data-limit="{{ $limit !== null ? (int) $limit : '' }}"
         data-remaining="{{ $remaining !== null ? (int) $remaining : '' }}"
         data-history-limit="{{ $historyLimit !== null ? (int) $historyLimit : '' }}"
         data-saved-count="{{ (int) $savedCount }}"
         data-max-catalog-presets="{{ $maxCatalogPresets }}"
         data-i18n-preset-empty="{{ e(__('Site types catalog preset empty')) }}"
         data-i18n-preset-select="{{ e(__('Site types catalog preset select')) }}"
         data-i18n-preset-apply="{{ e(__('Site types catalog preset applied')) }}"
         data-i18n-preset-updated="{{ e(__('Site types catalog preset updated')) }}"
         data-i18n-preset-deleted="{{ e(__('Preset deleted')) }}"
         data-i18n-preset-name-required="{{ e(__('Enter preset name')) }}"
         data-i18n-preset-delete-confirm="{{ e(__('Site types catalog preset delete confirm')) }}"
         data-i18n-preset-update-confirm="{{ e(__('Site types catalog preset update confirm')) }}"
         data-i18n-preset-limit="{{ e(__('You have reached the maximum number of presets')) }}"
         data-categories="{{ e(json_encode(collect($categories)->map(function ($c, $k) {
             return [
                 'key' => $k,
                 'label' => $c['label'] ?? $k,
                 'short' => $c['short'] ?? $k,
                 'color' => $c['color'] ?? '#64748b',
                 'hint' => $c['hint'] ?? '',
             ];
         })->values()->all(), JSON_UNESCAPED_UNICODE)) }}">
        <script type="application/json" id="cabinetStCatalogPresetsJson">@json($catalogPresets)</script>

        <div class="cabinet-st-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-center justify-content-between flex-wrap">
                <div class="d-flex gap-3 align-items-center">
                    <span class="cabinet-st-lead__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <p class="mb-0 fw-semibold text-body">{{ __('Site types lead title') }}</p>
                </div>
                <div class="cabinet-st-cost-preview text-nowrap" id="cabinetStCostPreview"
                     data-label="{{ __('Site types cost label') }}"
                     data-unit-one="{{ __('Site types cost unit one') }}"
                     data-unit-few="{{ __('Site types cost unit few') }}"
                     data-unit-many="{{ __('Site types cost unit many') }}">
                    <span id="cabinetStCostText">{{ __('Site types cost label') }} <strong id="cabinetStCostValue">0</strong> {{ __('Site types cost unit many') }}</span>
                </div>
            </div>
        </div>

        <form id="cabinetStForm" class="px-4 pb-2" autocomplete="off">
            <div class="row">
                <div class="col-lg-7">
                    <div class="form-group">
                        <label for="cabinetStPhrases">{{ __('Site types phrases label') }}</label>
                        <textarea id="cabinetStPhrases" class="form-control" rows="10"
                                  placeholder="{{ __('Site types phrases placeholder') }}"></textarea>
                        <small class="form-text text-muted">{{ __('Site types phrases hint') }}</small>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="form-group">
                        <label class="d-block">{{ __('Site types depth') }}</label>
                        <div class="cabinet-st-depth" id="cabinetStDepth" role="group" aria-label="{{ __('Site types depth') }}">
                            @foreach($depths as $d)
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary cabinet-st-depth__btn @if((int)$d === (int)$defaultDepth) is-active @endif"
                                        data-depth="{{ (int) $d }}">ТОП-{{ (int) $d }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" id="cabinetStDepthValue" value="{{ (int) $defaultDepth }}">
                    </div>

                    <div class="form-group">
                        <label class="d-block">{{ __('Site types engines') }}</label>
                        <div class="cabinet-st-checks cabinet-st-checks--inline">
                            <label><input type="checkbox" id="engine_yandex" checked> {{ __('Yandex') }}</label>
                            <label><input type="checkbox" id="engine_google"> {{ __('Google') }}</label>
                        </div>
                    </div>

                    <div class="form-group" id="cabinetStYandexRegionWrap">
                        <label for="cabinetStYandexLr">{{ __('Site types yandex region') }}</label>
                        <select id="cabinetStYandexLr"
                                class="form-control form-control-sm cabinet-st-region-select"
                                data-engine="yandex"
                                data-placeholder="{{ __('Search city or region') }}"
                                style="width: 100%;">
                            @if(!empty($defaultYandex))
                                <option value="{{ $defaultYandex['id'] }}" selected>
                                    {{ $defaultYandex['text'] ?? (($defaultYandex['name'] ?? '') . ' [' . $defaultYandex['id'] . ']') }}
                                </option>
                            @endif
                        </select>
                    </div>

                    <div class="form-group d-none" id="cabinetStGoogleRegionWrap">
                        <label for="cabinetStGoogleLr">{{ __('Site types google region') }}</label>
                        <select id="cabinetStGoogleLr"
                                class="form-control form-control-sm cabinet-st-region-select"
                                data-engine="google"
                                data-placeholder="{{ __('Site types google region search') }}"
                                style="width: 100%;">
                            @if(!empty($defaultGoogle))
                                <option value="{{ $defaultGoogle['id'] }}" selected>
                                    {{ $defaultGoogle['text'] ?? (($defaultGoogle['name'] ?? '') . ' [' . $defaultGoogle['id'] . ']') }}
                                </option>
                            @endif
                        </select>
                        <small class="form-text text-muted">{{ __('Site types google region hint') }}</small>
                    </div>

                    @if($canSaveHistory)
                        <div class="form-group mb-2">
                            <label class="cabinet-st-save-check" for="cabinetStSave">
                                <input type="checkbox" id="cabinetStSave" checked>
                                <span>{{ __('Site types save to history') }}</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>

            <details class="cabinet-st-catalogs mb-3">
                <summary>{{ __('Site types custom catalogs') }}</summary>
                <p class="small text-secondary mb-3">{{ __('Site types custom catalogs hint') }}</p>

                <div class="cabinet-st-presets mb-3" id="cabinetStPresets">
                    <div class="d-flex flex-wrap align-items-end gap-2">
                        <div class="cabinet-st-presets__select">
                            <label class="form-label small mb-1" for="cabinetStPresetSelect">{{ __('Site types catalog presets') }}</label>
                            <select id="cabinetStPresetSelect" class="form-control form-control-sm">
                                <option value="">{{ __('Site types catalog preset select') }}</option>
                                @foreach($catalogPresets as $preset)
                                    <option value="{{ $preset['id'] }}">{{ $preset['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="cabinetStPresetApply" disabled>
                            <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Site types catalog preset apply') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinetStPresetSave"
                                @if(count($catalogPresets) >= $maxCatalogPresets) disabled @endif>
                            <i class="bi bi-bookmark-plus me-1" aria-hidden="true"></i>{{ __('Save as preset') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinetStPresetUpdate" disabled>
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>{{ __('Site types catalog preset update') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="cabinetStPresetDelete" disabled>
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete preset') }}
                        </button>
                    </div>
                    <p class="small text-muted mb-0 mt-2">{{ __('Site types catalog presets hint', ['max' => $maxCatalogPresets]) }}</p>
                </div>

                <div class="row">
                    @foreach($categories as $key => $cat)
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label class="cabinet-st-cat-label" for="custom_{{ $key }}"
                                   style="--st-cat: {{ $cat['color'] ?? '#64748b' }}">
                                <span class="cabinet-st-cat-dot"></span>
                                {{ $cat['label'] ?? $key }}
                            </label>
                            <textarea id="custom_{{ $key }}" class="form-control form-control-sm cabinet-st-custom"
                                      rows="4"
                                      data-type="{{ $key }}"
                                      placeholder="domain.ru&#10;example.com"></textarea>
                            <small class="form-text text-muted">{{ $cat['hint'] ?? '' }}</small>
                        </div>
                    @endforeach
                </div>
            </details>

            <div class="modal fade" id="cabinetStPresetSaveModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Save as preset') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="cabinetStPresetName">{{ __('Preset name') }}</label>
                            <input type="text" class="form-control" id="cabinetStPresetName" maxlength="120"
                                   placeholder="{{ __('Site types catalog preset name placeholder') }}">
                            <p class="small text-danger mb-0 mt-2 d-none" id="cabinetStPresetSaveError" role="alert"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="cabinetStPresetSaveSubmit">{{ __('Save preset') }}</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="submit" class="btn btn-primary" id="cabinetStSubmit">{{ __('Site types submit') }}</button>
                <button type="button" class="btn btn-outline-secondary" id="cabinetStClear">{{ __('Clear') }}</button>
                <span class="small text-muted ml-2" id="cabinetStStatus"></span>
            </div>

            <div class="cabinet-st-progress d-none mb-3" id="cabinetStProgress" aria-live="polite">
                <div class="cabinet-st-progress__head">
                    <span class="cabinet-st-progress__spinner" aria-hidden="true"></span>
                    <div class="cabinet-st-progress__text">
                        <div class="cabinet-st-progress__title" id="cabinetStProgressTitle">{{ __('Site types progress title') }}</div>
                        <div class="cabinet-st-progress__sub small text-secondary" id="cabinetStProgressSub"></div>
                    </div>
                    <div class="cabinet-st-progress__time small text-muted text-nowrap" id="cabinetStProgressTime">0:00</div>
                </div>
                <div class="progress cabinet-st-progress__bar">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         id="cabinetStProgressBar"
                         role="progressbar"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-valuenow="0"
                         style="width: 0%"></div>
                </div>
                <p class="cabinet-st-progress__keep small text-muted mb-0 mt-2">
                    {{ __('Site types progress keep tab hint') }}
                </p>
            </div>
        </form>

        <div class="px-4 mb-4 d-none cabinet-st-results-block" id="cabinetStResultsWrap">
            <div class="cabinet-st-verdict mb-3" id="cabinetStVerdict">
                <div class="cabinet-st-verdict__title" id="cabinetStVerdictTitle"></div>
                <div class="cabinet-st-verdict__hint small" id="cabinetStVerdictHint"></div>
            </div>

            <div class="cabinet-st-mix mb-3" id="cabinetStMix"></div>

            <div class="cabinet-st-phrase-block mb-3 d-none" id="cabinetStPhraseBlock">
                <h5 class="h6 mb-2">{{ __('Site types phrase matrix title') }}</h5>
                <div class="table-responsive cabinet-st-matrix-scroll">
                    <table class="table table-sm table-bordered cabinet-st-matrix mb-0" id="cabinetStPhraseMatrix">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="cabinet-st-hosts-block mb-3 d-none" id="cabinetStHostsBlock">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <h5 class="h6 mb-0">{{ __('Site types frequent hosts title') }}</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <select id="cabinetStHostsFilterType" class="form-control form-control-sm" style="width: auto; min-width: 10rem;"
                                aria-label="{{ __('Site types filter all') }}">
                            <option value="">{{ __('Site types filter all') }}</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinetStHostsExport">{{ __('Export') }} CSV</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered cabinet-st-hosts mb-0" id="cabinetStFrequentHosts">
                        <thead>
                        <tr>
                            <th>{{ __('Site types col host') }}</th>
                            <th style="width: 5rem;">{{ __('Site types col count') }}</th>
                            <th style="width: 9rem;">{{ __('Site types col in catalog') }}</th>
                            <th style="width: 12rem;">{{ __('Site types col type') }}</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <h5 class="h6 mb-0">{{ __('Site types results title') }}
                    <span class="text-muted font-weight-normal" id="cabinetStResultsMeta"></span>
                </h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select id="cabinetStFilterType" class="form-control form-control-sm" style="width: auto; min-width: 10rem;">
                        <option value="">{{ __('Site types filter all') }}</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinetStExport">{{ __('Export') }} CSV</button>
                </div>
            </div>

            <div class="cabinet-st-query-tabs mb-2" id="cabinetStQueryTabs"></div>
            <p class="small text-muted mb-2 d-none" id="cabinetStShortfallNote" role="status"
               data-template="{{ e(__('Site types shortfall note')) }}"></p>

            <div class="table-responsive cabinet-st-results-scroll">
                <table class="table table-sm table-bordered mb-0 cabinet-st-serp" id="cabinetStResults">
                    <thead>
                    <tr>
                        <th class="cabinet-st-col-pos">#</th>
                        <th class="cabinet-st-col-domain">{{ __('Site types col domain') }}</th>
                        <th class="cabinet-st-col-type">{{ __('Site types col type') }}</th>
                        <th class="cabinet-st-col-url">{{ __('Site types col url') }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        @if($canSaveHistory && count($histories))
            <div class="px-4 pb-4 cabinet-st-history-block" id="cabinetStHistoryWrap">
                <h5 class="h6 mb-2">{{ __('Site types history title') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="cabinetStHistoryTable">
                        <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Site types col engine') }}</th>
                            <th>{{ __('Site types history settings') }}</th>
                            <th>{{ __('Site types col phrases') }}</th>
                            <th>{{ __('Site types positions') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($histories as $h)
                            <tr data-id="{{ $h->id }}">
                                <td class="text-nowrap">{{ optional($h->created_at)->format('d.m.Y H:i') }}</td>
                                <td>{{ $h->title }}</td>
                                <td class="text-nowrap">{{ $h->enginesLabel() }}</td>
                                <td class="small text-secondary">{{ $h->settingsLabel() }}</td>
                                <td>{{ $h->phrases_count }}</td>
                                <td>{{ $h->results_count }}</td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-xs btn-outline-primary cabinet-st-history-open">{{ __('Open') }}</button>
                                    <button type="button" class="btn btn-xs btn-outline-danger cabinet-st-history-del">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}?v={{ @filemtime(public_path('js/cabinet-select2-defaults.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-site-types.js') }}?v={{ @filemtime(public_path('js/cabinet-site-types.js')) ?: time() }}"></script>
    @endslot
@endcomponent
