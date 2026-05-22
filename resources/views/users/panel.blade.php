<div class="sidebar-user-panel cabinet-user-panel mb-2">
    <div class="d-flex align-items-center gap-2">
        <img src="{{ $user->image }}" class="rounded-circle shadow cabinet-user-panel__avatar" alt="{{ $user->fullName }}">
        <div class="info flex-grow-1 min-w-0">
            <a href="{{ route('profile.index') }}" class="d-block text-truncate text-white fw-semibold">{{ $user->fullName }}</a>
        </div>
        @if(!empty($adminMenuItems))
            <div class="cabinet-admin-gear flex-shrink-0">
                <button type="button"
                        class="btn btn-sm btn-outline-light border-0 cabinet-admin-gear__toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="cabinet-admin-gear-menu"
                        title="{{ __('Administration') }}">
                    <i class="bi bi-gear" aria-hidden="true"></i>
                </button>
                <div id="cabinet-admin-gear-menu"
                     class="cabinet-admin-gear__menu"
                     role="menu">
                    @foreach($adminMenuItems as $item)
                        <a class="cabinet-admin-gear__link"
                           role="menuitem"
                           href="{{ $item['link'] }}"
                           @if($item['external']) target="_blank" rel="noopener noreferrer" @endif>
                            {{ $item['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
