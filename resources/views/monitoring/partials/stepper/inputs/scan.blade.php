<p class="cabinet-mon-create-hint-step">{{ __('Monitoring v2 create step scan hint') }}</p>
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Monitoring v2 create scan card title') }}</h3>
            </div>
            <div class="card-body">
                @include('monitoring.partials.free-tariff-schedule-notice')

                @if($onFreeTariff ?? false)
                    <p class="small text-secondary mb-0">{{ __('Monitoring free tariff schedule manual only') }}</p>
                @else
                    <div class="callout callout-warning">
                        <p class="mb-2">{{ __('Monitoring v2 create scan schedule lead') }}</p>
                        <p class="mb-2">
                            {!! __('Monitoring v2 create scan schedule any mode') !!}
                        </p>
                        <ul class="mb-2 ps-3 small">
                            <li>{{ __('Monitoring v2 create scan mode times') }}</li>
                            <li>{{ __('Monitoring v2 create scan mode weeks') }}</li>
                            <li>{{ __('Monitoring v2 create scan mode months') }}</li>
                            <li>{{ __('Monitoring v2 create scan mode ranges') }}</li>
                        </ul>
                        <p class="mb-0 small text-secondary">{{ __('Monitoring v2 create scan schedule manual') }}</p>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Monitoring v2 create scan modes label') }}</label>
                        <select id="mode-scan" class="form-select">
                            <option value="times">{{ __('Monitoring v2 create scan mode times') }}</option>
                            <option value="months">{{ __('Monitoring v2 create scan mode months') }}</option>
                            <option value="weeks">{{ __('Monitoring v2 create scan mode weeks') }}</option>
                            <option value="ranges">{{ __('Monitoring v2 create scan mode ranges') }}</option>
                        </select>
                    </div>

                    <div class="" id="callout-info"></div>

                    <div class="mode-scan"></div>
                @endif
            </div>
        </div>
    </div>
</div>
