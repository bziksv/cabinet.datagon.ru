<div class="card shadow-sm mb-3 cabinet-ma-guide">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="callout callout-info h-100 mb-0">
                    <h5 class="mb-2"><i class="bi bi-shield-lock me-1"></i>{{ __('What is configured here') }}</h5>
                    <p class="small mb-0">
                        {{ __('Roles unite users. Permissions open sections of the cabinet (routes, buttons). Assign permissions to roles — users inherit them through their role.') }}
                    </p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-warning h-100 mb-0">
                    <h5 class="mb-2"><i class="bi bi-grid me-1"></i>{{ __('Do not confuse with module Access') }}</h5>
                    <p class="small mb-2">
                        <strong>{{ __('Permission') }} «{{ __('Main projects') }}»</strong> —
                        {{ __('who can open') }}
                        <a href="{{ route('main-projects.index') }}">/main-projects</a>
                        {{ __('(menu modules admin).') }}
                    </p>
                    <p class="small mb-0">
                        <strong>{{ __('Field Access') }}</strong> {{ __('in the module card on') }}
                        <a href="{{ route('main-projects.index') }}">/main-projects</a>
                        — {{ __('which roles see the item in the sidebar for all users. These are different levels.') }}
                    </p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-success h-100 mb-0">
                    <h5 class="mb-2"><i class="bi bi-arrows-move me-1"></i>{{ __('How to assign') }}</h5>
                    <p class="small mb-0">
                        {{ __('Drag a permission from the right column onto a role on the left. To remove — trash icon on the permission under the role. Role and permission names — Latin letters only.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
