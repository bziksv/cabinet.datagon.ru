<div class="cabinet-ideas-hero card border-0 shadow-sm mb-3">
    <div class="card-body p-4 p-lg-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="cabinet-ideas-hero__eyebrow text-uppercase small mb-2">{{ __('Product feedback') }}</p>
                <h1 class="h3 mb-2 d-flex align-items-center flex-wrap gap-1">
                    <i class="bi bi-lightbulb-fill text-warning me-2" aria-hidden="true"></i>
                    <span>{{ __('Suggest an idea / Vote') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-ideas'])
                </h1>
                <p class="text-secondary mb-0 pe-lg-4">
                    {{ __('Share what would make the cabinet better. Ideas are reviewed by the team, then everyone can vote for the best ones.') }}
                </p>
            </div>
            <div class="col-lg-5">
                <div class="row g-2 cabinet-ideas-hero__stats">
                    <div class="col-4">
                        <div class="cabinet-ideas-stat-tile">
                            <span class="cabinet-ideas-stat-tile__value">{{ $stats['published'] }}</span>
                            <span class="cabinet-ideas-stat-tile__label">{{ __('Published ideas') }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="cabinet-ideas-stat-tile">
                            <span class="cabinet-ideas-stat-tile__value">{{ $stats['votes_total'] }}</span>
                            <span class="cabinet-ideas-stat-tile__label">{{ __('Votes') }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="cabinet-ideas-stat-tile">
                            <span class="cabinet-ideas-stat-tile__value">{{ $stats['mine'] }}</span>
                            <span class="cabinet-ideas-stat-tile__label">{{ __('Yours') }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('ideas.create') }}" class="btn btn-primary w-100 mt-3">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('Suggest an idea') }}
                </a>
            </div>
        </div>
    </div>
</div>
