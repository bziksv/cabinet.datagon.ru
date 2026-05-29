<section class="cabinet-mon-project-kpis is-loading" id="cabinetMonProjectKpis" aria-label="{{ __('Monitoring show kpi strip') }}" aria-busy="true">
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top1') }}</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top1">—</span>
        <span class="cabinet-mon-project-kpi__delta" data-kpi-delta="top1"></span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top3') }}</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top3">—</span>
        <span class="cabinet-mon-project-kpi__delta" data-kpi-delta="top3"></span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top10') }}</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top10">—</span>
        <span class="cabinet-mon-project-kpi__delta" data-kpi-delta="top10"></span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top30') }}</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top30">—</span>
        <span class="cabinet-mon-project-kpi__delta" data-kpi-delta="top30"></span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top100') }}</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top100">—</span>
        <span class="cabinet-mon-project-kpi__delta" data-kpi-delta="top100"></span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi avg position') }}</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="middle">—</span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi keywords') }}</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="words">—</span>
    </article>
    <article class="cabinet-mon-project-kpi cabinet-mon-project-kpi--muted">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi snapshot') }}</span>
        <span class="cabinet-mon-project-kpi__value cabinet-mon-project-kpi__value--small" data-kpi="snapshot_at">—</span>
        <span class="cabinet-mon-project-kpi__hint text-secondary" data-kpi-hint="snapshot"></span>
    </article>
    <div class="cabinet-mon-project-kpis__loader" id="cabinetMonProjectKpisLoader">
        @include('monitoring.partials.show.loader', ['label' => __('Monitoring show kpi loading'), 'size' => 'sm'])
    </div>
</section>
