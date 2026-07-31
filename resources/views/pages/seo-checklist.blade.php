@component('component.card', [
    'title' => __('SEO Checklist'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page">
        <div class="cabinet-sc-hero">
            <div>
                <p class="cabinet-sc-hero__eyebrow">{{ __('SEO project workflow') }}</p>
                <h1 class="cabinet-sc-hero__title">{{ __('SEO Checklist') }}</h1>
                <p class="cabinet-sc-hero__lead">{{ __('SEO checklist lead') }}</p>
            </div>
            @if(count($availableDomains) > 0)
                <form method="post" action="{{ route('pages.seo-checklist.store') }}" class="cabinet-sc-create">
                    @csrf
                    <label class="cabinet-sc-create__label" for="cabinet-sc-domain">{{ __('Create checklist for site') }}</label>
                    <div class="cabinet-sc-create__row">
                        <select name="domain" id="cabinet-sc-domain" class="form-select form-select-sm" required>
                            <option value="">{{ __('Choose a site') }}…</option>
                            @foreach($availableDomains as $domain)
                                <option value="{{ $domain }}">{{ $domain }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Create') }}</button>
                    </div>
                </form>
            @endif
        </div>

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        @if($projects->isEmpty())
            <div class="cabinet-sc-empty">
                <i class="bi bi-clipboard-check display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No SEO checklists yet') }}</p>
                <p class="small text-secondary mb-0">{{ __('No SEO checklists yet hint') }}</p>
            </div>
        @else
            <div class="cabinet-sc-grid">
                @foreach($projects as $project)
                    @php
                        $pct = $project->progress_total > 0
                            ? (int) round(100 * $project->progress_done / $project->progress_total)
                            : 0;
                    @endphp
                    <a href="{{ route('pages.seo-checklist.show', ['id' => $project->id]) }}" class="cabinet-sc-card">
                        <div class="cabinet-sc-card__top">
                            <strong class="cabinet-sc-card__domain">{{ $project->domain }}</strong>
                            <span class="cabinet-sc-card__pct">{{ $pct }}%</span>
                        </div>
                        <div class="cabinet-sc-card__bar" aria-hidden="true">
                            <span style="width: {{ $pct }}%"></span>
                        </div>
                        <div class="cabinet-sc-card__meta text-secondary small">
                            {{ $project->progress_done }}/{{ $project->progress_total }}
                            ·
                            {{ $project->last_activity_at ? $project->last_activity_at->format('d.m.Y H:i') : '—' }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endcomponent
