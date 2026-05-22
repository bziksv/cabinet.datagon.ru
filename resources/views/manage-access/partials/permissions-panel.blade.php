<div id="cabinet-ma-permissions">
    @foreach($permissionGroups as $group)
        <div class="cabinet-ma-group">
            <div class="cabinet-ma-group-title">{{ __($group['title']) }}</div>
            @if(!empty($group['hint']))
                <p class="text-secondary small mb-2">{{ __($group['hint']) }}</p>
            @endif
            <ul class="todo-list cabinet-ma-permission-pool" data-group="{{ $group['key'] }}">
                @foreach($group['items'] as $permission)
                    @php
                        $hintKey = $permissionHints[$permission->name] ?? null;
                    @endphp
                    <li data-id="{{ $permission->id }}" data-permission="{{ $permission->name }}">
                        <span class="handle" title="{{ __('Drag to role') }}">
                            <i class="bi bi-grip-vertical"></i>
                        </span>
                        <span class="cabinet-ma-item-label">
                            {{ $permission->name }}
                            @if($hintKey)
                                <span class="cabinet-ma-perm-hint d-block">{{ __($hintKey) }}</span>
                            @endif
                        </span>
                        <div class="tools">
                            <button type="button" class="btn btn-sm btn-link p-0 copy-item" data-copy="{{ $permission->name }}"
                                    title="{{ __('Copy name') }}">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-link p-0 update-item" data-type="permission" data-id="{{ $permission->id }}"
                                    data-name="{{ $permission->name }}" title="{{ __('Edit') }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-link p-0 text-danger delete-item" data-type="permission" data-id="{{ $permission->id }}"
                                    title="{{ __('Delete') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
