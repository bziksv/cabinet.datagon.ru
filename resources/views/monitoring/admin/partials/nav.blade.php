@php
    $routeName = Route::currentRouteName();
    $active = $active ?? null;
    if ($active === null || $active === '') {
        $map = [
            'monitoring.index' => 'projects',
            'monitoring-v2.index' => 'projects',
            'monitoring.admin' => 'settings',
            'monitoring.stat' => 'stat',
            'monitoring-permissions.index' => 'permissions',
            'set.positions' => 'set_positions',
            'offset.positions' => 'offset_positions',
        ];
        $active = $map[$routeName] ?? '';
    }
    $items = [
        'projects' => [
            'route' => 'monitoring.index',
            'icon' => 'bi-grid-3x3-gap',
            'label' => __('Monitoring admin nav projects'),
        ],
        'settings' => [
            'route' => 'monitoring.admin',
            'icon' => 'bi-sliders',
            'label' => __('Monitoring admin nav settings'),
        ],
        'stat' => [
            'route' => 'monitoring.stat',
            'icon' => 'bi-list-task',
            'label' => __('Monitoring admin nav queues'),
        ],
        'permissions' => [
            'route' => 'monitoring-permissions.index',
            'icon' => 'bi-shield-lock',
            'label' => __('Monitoring admin nav permissions'),
        ],
        'set_positions' => [
            'route' => 'set.positions',
            'icon' => 'bi-plus-circle',
            'label' => __('Monitoring admin nav set positions'),
        ],
        'offset_positions' => [
            'route' => 'offset.positions',
            'icon' => 'bi-pencil-square',
            'label' => __('Monitoring admin nav offset positions'),
        ],
    ];
@endphp
<nav class="cabinet-mon-admin-nav card shadow-sm border-0 mb-3" aria-label="{{ __('Monitoring admin nav label') }}">
    <div class="card-body py-2 px-3">
        <ul class="nav nav-pills flex-wrap gap-1 mb-0">
            @foreach($items as $key => $item)
                <li class="nav-item">
                    <a class="nav-link{{ $active === $key ? ' active' : '' }}"
                       href="{{ route($item['route']) }}"
                       @if($active === $key) aria-current="page" @endif>
                        <i class="bi {{ $item['icon'] }} me-1" aria-hidden="true"></i>{{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>
