<div id="cabinet-ma-roles" class="cabinet-ma-roles">
    @foreach($roles as $role)
        @php
            $isProtected = in_array($role->name, $protectedRoles, true);
            $permCount = $role->permissions->count();
        @endphp
        <div class="cabinet-ma-role-card {{ $isProtected ? 'cabinet-ma-role-card--protected' : '' }}"
             data-role-name="{{ $role->name }}">
            <div class="cabinet-ma-role-head">
                <i class="bi bi-person-badge text-primary" aria-hidden="true"></i>
                <span class="fw-semibold flex-grow-1">{{ $role->name }}</span>
                <span class="badge text-bg-secondary">{{ $permCount }}</span>
                @unless($isProtected)
                    <div class="tools d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-link p-0 copy-item" data-copy="{{ $role->name }}"
                                title="{{ __('Copy slug') }}">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-link p-0 update-item" data-type="role" data-id="{{ $role->id }}"
                                data-name="{{ $role->name }}" title="{{ __('Edit') }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-link p-0 text-danger delete-item" data-type="role" data-id="{{ $role->id }}"
                                title="{{ __('Delete') }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                @endunless
            </div>
            <ul class="todo-list cabinet-ma-role-list"
                data-role="{{ $role->name }}"
                data-empty-label="{{ __('Drop permissions here') }}">
                @foreach($role->permissions as $permission)
                    <li class="cabinet-ma-assigned" data-id="{{ $permission->id }}" data-permission="{{ $permission->name }}">
                        <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                        <span class="cabinet-ma-item-label">{{ $permission->name }}</span>
                        <div class="tools">
                            <button type="button" class="revoke-permission" title="{{ __('Revoke') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
