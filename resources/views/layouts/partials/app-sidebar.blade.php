<aside class="app-sidebar bg-body-secondary shadow cabinet-sidebar" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('home') }}" class="brand-link cabinet-brand">
            <img src="{{ asset('img/logo-icon.svg') }}" alt="Датагон" class="brand-image opacity-75 shadow cabinet-brand__icon">
            <span class="brand-text fw-light">Датагон</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        @auth
            @include('users.panel')
        @endauth
        @include('navigation.sidebar')
    </div>
</aside>
