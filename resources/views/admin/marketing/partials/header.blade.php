{{-- Шапка раздела «Маркетинг сайта» + вкладки. $active: plan|content --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
        <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
            <i class="bi bi-megaphone text-primary" aria-hidden="true"></i>
            <span>{{ __('Site marketing') }}</span>
            @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-marketing'])
        </h2>
        <p class="text-secondary small mb-0" style="max-width: 44rem;">{{ __('Site marketing lead') }}</p>
    </div>
    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{!! $actions !!}</div>
    @endisset
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ ($active ?? '') === 'plan' ? 'active' : '' }}" href="{{ route('admin.marketing.index') }}">
            <i class="bi bi-kanban me-1" aria-hidden="true"></i>{{ __('Marketing plan') }}
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($active ?? '') === 'content' ? 'active' : '' }}" href="{{ route('admin.marketing.content') }}">
            <i class="bi bi-journal-text me-1" aria-hidden="true"></i>{{ __('Marketing content plan') }}
        </a>
    </li>
</ul>
