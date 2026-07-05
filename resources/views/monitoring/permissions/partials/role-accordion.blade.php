@php
    $meta = $roleMeta[$role->name] ?? null;
    $enabled = $enabledCount ?? 0;
    $slug = Str::slug($role->name, '-');
@endphp
<div class="accordion-item cabinet-mon-perm-role" data-role="{{ $role->name }}">
    <h2 class="accordion-header" id="mon-perm-heading-{{ $role->id }}">
        <button class="accordion-button{{ $loop->first ? '' : ' collapsed' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mon-perm-collapse-{{ $role->id }}"
                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                aria-controls="mon-perm-collapse-{{ $role->id }}">
            <span class="d-flex flex-wrap align-items-center gap-2 w-100 pe-2">
                @if($meta)
                    <span class="badge text-bg-{{ $meta['badge'] }}">{{ __($meta['title_key']) }}</span>
                @else
                    <span class="badge text-bg-secondary">{{ $role->title ?: $role->name }}</span>
                @endif
                <span class="small text-secondary cabinet-mon-perm-role-count">
                    {{ __('Monitoring perm enabled count', ['enabled' => $enabled, 'total' => $permissions->count()]) }}
                </span>
                <span class="small text-success cabinet-mon-perm-role-saved d-none ms-auto">
                    <i class="bi bi-check-circle me-1"></i>{{ __('Monitoring perm role saved') }}
                </span>
            </span>
        </button>
    </h2>
    <div id="mon-perm-collapse-{{ $role->id }}"
         class="accordion-collapse collapse{{ $loop->first ? ' show' : '' }}"
         aria-labelledby="mon-perm-heading-{{ $role->id }}"
         data-bs-parent="#mon-perm-accordion">
        <div class="accordion-body">
            @if($meta)
                <p class="small mb-3">{{ __($meta['lead_key']) }}</p>
            @endif

            <div class="row g-3">
                @foreach($groupedPermissions as $group)
                    <div class="col-md-6">
                        <div class="cabinet-mon-perm-group h-100">
                            <h4 class="h6 mb-2 d-flex align-items-center gap-2">
                                <i class="bi {{ $group['icon'] }} text-primary" aria-hidden="true"></i>
                                {{ __($group['title_key']) }}
                            </h4>
                            <ul class="list-unstyled mb-0 cabinet-mon-perm-switch-list">
                                @foreach($group['permissions'] as $permission)
                                    @php($inputId = $slug . '-' . Str::slug($permission->name, '-'))
                                    <li class="cabinet-mon-perm-switch-item">
                                        <div class="form-check form-switch">
                                            <input type="checkbox"
                                                   class="form-check-input cabinet-mon-perm-switch"
                                                   name="permissions[{{ $role->name }}][{{ $permission->name }}]"
                                                   id="{{ $inputId }}"
                                                   value="1"
                                                   @if($role->hasPermissionTo($permission)) checked @endif>
                                            <label class="form-check-label" for="{{ $inputId }}">
                                                <span class="fw-semibold">{{ $permission->title }}</span>
                                                @if(!empty($permissionHints[$permission->name]))
                                                    <span class="d-block small text-secondary fw-normal">
                                                        {{ __($permissionHints[$permission->name]) }}
                                                    </span>
                                                @endif
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
