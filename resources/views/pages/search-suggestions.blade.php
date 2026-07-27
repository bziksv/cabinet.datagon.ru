@component('component.card', [
    'title' => __('Search suggestions'),
    'titleHtml' => e(__('Search suggestions')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-search-suggestions'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-search-suggestions.css') }}?v={{ @filemtime(public_path('css/cabinet-search-suggestions.css')) ?: time() }}">
    @endslot

    <div class="cabinet-ss-page" id="cabinetSsPage"
         data-collect-url="{{ route('pages.search-suggestions.collect') }}"
         data-export-url="{{ route('pages.search-suggestions.export') }}"
         data-history-url="{{ url('/search-suggestions/history') }}"
         data-regions-url="{{ route('cluster.regions') }}"
         data-csrf="{{ csrf_token() }}"
         data-can-save="{{ $canSaveHistory ? '1' : '0' }}"
         data-limit="{{ $limit !== null ? (int) $limit : '' }}"
         data-remaining="{{ $remaining !== null ? (int) $remaining : '' }}"
         data-history-limit="{{ $historyLimit !== null ? (int) $historyLimit : '' }}"
         data-saved-count="{{ (int) $savedCount }}"
         data-pause-ms="{{ (int) config('cabinet-search-suggestions.request_pause_ms', 80) }}"
         data-preset-local="{{ count(config('cabinet-search-suggestions.presets.local', [])) }}"
         data-preset-shopping="{{ count(config('cabinet-search-suggestions.presets.shopping', [])) }}"
         data-preset-questions="{{ count(config('cabinet-search-suggestions.presets.questions', [])) }}"
         data-preset-reviews="{{ count(config('cabinet-search-suggestions.presets.reviews', [])) }}"
         data-stop-presets='@json(collect(config("cabinet-search-suggestions.stop_word_presets", []))->mapWithKeys(function ($p, $k) { return [$k => array_values($p["words"] ?? [])]; }))'
         data-i18n-keep="{{ e(__('Search suggestions keep tab hint')) }}"
         data-i18n-notify-done-title="{{ e(__('Search suggestions notify done title')) }}"
         data-i18n-notify-done-body="{{ e(__('Search suggestions notify done body')) }}"
         data-i18n-notify-error-title="{{ e(__('Search suggestions notify error title')) }}"
         data-i18n-notify-error-body="{{ e(__('Search suggestions notify error body')) }}">

        <div class="cabinet-ss-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-ss-lead__icon" aria-hidden="true"><i class="bi bi-lightbulb"></i></span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Search suggestions lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Search suggestions lead hint') }}</p>
                </div>
            </div>
        </div>

        <form id="cabinetSsForm" class="px-4 pb-2" autocomplete="off">
            <div class="row">
                <div class="col-lg-7">
                    <div class="form-group">
                        <label for="cabinetSsSeeds">{{ __('Search suggestions seeds label') }}</label>
                        <textarea id="cabinetSsSeeds" class="form-control" rows="8"
                                  placeholder="{{ __('Search suggestions seeds placeholder') }}"></textarea>
                        <small class="form-text text-muted">{{ __('Search suggestions seeds hint') }}</small>
                    </div>
                    <div class="form-group">
                        <label for="cabinetSsStop">{{ __('Search suggestions stop words') }}</label>
                        <textarea id="cabinetSsStop" class="form-control" rows="8"
                                  placeholder="{{ __('Search suggestions stop words placeholder') }}"></textarea>
                        <div class="cabinet-ss-stop-presets mt-2" id="cabinetSsStopPresets" role="group"
                             aria-label="{{ __('Search suggestions stop presets') }}">
                            @foreach(config('cabinet-search-suggestions.stop_word_presets', []) as $key => $preset)
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary cabinet-ss-stop-presets__btn"
                                        data-stop-preset="{{ $key }}"
                                        data-ss-tip="{{ $preset['hint'] ?? '' }}">
                                    {{ $preset['label'] ?? $key }}
                                </button>
                            @endforeach
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger cabinet-ss-stop-presets__btn"
                                    data-stop-preset="clear"
                                    data-ss-tip="{{ __('Search suggestions stop presets clear hint') }}">
                                {{ __('Search suggestions stop presets clear') }}
                            </button>
                        </div>
                        <small class="form-text text-muted">{{ __('Search suggestions stop presets hint') }}</small>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="form-group">
                        <label class="d-block">{{ __('Search suggestions quick presets') }}</label>
                        <div class="cabinet-ss-quick" id="cabinetSsQuickPresets" role="group" aria-label="{{ __('Search suggestions quick presets') }}">
                            <button type="button" class="btn btn-sm btn-outline-primary cabinet-ss-quick__btn" data-preset="basic"
                                    data-ss-tip="{{ __('Search suggestions quick basic tip') }}">{{ __('Search suggestions quick basic') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary cabinet-ss-quick__btn" data-preset="alphabet"
                                    data-ss-tip="{{ __('Search suggestions quick alphabet tip') }}">{{ __('Search suggestions quick alphabet') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary cabinet-ss-quick__btn" data-preset="commerce"
                                    data-ss-tip="{{ __('Search suggestions quick commerce tip') }}">{{ __('Search suggestions quick commerce') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary cabinet-ss-quick__btn" data-preset="questions"
                                    data-ss-tip="{{ __('Search suggestions quick questions tip') }}">{{ __('Search suggestions quick questions') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary cabinet-ss-quick__btn" data-preset="max"
                                    data-ss-tip="{{ __('Search suggestions quick max tip') }}">{{ __('Search suggestions quick max') }}</button>
                        </div>
                        <small class="form-text text-muted">{{ __('Search suggestions quick presets hint') }}</small>
                    </div>
                    <div class="form-group">
                        <label class="d-block">{{ __('Search suggestions modes') }}</label>
                        <div class="cabinet-ss-checks">
                            <label><input type="checkbox" id="mode_phrase" checked> {{ __('Search suggestions mode phrase') }}</label>
                            <label><input type="checkbox" id="mode_space"> {{ __('Search suggestions mode space') }}</label>
                            <label><input type="checkbox" id="mode_en"> {{ __('Search suggestions mode en') }}</label>
                            <label><input type="checkbox" id="mode_ru"> {{ __('Search suggestions mode ru') }}</label>
                            <label><input type="checkbox" id="mode_digits"> {{ __('Search suggestions mode digits') }}</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="d-block">{{ __('Search suggestions presets') }}</label>
                        <div class="cabinet-ss-checks">
                            <label><input type="checkbox" id="preset_local"> {{ __('Search suggestions preset local') }}</label>
                            <label><input type="checkbox" id="preset_shopping"> {{ __('Search suggestions preset shopping') }}</label>
                            <label><input type="checkbox" id="preset_questions"> {{ __('Search suggestions preset questions') }}</label>
                            <label><input type="checkbox" id="preset_reviews"> {{ __('Search suggestions preset reviews') }}</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cabinetSsDepth">{{ __('Search suggestions depth') }}</label>
                        <select id="cabinetSsDepth" class="form-control form-control-sm" style="max-width: 8rem;">
                            <option value="1" selected>1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                        <small class="form-text text-muted">{{ __('Search suggestions depth hint') }}</small>
                    </div>
                    <div class="form-group">
                        <label class="d-block">{{ __('Search suggestions engines') }}</label>
                        <div class="cabinet-ss-checks cabinet-ss-checks--inline">
                            <label><input type="checkbox" id="engine_yandex" checked> {{ __('Yandex') }}</label>
                            <label><input type="checkbox" id="engine_google"> {{ __('Google') }}</label>
                        </div>
                    </div>
                    <div class="form-group" id="cabinetSsYandexRegionWrap">
                        <label for="cabinetSsYandexLr">{{ __('Search suggestions yandex region') }}</label>
                        <select id="cabinetSsYandexLr"
                                class="form-control form-control-sm cabinet-ss-region-select"
                                data-placeholder="{{ __('Search city or region') }}"
                                style="width: 100%;">
                            @if(!empty($defaultRegion))
                                <option value="{{ $defaultRegion['id'] }}" selected>
                                    {{ $defaultRegion['text'] ?? (($defaultRegion['name'] ?? '') . ' [' . $defaultRegion['id'] . ']') }}
                                </option>
                            @endif
                        </select>
                        <small class="form-text text-muted">{{ __('Search suggestions yandex region hint') }}</small>
                    </div>
                    <div class="form-group d-none" id="cabinetSsGoogleDomainWrap">
                        <label for="cabinetSsGoogleDomain">{{ __('Search suggestions google domain') }}</label>
                        <select id="cabinetSsGoogleDomain" class="form-control form-control-sm" style="width: 100%;">
                            @foreach($googleDomains as $domain => $meta)
                                <option value="{{ $domain }}"
                                        data-gl="{{ $meta['gl'] ?? '' }}"
                                        data-hl="{{ $meta['hl'] ?? '' }}"
                                        @if($domain === $defaultGoogleDomain) selected @endif>
                                    {{ $domain }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group d-none" id="cabinetSsGoogleCountryWrap">
                        <label for="cabinetSsGoogleGl">{{ __('Search suggestions google country') }}</label>
                        <select id="cabinetSsGoogleGl"
                                class="form-control form-control-sm cabinet-ss-google-country"
                                data-placeholder="{{ __('Search suggestions google country search') }}"
                                style="width: 100%;">
                            @foreach($googleCountries as $code => $meta)
                                <option value="{{ $code }}"
                                        data-hl="{{ $meta['hl'] ?? 'en' }}"
                                        @if($code === $defaultGoogleGl) selected @endif>
                                    {{ $meta['name'] ?? $code }} [{{ strtoupper($code) }}]
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">{{ __('Search suggestions google geo hint') }}</small>
                    </div>
                    @if($canSaveHistory)
                        <div class="form-group mb-0">
                            <label class="cabinet-ss-save-check" for="cabinetSsSave">
                                <input type="checkbox" id="cabinetSsSave" checked>
                                <span>{{ __('Search suggestions save to history') }}</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="submit" class="btn btn-primary" id="cabinetSsSubmit"
                        data-ss-tip="{{ __('Search suggestions submit tip') }}">{{ __('Search suggestions submit') }}</button>
                <button type="button" class="btn btn-outline-secondary" id="cabinetSsClear"
                        data-ss-tip="{{ __('Search suggestions clear tip') }}">{{ __('Clear') }}</button>
                <span class="small text-muted ml-2" id="cabinetSsStatus"></span>
            </div>

            <div class="cabinet-ss-progress d-none mb-3" id="cabinetSsProgress" aria-live="polite">
                <div class="cabinet-ss-progress__head">
                    <span class="cabinet-ss-progress__spinner" aria-hidden="true"></span>
                    <div class="cabinet-ss-progress__text">
                        <div class="cabinet-ss-progress__title" id="cabinetSsProgressTitle">Сбор подсказок…</div>
                        <div class="cabinet-ss-progress__sub small text-secondary" id="cabinetSsProgressSub"></div>
                    </div>
                    <div class="cabinet-ss-progress__time small text-muted text-nowrap" id="cabinetSsProgressTime">0:00</div>
                </div>
                <div class="progress cabinet-ss-progress__bar">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         id="cabinetSsProgressBar"
                         role="progressbar"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-valuenow="0"
                         style="width: 0%"></div>
                </div>
                <p class="cabinet-ss-progress__keep small text-muted mb-0 mt-2" id="cabinetSsProgressKeep">
                    {{ __('Search suggestions keep tab hint') }}
                </p>
            </div>
        </form>

        <div class="px-4 mb-4 d-none" id="cabinetSsResultsWrap">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <h5 class="h6 mb-0">{{ __('Search suggestions results title') }}
                    <span class="text-muted font-weight-normal" id="cabinetSsResultsMeta"></span>
                </h5>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="cabinetSsCopySuggests"
                            data-ss-tip="{{ __('Search suggestions copy suggests tip') }}">{{ __('Search suggestions copy suggests') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinetSsExport"
                            data-ss-tip="{{ __('Search suggestions export tip') }}">{{ __('Export') }} CSV</button>
                </div>
            </div>
            <div class="table-responsive cabinet-ss-results-scroll">
                <table class="table table-sm table-striped table-bordered mb-0" id="cabinetSsResults">
                    <thead>
                    <tr>
                        <th>{{ __('Search suggestions col seed') }}</th>
                        <th>{{ __('Search suggestions col suggest') }}</th>
                        <th>{{ __('Search suggestions col engine') }}</th>
                        <th>{{ __('Search suggestions col level') }}</th>
                        <th>{{ __('Search suggestions col words') }}</th>
                        <th>{{ __('Search suggestions col type') }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        @if($canSaveHistory && count($histories))
            <div class="px-4 pb-4">
                <h5 class="h6 mb-2">{{ __('Search suggestions history title') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="cabinetSsHistoryTable">
                        <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Search suggestions col engine') }}</th>
                            <th>{{ __('Search suggestions history settings') }}</th>
                            <th>{{ __('Search suggestions col seeds') }}</th>
                            <th>{{ __('Search suggestions results title') }}</th>
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
                                <td>{{ $h->seeds_count }}</td>
                                <td>{{ $h->results_count }}</td>
                                <td class="text-nowrap">
                                    <div class="cabinet-ss-history-actions">
                                        <button type="button" class="btn btn-xs btn-outline-primary cabinet-ss-history-open"
                                                data-ss-tip="{{ __('Search suggestions history open tip') }}">{{ __('Open') }}</button>
                                        <button type="button" class="btn btn-xs btn-outline-danger cabinet-ss-history-del"
                                                data-ss-tip="{{ __('Search suggestions history delete tip') }}">{{ __('Delete') }}</button>
                                    </div>
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
        <script src="{{ asset('js/cabinet-search-suggestions.js') }}?v={{ @filemtime(public_path('js/cabinet-search-suggestions.js')) ?: time() }}"></script>
    @endslot
@endcomponent
