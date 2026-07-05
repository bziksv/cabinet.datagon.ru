@php
    $activeTab = $activeTab ?? 'list';
@endphp
<nav class="cabinet-mon-competitors-subnav" aria-label="{{ __('Monitoring competitors subnav label') }}">
    <a href="{{ route('monitoring.competitors', $project->id) }}"
       class="cabinet-mon-competitors-subnav__item{{ $activeTab === 'list' ? ' is-active' : '' }}">
        <i class="bi bi-list-check me-1" aria-hidden="true"></i>{{ __('Monitoring competitors subnav list') }}
    </a>
    <a href="{{ route('monitoring.competitors.positions', $project->id) }}"
       class="cabinet-mon-competitors-subnav__item{{ $activeTab === 'positions' ? ' is-active' : '' }}">
        <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>{{ __('Comparison with competitors') }}
    </a>
    <a href="{{ route('monitoring.competitors.dynamics', $project->id) }}"
       class="cabinet-mon-competitors-subnav__item{{ $activeTab === 'dynamics' ? ' is-active' : '' }}">
        <i class="bi bi-graph-up-arrow me-1" aria-hidden="true"></i>{{ __('Monitoring competitors subnav dynamics') }}
    </a>
</nav>
