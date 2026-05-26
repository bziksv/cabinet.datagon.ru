@php
    $active = $active ?? 'users';
@endphp
@if($admin ?? false)
    <div class="card shadow-sm cabinet-partners-nav-card mb-0 border-0">
        <div class="card-header p-0 border-bottom-0 bg-transparent">
            <ul class="nav nav-pills p-2 cabinet-partners-nav flex-wrap gap-1 mb-0">
                <li class="nav-item">
                    <a href="{{ route('partners') }}"
                       class="nav-link{{ $active === 'users' ? ' active' : '' }}">
                        <i class="bi bi-people me-1" aria-hidden="true"></i>{{ __('Partners (users)') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('partners.admin') }}"
                       class="nav-link{{ $active === 'admin' ? ' active' : '' }}">
                        <i class="bi bi-gear me-1" aria-hidden="true"></i>{{ __('Partners (admins)') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('partners.add.group') }}"
                       class="nav-link{{ $active === 'add-group' ? ' active' : '' }}">
                        <i class="bi bi-folder-plus me-1" aria-hidden="true"></i>{{ __('Add group') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('partners.add.item') }}"
                       class="nav-link{{ $active === 'add-item' ? ' active' : '' }}">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>{{ __('Add partner') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>
@endif
