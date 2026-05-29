@php
    $iconBi = [
        'fas fa-home' => 'bi-grid-3x3-gap',
        'fas fa-user-secret' => 'bi-people',
        'fas fa-chart-pie' => 'bi-pie-chart',
        'fas fa-tasks' => 'bi-list-check',
        'fas fa-link' => 'bi-link-45deg',
    ];
@endphp
<nav class="cabinet-mon-show-shortcuts" aria-label="{{ __('Monitoring project shortcuts') }}">
    @foreach($navigations as $navigation)
        @php
            $href = trim((string) ($navigation['href'] ?? '#'));
            $disabled = $href === '' || $href === '#';
            $bi = $iconBi[$navigation['icon'] ?? ''] ?? 'bi-box-arrow-up-right';
            $labelByIcon = [
                'fas fa-home' => __('Projects'),
                'fas fa-user-secret' => __('My competitors'),
                'fas fa-chart-pie' => __('TOP-100 analysis'),
                'fas fa-tasks' => __('Site audit'),
                'fas fa-link' => __('Link tracking'),
            ];
            $iconKey = $navigation['icon'] ?? '';
            $label = $labelByIcon[$iconKey] ?? trim(strip_tags($navigation['content'] ?? ''));
            if ($label === '') {
                $label = trim(strip_tags($navigation['small'] ?? '')) ?: __('Open');
            }
            $count = $navigation['h3'] ?? null;
        @endphp
        @if($disabled)
            <span class="cabinet-mon-show-shortcuts__item is-disabled" title="{{ strip_tags($navigation['small'] ?? '') }}">
                <i class="bi {{ $bi }}" aria-hidden="true"></i>
                <span class="cabinet-mon-show-shortcuts__label">{{ $label }}</span>
            </span>
        @else
            <a href="{{ $href }}" class="cabinet-mon-show-shortcuts__item">
                <i class="bi {{ $bi }}" aria-hidden="true"></i>
                <span class="cabinet-mon-show-shortcuts__label">{{ $label }}</span>
                @if($count !== null && $count !== '')
                    <span class="cabinet-mon-show-shortcuts__badge">{{ $count }}</span>
                @endif
            </a>
        @endif
    @endforeach
</nav>
