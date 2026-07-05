<aside class="app-sidebar bg-body-secondary shadow cabinet-sidebar"
       data-bs-theme="dark"
       data-enable-persistence="true"
       data-sidebar-breakpoint="992">
    <div class="sidebar-brand">
        <a href="{{ route('home') }}" class="brand-link cabinet-brand">
            <img src="{{ asset('img/logo-icon.svg') }}" alt="Титло" class="brand-image opacity-75 shadow cabinet-brand__icon">
            <span class="brand-text fw-light">Титло</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        @auth
            @include('users.panel')
        @endauth
        @include('navigation.sidebar')
    </div>
</aside>
