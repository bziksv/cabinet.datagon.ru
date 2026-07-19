@component('component.card', [
    'title' => __('Text uniqueness'),
    'titleHtml' => e(__('Text uniqueness')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-text-uniqueness'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-text-uniqueness.css') }}?v={{ @filemtime(public_path('css/cabinet-text-uniqueness.css')) ?: time() }}">
    @endslot

    <div class="cabinet-tu-page" id="cabinetTuPage"
         data-analyze-url="{{ route('pages.text-uniqueness.analyze') }}"
         data-estimate-url="{{ route('pages.text-uniqueness.estimate') }}"
         data-history-url="{{ url('/text-uniqueness/history') }}"
         data-regions-url="{{ route('competitor.analysis.regions') }}"
         data-csrf="{{ csrf_token() }}"
         data-can-save="{{ $canSaveHistory ? '1' : '0' }}"
         data-min-chars="{{ (int) $minChars }}"
         data-max-chars="{{ (int) $maxChars }}"
         data-limit="{{ $limit !== null ? (int) $limit : '' }}"
         data-remaining="{{ $remaining !== null ? (int) $remaining : '' }}"
         data-hint-internet="{{ __('Text uniqueness mode internet hint') }}"
         data-hint-urls="{{ __('Text uniqueness mode urls hint') }}">

        <div class="cabinet-tu-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-center justify-content-between flex-wrap">
                <div class="d-flex gap-3 align-items-center">
                    <span class="cabinet-tu-lead__icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                    <div>
                        <p class="mb-0 fw-semibold text-body">{{ __('Text uniqueness lead title') }}</p>
                        <p class="mb-0 small text-secondary">{{ __('Text uniqueness lead hint') }}</p>
                    </div>
                </div>
                <div class="cabinet-tu-cost text-nowrap" id="cabinetTuCostPreview"
                     data-label="{{ __('Text uniqueness cost label') }}"
                     data-unit-one="{{ __('Site types cost unit one') }}"
                     data-unit-few="{{ __('Site types cost unit few') }}"
                     data-unit-many="{{ __('Site types cost unit many') }}">
                    <span id="cabinetTuCostText">{{ __('Text uniqueness cost label') }} <strong id="cabinetTuCostValue">0</strong> {{ __('Site types cost unit many') }}</span>
                </div>
            </div>
        </div>

        <form id="cabinetTuForm" class="px-4 pb-2" autocomplete="off">
            <div class="row">
                <div class="col-lg-7">
                    <div class="form-group">
                        <label for="cabinetTuText">{{ __('Text uniqueness text label') }}</label>
                        <textarea id="cabinetTuText" class="form-control" rows="12"
                                  placeholder="{{ __('Text uniqueness text placeholder') }}"></textarea>
                        <small class="form-text text-muted">{{ __('Text uniqueness text hint', ['min' => $minChars, 'max' => $maxChars]) }}</small>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="form-group">
                        <label class="d-block">{{ __('Text uniqueness mode label') }}</label>
                        <div class="cabinet-tu-modes">
                            <label class="cabinet-tu-mode">
                                <input type="radio" name="tu_mode" value="internet" checked>
                                <span>{{ __('Text uniqueness mode internet') }}</span>
                            </label>
                            <label class="cabinet-tu-mode">
                                <input type="radio" name="tu_mode" value="urls">
                                <span>{{ __('Text uniqueness mode urls') }}</span>
                            </label>
                        </div>
                        <small class="form-text text-muted" id="cabinetTuModeHint">{{ __('Text uniqueness mode internet hint') }}</small>
                    </div>

                    <div class="form-group" id="cabinetTuInternetWrap">
                        <label class="d-block">{{ __('Text uniqueness engine') }}</label>
                        <div class="cabinet-tu-checks mb-2">
                            <label><input type="radio" name="tu_engine" value="yandex" checked> {{ __('Yandex') }}</label>
                            <label><input type="radio" name="tu_engine" value="google"> {{ __('Google') }}</label>
                        </div>
                        <label for="cabinetTuYandexLr">{{ __('Text uniqueness yandex region') }}</label>
                        <select id="cabinetTuYandexLr" class="form-control form-control-sm" data-engine="yandex" style="width:100%">
                            @if(!empty($defaultYandex))
                                <option value="{{ $defaultYandex['id'] }}" selected>
                                    {{ $defaultYandex['text'] ?? (($defaultYandex['name'] ?? '') . ' [' . $defaultYandex['id'] . ']') }}
                                </option>
                            @endif
                        </select>
                    </div>

                    <div class="form-group d-none" id="cabinetTuUrlsWrap">
                        <label for="cabinetTuUrls">{{ __('Text uniqueness urls label') }}</label>
                        <textarea id="cabinetTuUrls" class="form-control" rows="5"
                                  placeholder="https://example.com/page"></textarea>
                        <small class="form-text text-muted">{{ __('Text uniqueness urls hint') }}</small>
                    </div>

                    @if($canSaveHistory)
                        <div class="form-group mb-2">
                            <label class="cabinet-tu-save" for="cabinetTuSave">
                                <input type="checkbox" id="cabinetTuSave" checked>
                                <span>{{ __('Text uniqueness save to history') }}</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="submit" class="btn btn-primary" id="cabinetTuSubmit">{{ __('Text uniqueness submit') }}</button>
                <button type="button" class="btn btn-outline-secondary" id="cabinetTuClear">{{ __('Clear') }}</button>
                <span class="small text-muted ml-2" id="cabinetTuStatus"></span>
            </div>

            <div class="cabinet-tu-progress d-none mb-3" id="cabinetTuProgress" aria-live="polite">
                <div class="cabinet-tu-progress__head">
                    <span class="cabinet-tu-progress__spinner" aria-hidden="true"></span>
                    <div class="cabinet-tu-progress__text">
                        <div class="cabinet-tu-progress__title" id="cabinetTuProgressTitle">{{ __('Text uniqueness progress') }}</div>
                        <div class="cabinet-tu-progress__sub small text-secondary" id="cabinetTuProgressSub"></div>
                    </div>
                </div>
            </div>
        </form>

        @if($canSaveHistory && count($histories))
            <div class="px-4 mb-4">
                <h5 class="h6 mb-2">{{ __('Text uniqueness history title') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Text uniqueness col mode') }}</th>
                            <th>%</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($histories as $h)
                            <tr data-id="{{ $h->id }}">
                                <td class="text-nowrap">{{ optional($h->created_at)->format('d.m.Y H:i') }}</td>
                                <td>{{ $h->title }}</td>
                                <td>{{ $h->modeLabel() }}</td>
                                <td>{{ $h->uniqueness_pct }}%</td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-xs btn-outline-primary cabinet-tu-history-open">{{ __('Open') }}</button>
                                    <button type="button" class="btn btn-xs btn-outline-danger cabinet-tu-history-del">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="px-4 pb-4 d-none" id="cabinetTuResultsWrap">
            <div class="cabinet-tu-summary mb-3" id="cabinetTuSummary"></div>
            <h5 class="h6 mb-2">{{ __('Text uniqueness sources title') }}</h5>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0" id="cabinetTuSources">
                    <thead>
                    <tr>
                        <th>{{ __('Text uniqueness col url') }}</th>
                        <th>{{ __('Text uniqueness col overlap') }}</th>
                        <th>{{ __('Text uniqueness col samples') }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <h5 class="h6 mb-2">{{ __('Text uniqueness matched title') }}</h5>
            <div class="cabinet-tu-matched" id="cabinetTuMatched"></div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}?v={{ @filemtime(public_path('js/cabinet-select2-defaults.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-text-uniqueness.js') }}?v={{ @filemtime(public_path('js/cabinet-text-uniqueness.js')) ?: time() }}"></script>
    @endslot
@endcomponent
