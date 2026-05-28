@if($count < 1)
    <div class="cabinet-mon-v2-dash cabinet-mon-v2-dash--empty text-secondary small">
        {{ __('Monitoring v2 dash empty') }}
    </div>
@else
    <section class="cabinet-mon-v2-dash" id="cabinet-mon-v2-dashboard" aria-label="{{ __('Monitoring v2 dash title') }}">
        <div class="cabinet-mon-v2-dash__layout">
            <div class="cabinet-mon-v2-dash__main card border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mon-v2-dash__chart-head">
                        <div>
                            <h2 class="cabinet-mon-v2-dash__heading mb-0">{{ __('Monitoring v2 dash title') }}</h2>
                            <p class="small text-secondary mb-0">{{ __('Monitoring v2 dash subtitle') }}</p>
                        </div>
                        <div class="btn-group btn-group-sm cabinet-mon-v2-dash-view" role="group" aria-label="{{ __('Monitoring v2 dash chart mode') }}">
                            <button type="button" class="btn btn-outline-secondary active" data-dash-chart="leaders">{{ __('Monitoring v2 dash chart leaders') }}</button>
                            <button type="button" class="btn btn-outline-secondary" data-dash-chart="distribution">{{ __('Monitoring v2 dash chart distribution') }}</button>
                            <button type="button" class="btn btn-outline-secondary" data-dash-chart="portfolio">{{ __('Monitoring v2 dash chart portfolio') }}</button>
                        </div>
                        <div class="btn-group btn-group-sm cabinet-mon-v2-dash-metric d-none" id="cabinet-mon-v2-dash-metric" role="group" aria-label="{{ __('Monitoring v2 dash metric label') }}">
                            <button type="button" class="btn btn-outline-secondary active" data-dash-metric="top10">{{ __('TOP') }}‑10</button>
                            <button type="button" class="btn btn-outline-secondary" data-dash-metric="top30">{{ __('TOP') }}‑30</button>
                            <button type="button" class="btn btn-outline-secondary" data-dash-metric="middle">{{ __('Position') }}</button>
                        </div>
                    </div>
                    <div class="cabinet-mon-v2-dash-card__canvas cabinet-mon-v2-dash-card__canvas--hero">
                        <canvas id="cabinet-mon-v2-chart-main" height="280" aria-hidden="true"></canvas>
                    </div>
                </div>
            </div>
            <aside class="cabinet-mon-v2-dash__side">
                <div class="cabinet-mon-v2-dash-stat">
                    <span class="cabinet-mon-v2-dash-stat__value" data-dash="projects">{{ $count }}</span>
                    <span class="cabinet-mon-v2-dash-stat__label">{{ __('Projects count') }}</span>
                </div>
                <div class="cabinet-mon-v2-dash-stat cabinet-mon-v2-dash-stat--accent">
                    <span class="cabinet-mon-v2-dash-stat__value" data-dash="avgTop10">—</span>
                    <span class="cabinet-mon-v2-dash-stat__label">{{ __('Monitoring v2 dash avg top10') }}</span>
                </div>
                <div class="cabinet-mon-v2-dash-stat">
                    <span class="cabinet-mon-v2-dash-stat__value" data-dash="avgMiddle">—</span>
                    <span class="cabinet-mon-v2-dash-stat__label">{{ __('Monitoring v2 dash avg position') }}</span>
                </div>
                <div class="cabinet-mon-v2-dash-stat">
                    <span class="cabinet-mon-v2-dash-stat__value" data-dash="words">—</span>
                    <span class="cabinet-mon-v2-dash-stat__label">{{ __('Words') }}</span>
                </div>
                <div class="cabinet-mon-v2-dash-stat">
                    <span class="cabinet-mon-v2-dash-stat__value" data-dash="weak">—</span>
                    <span class="cabinet-mon-v2-dash-stat__label">{{ __('Monitoring v2 dash weak') }}</span>
                </div>
            </aside>
        </div>
        <p class="cabinet-mon-v2-dash__hint text-secondary small mb-0" id="cabinet-mon-v2-dash-hint">
            {{ __('Monitoring v2 dash hint all') }}
        </p>
    </section>
@endif
