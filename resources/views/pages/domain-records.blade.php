@component('component.card', [
    'title' => __('Domain records'),
    'titleHtml' => e(__('Domain records')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-domain-records'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-domain-records.css') }}?v={{ @filemtime(public_path('css/cabinet-domain-records.css')) ?: time() }}">
    @endslot

    <div class="cabinet-dr-page" id="cabinetDrPage"
         data-lookup-url="{{ route('pages.domain-records.lookup') }}"
         data-neighbors-url="{{ route('pages.domain-records.ip-neighbors') }}"
         data-history-url="{{ url('/domain-records/history') }}"
         data-compare-url="{{ route('pages.domain-records.compare') }}"
         data-add-site-url="{{ route('pages.domain-records.add-site-monitoring') }}"
         data-add-domain-url="{{ route('pages.domain-records.add-domain-information') }}"
         data-csrf="{{ csrf_token() }}"
         data-can-site="{{ !empty($canAddSiteMonitoring) ? '1' : '0' }}"
         data-can-domain="{{ !empty($canAddDomainInformation) ? '1' : '0' }}"
         data-can-save="{{ !empty($canSaveHistory) ? '1' : '0' }}"
         data-history-limit="{{ isset($historyLimit) && $historyLimit !== null ? (int) $historyLimit : '' }}"
         data-saved-count="{{ (int) ($savedCount ?? 0) }}"
         data-i18n-ip-col="{{ e(__('Domain records site ip title')) }}"
         data-i18n-neighbors-col="{{ e(__('Domain records ip neighbors')) }}"
         data-i18n-neighbors-load="{{ e(__('Domain records ip neighbors load')) }}"
         data-i18n-neighbors-empty="{{ e(__('Domain records ip neighbors empty')) }}"
         data-i18n-neighbors-self="{{ e(__('Domain records ip neighbors self only')) }}"
         data-i18n-neighbors-error="{{ e(__('Domain records ip neighbors api error')) }}"
         data-i18n-neighbors-loading="{{ e(__('Domain records ip neighbors loading')) }}"
         data-i18n-compare-pick="{{ e(__('Domain records compare pick two')) }}"
         data-i18n-compare-title="{{ e(__('Domain records compare title')) }}">

        <div class="cabinet-dr-hero">
            <div class="cabinet-dr-hero__copy">
                <p class="cabinet-dr-hero__eyebrow">WHOIS · DNS · A / MX / NS / TXT</p>
                <h2 class="cabinet-dr-hero__title">{{ __('Domain records lead title') }}</h2>
                <p class="cabinet-dr-hero__hint">{{ __('Domain records lead hint') }}</p>
            </div>
        </div>

        <form id="cabinetDrForm" class="cabinet-dr-form" autocomplete="off">
            <div class="cabinet-dr-form__row">
                <div class="cabinet-dr-form__field">
                    <label for="cabinetDrDomain">{{ __('Domain records domain label') }}</label>
                    <input type="text" id="cabinetDrDomain" class="form-control form-control-lg"
                           placeholder="example.ru или https://example.ru"
                           autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" id="cabinetDrSubmit">
                    {{ __('Domain records submit') }}
                </button>
            </div>
            @if(!empty($canSaveHistory))
                <div class="cabinet-dr-form__save">
                    <label class="cabinet-dr-save-check" for="cabinetDrSave">
                        <input type="checkbox" id="cabinetDrSave" checked>
                        <span>{{ __('Domain records save to history') }}</span>
                    </label>
                </div>
            @endif
            <p class="cabinet-dr-form__status small" id="cabinetDrStatus"></p>
        </form>

        @if(!empty($canSaveHistory))
            <div class="cabinet-dr-history mb-4" id="cabinetDrHistoryWrap">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <h5 class="h6 mb-0">{{ __('Domain records history title') }}</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="cabinetDrCompareBtn" disabled>
                        {{ __('Domain records compare') }}
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="cabinetDrHistoryTable">
                        <thead>
                        <tr>
                            <th style="width: 2.5rem;"></th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Domain') }}</th>
                            <th>{{ __('Domain records history col ip') }}</th>
                            <th>{{ __('Domain records history col dns') }}</th>
                            <th class="text-nowrap">{{ __('Domain records history col neighbors') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody id="cabinetDrHistoryBody">
                        @forelse(($histories ?? []) as $h)
                            @php $sum = $h->tableSummary(); @endphp
                            <tr data-id="{{ $h->id }}">
                                <td class="text-center">
                                    <input type="checkbox" class="cabinet-dr-history-cmp" value="{{ $h->id }}">
                                </td>
                                <td class="text-nowrap">{{ optional($h->created_at)->format('d.m.Y H:i') }}</td>
                                <td>{{ $h->domain }}</td>
                                <td class="small text-nowrap"><code>{{ $sum['ip'] }}</code></td>
                                <td class="small">{{ $sum['dns'] }}</td>
                                <td class="text-nowrap">
                                    @if($sum['neighbors'] === null)
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ (int) $sum['neighbors'] }}
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-xs btn-outline-primary cabinet-dr-history-open">{{ __('Open') }}</button>
                                    <button type="button" class="btn btn-xs btn-outline-danger cabinet-dr-history-del">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr class="cabinet-dr-history-empty">
                                <td colspan="7" class="text-muted small">{{ __('Domain records history empty') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="cabinet-dr-compare d-none mt-3" id="cabinetDrCompare"></div>
            </div>
        @endif

        <div class="cabinet-dr-results d-none" id="cabinetDrResults">
            <div class="cabinet-dr-summary" id="cabinetDrSummary"></div>

            <div class="cabinet-dr-actions" id="cabinetDrActions">
                @if(!empty($canAddSiteMonitoring))
                    <button type="button" class="btn btn-outline-primary" id="cabinetDrAddSite">
                        <i class="fas fa-heartbeat mr-1"></i> {{ __('Domain records add site monitoring') }}
                    </button>
                @endif
                @if(!empty($canAddDomainInformation))
                    <button type="button" class="btn btn-outline-success" id="cabinetDrAddDomain">
                        <i class="fas fa-clock mr-1"></i> {{ __('Domain records add domain information') }}
                    </button>
                @endif
            </div>

            <div class="row">
                <div class="col-lg-5 mb-3">
                    <section class="cabinet-dr-card">
                        <header class="cabinet-dr-card__head">
                            <h3>{{ __('Domain records whois title') }}</h3>
                        </header>
                        <div class="cabinet-dr-card__body" id="cabinetDrWhois"></div>
                    </section>
                </div>
                <div class="col-lg-7 mb-3">
                    <section class="cabinet-dr-card">
                        <header class="cabinet-dr-card__head cabinet-dr-card__head--split">
                            <h3>{{ __('Domain records dns title') }}</h3>
                            <div class="cabinet-dr-dns-tabs" id="cabinetDrDnsTabs"></div>
                        </header>
                        <div class="cabinet-dr-card__body cabinet-dr-dns-body" id="cabinetDrDns"></div>
                    </section>
                </div>
            </div>

            <section class="cabinet-dr-card mb-2">
                <header class="cabinet-dr-card__head cabinet-dr-card__head--split">
                    <h3>{{ __('Domain records site ip title') }}</h3>
                    <span class="small text-muted">{{ __('Domain records ip neighbors hint') }}</span>
                </header>
                <div class="cabinet-dr-card__body" id="cabinetDrIps"></div>
            </section>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('js/cabinet-domain-records.js') }}?v={{ @filemtime(public_path('js/cabinet-domain-records.js')) ?: time() }}"></script>
    @endslot
@endcomponent
