<div class="cabinet-home-hero p-4 p-md-5 mb-3">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h1 class="h3 mb-2">
                {{ __('Welcome') }}, <span class="text-primary">{{ $summary['displayName'] }}</span>
            </h1>
            <p class="text-secondary mb-0">
                {{ __('Your workspace for SEO tools: pick a module below or use the sidebar menu.') }}
            </p>
        </div>
        <div class="col-lg-4">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <a href="{{ route('profile.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-person me-1"></i>{{ __('Profile') }}
                </a>
                <a href="{{ route('menu.config') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-sliders me-1"></i>{{ __('Setting menu') }}
                </a>
            </div>
        </div>
    </div>
</div>
