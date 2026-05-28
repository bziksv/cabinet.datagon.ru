@php
    use App\Support\MenuConfigurationDomId;
    $emptyHint = $emptyHint ?? __('Drag modules here or create a group');
@endphp

<ol class="nested_with_switch vertical cabinet-menu-config-tree" id="cabinetMenuConfigTree">
    @foreach($items as $key => $item)
        @if(array_key_exists('configurationInfo', $item))
            @php $groupId = MenuConfigurationDomId::fromGroupName($key); @endphp
            <li data-name="{{ $key }}" class="cabinet-menu-config-group-wrap" data-action="dir">
                <div class="card cabinet-menu-config-group shadow-sm mb-0">
                    <div class="card-header d-flex flex-wrap align-items-start gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <div class="cabinet-menu-config-group__title text-truncate" data-group-drag-handle title="{{ __('Drag group') }}">{{ $key }}</div>
                            <div class="cabinet-menu-config-group__rename">
                                <input type="text" class="form-control form-control-sm" value="{{ $key }}" data-group-rename-input>
                                <button type="button" class="btn btn-sm btn-primary change-group-name">{{ __('Change') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-group-rename-cancel>{{ __('Cancel') }}</button>
                            </div>
                        </div>
                        <div class="cabinet-menu-config-group__tools btn-group btn-group-sm flex-shrink-0" role="group" aria-label="{{ __('Group actions') }}">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-menu-panel-toggle
                                aria-expanded="{{ $item['configurationInfo']['show'] === 'true' ? 'true' : 'false' }}"
                                aria-controls="{{ $groupId }}"
                                title="{{ __('Expand or collapse group list') }}"
                            >
                                <i class="bi bi-chevron-down cabinet-menu-config-chevron" aria-hidden="true"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-menu-sidebar-toggle
                                data-action="{{ $item['configurationInfo']['show'] }}"
                                title="{{ __('Hide or show group in the menu') }}"
                            >
                                <i class="bi bi-eye{{ $item['configurationInfo']['show'] === 'true' ? '' : '-slash' }}"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary edit-dir-name" title="{{ __('Edit the group name') }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-danger remove-dir"
                                data-bs-toggle="modal"
                                data-bs-target="#removeModal"
                                title="{{ __('Delete a group') }}"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <ol
                    class="for-nest cabinet-menu-config-group__list @if($item['configurationInfo']['show'] === 'true') show @endif"
                    id="{{ $groupId }}"
                    data-empty-hint="{{ $emptyHint }}"
                >
                    @foreach($item as $k => $elem)
                        @if($k === 'configurationInfo')
                            @continue
                        @endif
                        <li class="moved-item" data-id="{{ $elem['id'] }}" data-name="{{ $elem['title'] }}">
                            <span class="handle ui-sortable-handle" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                            <span class="cabinet-menu-config-item__label">{{ __($elem['title']) }}</span>
                        </li>
                    @endforeach
                </ol>
            </li>
        @else
            <li class="moved-item cabinet-menu-config-item--solo" data-id="{{ $item['id'] }}" data-name="{{ $item['title'] }}">
                <span class="handle ui-sortable-handle" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                <span class="cabinet-menu-config-item__label">{{ __($item['title']) }}</span>
            </li>
        @endif
    @endforeach
</ol>
