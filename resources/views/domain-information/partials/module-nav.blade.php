<div class="card shadow-sm cabinet-mod-nav-card mb-3">
    <div class="card-header p-0">
        <ul class="nav nav-pills p-2 cabinet-mod-module-nav mb-0 flex-wrap">
            <li class="nav-item">
                <a href="{{ route('domain.information') }}"
                   class="nav-link{{ ($active ?? '') === 'projects' ? ' active' : '' }}">{{ __('Domain information tab') }}</a>
            </li>
            @if($admin ?? false)
                <li class="nav-item">
                    <a href="{{ route('domain.information.config') }}"
                       class="nav-link{{ ($active ?? '') === 'config' ? ' active' : '' }}">{{ __('Module administration') }}</a>
                </li>
            @endif
        </ul>
    </div>
</div>
