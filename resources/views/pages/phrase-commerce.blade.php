@component('component.card', [
    'title' => __('Phrase commerce'),
    'titleHtml' => e(__('Phrase commerce')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-phrase-commerce'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-phrase-commerce.css') }}?v={{ @filemtime(public_path('css/cabinet-phrase-commerce.css')) ?: time() }}">
    @endslot

    <div class="cabinet-pc-page" id="cabinetPcPage"
         data-analyze-url="{{ route('pages.phrase-commerce.analyze') }}"
         data-export-url="{{ route('pages.phrase-commerce.export') }}"
         data-history-url="{{ url('/phrase-commerce/history') }}"
         data-history-store-url="{{ route('pages.phrase-commerce.history.store') }}"
         data-regions-url="{{ route('competitor.analysis.regions') }}"
         data-csrf="{{ csrf_token() }}"
         data-can-save="{{ $canSaveHistory ? '1' : '0' }}"
         data-cost-yandex="{{ (int) $costYandex }}"
         data-cost-google="{{ (int) $costGoogle }}"
         data-limit="{{ $limit !== null ? (int) $limit : '' }}"
         data-remaining="{{ $remaining !== null ? (int) $remaining : '' }}"
         data-history-limit="{{ $historyLimit !== null ? (int) $historyLimit : '' }}"
         data-saved-count="{{ (int) $savedCount }}"
         data-tip-total="{{ e(__('Phrase commerce tip total')) }}"
         data-tip-gz="{{ e(__('Phrase commerce tip gz')) }}"
         data-tip-gnz="{{ e(__('Phrase commerce tip gnz')) }}"
         data-tip-com="{{ e(__('Phrase commerce tip com')) }}"
         data-tip-info="{{ e(__('Phrase commerce tip info')) }}"
         data-tip-loc="{{ e(__('Phrase commerce tip loc')) }}"
         data-tip-com-avg="{{ e(__('Phrase commerce tip com avg')) }}">

        <div class="cabinet-pc-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-center justify-content-between flex-wrap">
                <div class="d-flex gap-3 align-items-center">
                    <span class="cabinet-pc-lead__icon" aria-hidden="true"><i class="fas fa-map-marked-alt"></i></span>
                    <div>
                        <p class="mb-0 fw-semibold text-body">{{ __('Phrase commerce lead title') }}</p>
                        <p class="mb-0 small text-secondary">{{ __('Phrase commerce lead hint') }}</p>
                    </div>
                </div>
                <div class="cabinet-pc-cost text-nowrap" id="cabinetPcCostPreview"
                     data-label="{{ __('Phrase commerce cost label') }}"
                     data-unit-one="{{ __('Site types cost unit one') }}"
                     data-unit-few="{{ __('Site types cost unit few') }}"
                     data-unit-many="{{ __('Site types cost unit many') }}">
                    <span id="cabinetPcCostText">{{ __('Phrase commerce cost label') }} <strong id="cabinetPcCostValue">0</strong> {{ __('Site types cost unit many') }}</span>
                </div>
            </div>
        </div>

        <form id="cabinetPcForm" class="px-4 pb-2" autocomplete="off">
            <div class="row">
                <div class="col-lg-7">
                    <div class="form-group">
                        <label for="cabinetPcPhrases">{{ __('Phrase commerce phrases label') }}</label>
                        <textarea id="cabinetPcPhrases" class="form-control" rows="10"
                                  placeholder="{{ __('Phrase commerce phrases placeholder') }}"></textarea>
                        <small class="form-text text-muted">{{ __('Phrase commerce phrases hint', ['max' => (int) config('cabinet-phrase-commerce.max_phrases', 200)]) }}</small>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="form-group">
                        <label class="d-block">{{ __('Phrase commerce engines') }}</label>
                        <div class="cabinet-pc-checks">
                            <label><input type="checkbox" id="pc_engine_yandex" checked> {{ __('Yandex') }}</label>
                            <label><input type="checkbox" id="pc_engine_google"> {{ __('Google') }}</label>
                        </div>
                    </div>

                    <div class="form-group" id="cabinetPcYandexWrap">
                        <label for="cabinetPcYandexLr">{{ __('Phrase commerce yandex region') }}</label>
                        <select id="cabinetPcYandexLr" class="form-control form-control-sm cabinet-pc-region"
                                data-engine="yandex" style="width: 100%;">
                            @if(!empty($defaultYandex))
                                <option value="{{ $defaultYandex['id'] }}" selected>
                                    {{ $defaultYandex['text'] ?? (($defaultYandex['name'] ?? '') . ' [' . $defaultYandex['id'] . ']') }}
                                </option>
                            @endif
                        </select>
                        <small class="form-text text-muted">{{ __('Phrase commerce contrast hint') }}</small>
                    </div>

                    <div class="form-group d-none" id="cabinetPcGoogleWrap">
                        <label for="cabinetPcGoogleLr">{{ __('Phrase commerce google region') }}</label>
                        <select id="cabinetPcGoogleLr" class="form-control form-control-sm cabinet-pc-region"
                                data-engine="google" style="width: 100%;">
                            @if(!empty($defaultGoogle))
                                <option value="{{ $defaultGoogle['id'] }}" selected>
                                    {{ $defaultGoogle['text'] ?? (($defaultGoogle['name'] ?? '') . ' [' . $defaultGoogle['id'] . ']') }}
                                </option>
                            @endif
                        </select>
                    </div>

                    @if($canSaveHistory)
                        <div class="form-group mb-2">
                            <label class="cabinet-pc-save" for="cabinetPcSave">
                                <input type="checkbox" id="cabinetPcSave" checked>
                                <span>{{ __('Phrase commerce save to history') }}</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="submit" class="btn btn-primary" id="cabinetPcSubmit">{{ __('Phrase commerce submit') }}</button>
                <button type="button" class="btn btn-outline-secondary" id="cabinetPcClear">{{ __('Clear') }}</button>
                <span class="small text-muted ml-2" id="cabinetPcStatus"></span>
            </div>

            <div class="cabinet-pc-progress d-none mb-3" id="cabinetPcProgress" aria-live="polite">
                <div class="cabinet-pc-progress__head">
                    <span class="cabinet-pc-progress__spinner" aria-hidden="true"></span>
                    <div class="cabinet-pc-progress__text">
                        <div class="cabinet-pc-progress__title" id="cabinetPcProgressTitle">Сбор выдачи…</div>
                        <div class="cabinet-pc-progress__sub small text-secondary" id="cabinetPcProgressSub"></div>
                    </div>
                    <div class="cabinet-pc-progress__time small text-muted text-nowrap" id="cabinetPcProgressTime">0:00</div>
                </div>
                <div class="progress cabinet-pc-progress__bar">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         id="cabinetPcProgressBar"
                         role="progressbar"
                         style="width: 0%"
                         aria-valuenow="0"
                         aria-valuemin="0"
                         aria-valuemax="100"></div>
                </div>
            </div>
        </form>

        @if($canSaveHistory && count($histories))
            <div class="px-4 mb-4">
                <h5 class="h6 mb-2">{{ __('Phrase commerce history title') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Site types col engine') }}</th>
                            <th>{{ __('Site types history settings') }}</th>
                            <th>{{ __('Site types col phrases') }}</th>
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
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-xs btn-outline-primary cabinet-pc-history-open">{{ __('Open') }}</button>
                                    <button type="button" class="btn btn-xs btn-outline-danger cabinet-pc-history-del">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="px-4 pb-4 d-none" id="cabinetPcResultsWrap">
            <div class="cabinet-pc-summary mb-3" id="cabinetPcSummary"></div>
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
                <h5 class="h6 mb-0">{{ __('Phrase commerce results title') }}
                    <span class="text-muted font-weight-normal" id="cabinetPcResultsMeta"></span>
                </h5>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="cabinetPcCopy" title="{{ __('Phrase commerce copy filtered') }}">
                        <i class="far fa-copy mr-1"></i>{{ __('Phrase commerce copy filtered') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinetPcExport">{{ __('Export') }} CSV</button>
                </div>
            </div>

            <div class="cabinet-pc-filters mb-3" id="cabinetPcFilters" role="group" aria-label="{{ __('Phrase commerce filters') }}">
                <span class="cabinet-pc-filters__label">{{ __('Phrase commerce filters') }}</span>
                <span class="cabinet-pc-filters__sep" aria-hidden="true"></span>
                <button type="button" class="cabinet-pc-filter is-active" data-filter="all">{{ __('Phrase commerce filter all') }}</button>
                <span class="cabinet-pc-filters__sep" aria-hidden="true"></span>
                <button type="button" class="cabinet-pc-filter is-active" data-filter="yandex">{{ __('Yandex') }}</button>
                <button type="button" class="cabinet-pc-filter is-active" data-filter="google">{{ __('Google') }}</button>
                <span class="cabinet-pc-filters__sep" aria-hidden="true"></span>
                <button type="button" class="cabinet-pc-filter" data-filter="gz">{{ __('Phrase commerce filter gz') }}</button>
                <button type="button" class="cabinet-pc-filter" data-filter="gnz">{{ __('Phrase commerce filter gnz') }}</button>
                <span class="cabinet-pc-filters__sep" aria-hidden="true"></span>
                <button type="button" class="cabinet-pc-filter" data-filter="com">{{ __('Phrase commerce filter com') }}</button>
                <button type="button" class="cabinet-pc-filter" data-filter="nocom">{{ __('Phrase commerce filter nocom') }}</button>
                <span class="cabinet-pc-filters__count small text-muted ml-1" id="cabinetPcFilterCount"></span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 cabinet-pc-table" id="cabinetPcResults">
                    <thead>
                    <tr>
                        <th style="width:2rem"></th>
                        <th>{{ __('Phrase commerce col phrase') }}</th>
                        <th class="cabinet-pc-col-yandex">{{ __('Yandex') }}</th>
                        <th class="cabinet-pc-col-google">{{ __('Google') }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}?v={{ @filemtime(public_path('js/cabinet-select2-defaults.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-phrase-commerce.js') }}?v={{ @filemtime(public_path('js/cabinet-phrase-commerce.js')) ?: time() }}"></script>
    @endslot
@endcomponent
