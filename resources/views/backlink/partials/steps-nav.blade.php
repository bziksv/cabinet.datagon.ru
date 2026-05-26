@php
    $steps = $steps ?? [];
    $navLabel = $navLabel ?? '';
@endphp
<nav class="cabinet-bl-steps-nav" aria-label="{{ $navLabel }}">
    <ol class="cabinet-bl-steps-nav__list list-unstyled mb-0">
        @foreach($steps as $step)
            <li class="cabinet-bl-steps-nav__item{{ !empty($step['active']) ? ' is-active' : '' }}">
                @if(!empty($step['anchor']))
                    <a href="#{{ $step['anchor'] }}" class="cabinet-bl-steps-nav__link text-decoration-none">
                        <span aria-hidden="true">{{ $step['n'] }}</span>
                        {{ $step['title'] }}
                    </a>
                @else
                    <span class="cabinet-bl-steps-nav__link">
                        <span aria-hidden="true">{{ $step['n'] }}</span>
                        {{ $step['title'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
