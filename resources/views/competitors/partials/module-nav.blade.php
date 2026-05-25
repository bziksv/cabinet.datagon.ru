<div class="card shadow-sm cabinet-ca-nav-card mb-3">
    <div class="card-header p-0">
        <ul class="nav nav-pills p-2 cabinet-ca-module-nav mb-0 flex-wrap">
            <li class="nav-item">
                <a href="{{ route('competitor.analysis') }}"
                   class="nav-link{{ ($active ?? '') === 'analyzer' ? ' active' : '' }}">{{ __('Analyzer') }}</a>
            </li>
            @if($admin ?? false)
                <li class="nav-item">
                    <a href="{{ route('competitor.config') }}"
                       class="nav-link{{ ($active ?? '') === 'config' ? ' active' : '' }}">{{ __('Module administration') }}</a>
                </li>
            @endif
        </ul>
    </div>
</div>
