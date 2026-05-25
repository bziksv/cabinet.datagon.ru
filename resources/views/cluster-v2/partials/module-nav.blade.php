@php
    $active = $active ?? 'analyzer';
@endphp
<div class="card shadow-sm cabinet-cluster-v2-nav-card mb-3">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-pills p-2 cabinet-cluster-v2-module-nav mb-0 flex-wrap">
            <li class="nav-item">
                <a href="{{ route('cluster.v2') }}"
                   class="nav-link{{ $active === 'analyzer' ? ' active' : '' }}">
                    {{ __('Analyzer') }}
                    <span class="badge text-bg-primary ms-1">v2</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cluster.projects') }}"
                   class="nav-link">{{ __('My projects') }}</a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cluster') }}"
                   class="nav-link text-secondary">{{ __('Classic UI') }}</a>
            </li>
            @if($admin ?? false)
                <li class="nav-item">
                    <a href="{{ route('cluster.configuration') }}"
                       class="nav-link">{{ __('Module administration') }}</a>
                </li>
            @endif
        </ul>
    </div>
</div>
