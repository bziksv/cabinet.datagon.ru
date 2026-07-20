@component('component.card', [
    'title' => __('ROI calculator'),
    'titleHtml' => e(__('ROI calculator')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-roi-calculator'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-roi-calculator.css') }}?v={{ @filemtime(public_path('css/cabinet-roi-calculator.css')) ?: time() }}">
    @endslot

    <div class="cabinet-roi-page content">
        <p class="cabinet-roi-lead">{{ __('ROI calculator lead') }}</p>

        <div class="cabinet-roi-tabs btn-group centered" role="group" aria-label="{{ __('ROI calculator') }}">
            <button type="button" class="btn btn-info active click_tracking" data-click="ROI calculator" data-id="calc">
                {{ __('ROI calculator') }}
            </button>
            <button type="button" class="btn btn-info click_tracking" data-click="Traffic forecast" data-id="prognoz">
                {{ __('Traffic forecast') }}
            </button>
        </div>

        <section class="box-result cabinet-roi-panel" id="calc">
            <div class="row g-3 align-items-stretch">
                <div class="col-lg-4">
                    <div class="card shadow-sm cabinet-roi-form-card h-100">
                        <div class="card-header">
                            <h2 class="card-title mb-0">{{ __('ROI calculator inputs title') }}</h2>
                        </div>
                        <div class="card-body">
                            <form>
                                <div class="cabinet-roi-field--cost">
                                    <label class="form-label" for="zatrat">{{ __('RK cost') }}</label>
                                    <input type="number" class="form-control" name="zatrat" id="zatrat"
                                           placeholder="{{ __('Costs in rubles') }}" required>
                                </div>
                                <div class="cabinet-roi-field--cost">
                                    <label class="form-label" for="doxod">{{ __('Income from RK') }}</label>
                                    <input type="number" class="form-control" name="doxod" id="doxod"
                                           placeholder="{{ __('Income in rubles') }}" required>
                                </div>
                                <div class="cabinet-roi-field--traffic">
                                    <label class="form-label" for="prosmotr">{{ __('Views') }}</label>
                                    <input type="number" class="form-control" name="prosmotr" id="prosmotr"
                                           placeholder="{{ __('Number of views') }}">
                                </div>
                                <div class="cabinet-roi-field--traffic">
                                    <label class="form-label" for="kliki">{{ __('Clicks') }}</label>
                                    <input type="number" class="form-control" name="kliki" id="kliki"
                                           placeholder="{{ __('Number of clicks') }}">
                                </div>
                                <div class="cabinet-roi-field--conv">
                                    <label class="form-label" for="zayavka">{{ __('Applications, calls') }}</label>
                                    <input type="number" class="form-control" name="zayavka" id="zayavka"
                                           placeholder="{{ __('Number of actions') }}">
                                </div>
                                <div class="cabinet-roi-field--conv">
                                    <label class="form-label" for="pokupka">{{ __('Sales') }}</label>
                                    <input type="number" class="form-control" name="pokupka" id="pokupka"
                                           placeholder="{{ __('Number of sales') }}">
                                </div>
                            </form>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-primary click_tracking" data-click="Calculate" id="go-calc">
                                    <i class="bi bi-calculator" aria-hidden="true"></i>
                                    {{ __('Calculate') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Clear" id="go-reset">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    {{ __('Clear') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="cabinet-roi-metrics-wrap h-100">
                        <p class="cabinet-roi-metrics-wrap__title">{{ __('ROI calculator metrics title') }}</p>
                        <div class="row g-2 boxes">
                            @foreach($arRoi as $roi)
                                @include('pages.partials.roi-metric-card', ['roi' => $roi])
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @foreach($arRoi as $roi)
                <input type="hidden" id="{{ $roi['id_value'] }}-val">
            @endforeach
        </section>

        <section class="box-result cabinet-roi-panel" id="prognoz">
            <div class="row g-3 align-items-stretch">
                <div class="col-lg-4">
                    <div class="card shadow-sm cabinet-roi-form-card h-100">
                        <div class="card-header">
                            <h2 class="card-title mb-0">{{ __('Traffic forecast inputs title') }}</h2>
                        </div>
                        <div class="card-body">
                            <form>
                                <div class="cabinet-roi-field--cost">
                                    <label class="form-label" for="budget">{{ __('RK budget') }}</label>
                                    <input type="number" class="form-control input-lg" name="budget" id="budget"
                                           placeholder="{{ __('Costs in rubles') }}" required>
                                </div>
                                <div class="cabinet-roi-field--traffic">
                                    <label class="form-label" for="clickcost">{{ __('Average cost per click') }}</label>
                                    <input type="number" class="form-control input-lg" name="clickcost" id="clickcost"
                                           placeholder="{{ __('Cost per click in rubles') }}" required>
                                </div>
                                <div class="cabinet-roi-field--conv">
                                    <label class="form-label" for="convaction">{{ __('Conversion rate') }}</label>
                                    <input type="number" class="form-control input-lg" name="convaction" id="convaction"
                                           placeholder="{{ __('Percentage of targeted actions') }}">
                                </div>
                                <div class="cabinet-roi-field--conv">
                                    <label class="form-label" for="convsales">{{ __('Percentage of sales') }}</label>
                                    <input type="number" class="form-control input-lg" name="convsales" id="convsales"
                                           placeholder="{{ __('Percentage of sales') }}">
                                </div>
                                <div class="cabinet-roi-field--check">
                                    <label class="form-label" for="sredcheck">{{ __('Average check') }}</label>
                                    <input type="number" class="form-control input-lg" name="sredcheck" id="sredcheck"
                                           placeholder="{{ __('Average check of 1 purchase') }}">
                                </div>
                            </form>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-primary" id="go-prognoz">
                                    <i class="bi bi-graph-up" aria-hidden="true"></i>
                                    {{ __('Calculate') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="go-prreset">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    {{ __('Clear') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="cabinet-roi-metrics-wrap h-100">
                        <p class="cabinet-roi-metrics-wrap__title">{{ __('Traffic forecast metrics title') }}</p>
                        <div class="row g-2 boxes">
                            @foreach($arRoiTraff as $key => $roi)
                                @include('pages.partials.roi-metric-card', ['roi' => $roi, 'wide' => $key === 4])
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @foreach($arRoiTraff as $roi)
                <input type="hidden" id="rez-{{ $roi['id_value'] }}">
            @endforeach
        </section>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/roi/js/calc.js') }}?v={{ @filemtime(public_path('plugins/roi/js/calc.js')) ?: time() }}"></script>
        @php $demoRoi = \App\Support\DemoCabinet::isCurrentUser() ? \App\Support\DemoCabinet::roiCalculatorShowcase() : null; @endphp
        @if($demoRoi)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var demo = @json($demoRoi);
                ['zatrat', 'doxod', 'prosmotr', 'kliki', 'zayavka', 'pokupka'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el && demo[id] != null) {
                        el.value = demo[id];
                    }
                });
                var btn = document.getElementById('go-calc');
                if (btn) {
                    btn.click();
                }
            });
        </script>
        @endif
    @endslot
@endcomponent
