<nav class="mt-2">
    <div class="js-toggle x-drop-down" data-qaid="dd_widget">
        <div class="x-drop-down__dropped">
            <div class="x-drop-down__list js-dropdown">
                <div class="x-drop-down__search">
                    <div class="x-input x-input_size_s">
                        <div class="input-group">
                            <input type="text"
                                   class="x-input__field form-control form-control-sidebar"
                                   autocomplete="off"
                                   placeholder="{{ __('Search') }}"
                                   value="">
                            <div class="input-group-append">
                                <button class="btn btn-sidebar">
                                    <i class="fas fa-search fa-fw"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column mt-3 cabinet-sidebar-menu" role="menu" style="min-height: 70vh; overflow-x: hidden; overflow-y: auto; padding-bottom: 50px;">
        @if(isset($modules))
            @foreach($modules as $key => $module)
                @if(!array_key_exists('configurationInfo', $module))
                    <li class="nav-item menu-item" data-id="{{ $module['id'] }}">
                        <a class="nav-link search-link" href="{{ $module['link'] }}">
                            <span>{!! $module['icon'] !!}
                                <span class="module-name">{{ $module['title'] }}
                                </span>
                            </span>
                        </a>
                    </li>
                @elseif(count($module) > 1)
                    <li class="folder nav-item has-treeview menu-item @if($module['configurationInfo']['show'] == 'true') menu-is-opening menu-open @endif"
                        data-action="{{ $module['configurationInfo']['show'] }}">
                        <a href="#" class="nav-link sidebar-folder-toggle">
                            <i class="fa-solid fa-folder"></i>
                            <p> {{ $key }} </p>
                        </a>
                        <ul class="nav nav-treeview"
                            @if($module['configurationInfo']['show'] == 'false') style="display: none;" @endif>
                            @foreach($module as $k => $elem)
                                @if($k === 'configurationInfo')
                                    @continue
                                @endif
                                <li class="nav-item" data-id="{{ $elem['id'] }}">
                                    <a class="nav-link search-link" href="{{ $elem['link'] }}">
                                        <span>{!! $elem['icon'] !!}
                                            <span class="module-name">{{ $elem['title'] }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            @endforeach
            <li class="nav-item menu-item">
                <a class="nav-link search-link" href="{{ route('partners') }}">
                    <span>
                        <i class="fa fa-handshake" style="color: white"></i>
                        <span class="module-name"> {{ __('Partners') }}</span>
                    </span>
                </a>
            </li>
        @else
            <li class="nav-item menu-item">
                <a class="nav-link search-link" href="/login">
                    <span>
                        <i class="fa fa-users"></i>
                        <span class="module-name"> {{ __('Login page') }}</span>
                    </span>
                </a>
            </li>
        @endif
        {{-- Контроллер с CRUD DescriptionProjectForAdminController--}}
    </ul>
</nav>
