<section class="card shadow-sm border-0 mb-3 cabinet-mon-admin-section" id="mon-admin-section-{{ $sectionKey }}">
    <div class="card-header bg-white py-3">
        <h3 class="h6 mb-1 d-flex align-items-center gap-2">
            <i class="bi {{ $section['icon'] ?? 'bi-gear' }} text-primary" aria-hidden="true"></i>
            {{ __($section['title_key']) }}
        </h3>
        @if(!empty($section['lead_key']))
            <p class="small text-secondary mb-0">{{ __($section['lead_key']) }}</p>
        @endif
    </div>
    <div class="card-body pt-2">
        <div class="row g-2">
            @foreach($section['fields'] as $field)
                @include('monitoring.admin.settings.field', ['field' => $field, 'values' => $values])
            @endforeach
        </div>
    </div>
</section>
