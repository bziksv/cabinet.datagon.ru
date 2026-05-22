<div class="user-panel cabinet-user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
    <div class="image">
        <img src="{{ $user->image }}" class="img-circle elevation-2" alt="{{ $user->fullName }}">
    </div>
    <div class="info d-flex align-items-center flex-grow-1 min-width-0">
        <a href="{{ route('profile.index') }}" class="d-block text-truncate">{{ $user->fullName }}</a>
    </div>
    @if(!empty($adminMenuItems))
        <div class="dropdown cabinet-admin-gear flex-shrink-0">
            <a href="#"
               class="cabinet-admin-gear__toggle"
               data-toggle="dropdown"
               aria-haspopup="true"
               aria-expanded="false"
               title="{{ __('Administration') }}">
                <i class="fas fa-cog" aria-hidden="true"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right cabinet-admin-gear__menu">
                @foreach($adminMenuItems as $item)
                    <a class="dropdown-item"
                       href="{{ $item['link'] }}"
                       @if($item['external']) target="_blank" rel="noopener noreferrer" @endif>
                        {{ $item['title'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
