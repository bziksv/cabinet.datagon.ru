@php
    $tabs = [
        'popular' => ['icon' => 'bi-fire', 'label' => __('Popular')],
        'new' => ['icon' => 'bi-stars', 'label' => __('Latest ideas')],
        'mine' => ['icon' => 'bi-person', 'label' => __('My ideas')],
    ];
    if ($isStaff ?? false) {
        $tabs['moderation'] = ['icon' => 'bi-shield-check', 'label' => __('Moderation')];
    }
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <ul class="nav nav-pills cabinet-ideas-tabs flex-wrap gap-1 mb-0">
                @foreach($tabs as $code => $tab)
                    @php $active = ($filter ?? 'popular') === $code; @endphp
                    <li class="nav-item">
                        <a href="{{ route('ideas.index', array_filter(['tab' => $code === 'popular' ? null : $code, 'q' => $search ?? null])) }}"
                           class="nav-link {{ $active ? 'active' : '' }}">
                            <i class="bi {{ $tab['icon'] }} me-1" aria-hidden="true"></i>{{ $tab['label'] }}
                            @if($code === 'moderation' && ($pendingCount ?? 0) > 0)
                                <span class="badge text-bg-danger ms-1">{{ $pendingCount }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <form method="get" action="{{ route('ideas.index') }}" class="cabinet-ideas-search d-flex gap-2">
                @if(($filter ?? 'popular') !== 'popular')
                    <input type="hidden" name="tab" value="{{ $filter }}">
                @endif
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search"
                           name="q"
                           class="form-control"
                           value="{{ $search ?? '' }}"
                           placeholder="{{ __('Search ideas') }}…"
                           aria-label="{{ __('Search') }}">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Search') }}</button>
            </form>
        </div>
    </div>
</div>
