@component('component.card', [
    'title' => __('Setting the order of menu items'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-configuration-menu.css') }}?v={{ @filemtime(public_path('css/cabinet-configuration-menu.css')) ?: time() }}">
    @endslot

    <div
        id="cabinetMenuConfigRoot"
        class="cabinet-menu-config-page"
        data-save-url="{{ route('configuration.menu') }}"
        data-restore-url="{{ route('restore.configuration.menu') }}"
        data-csrf="{{ csrf_token() }}"
        data-msg-success="{{ __('Menu configuration saved') }}"
        data-msg-restore-success="{{ __('Menu configuration restored') }}"
        data-msg-saving="{{ __('Menu configuration saving') }}"
        data-msg-restoring="{{ __('Menu configuration restoring') }}"
        data-msg-error="{{ __('Error') }}"
        data-msg-empty-group="{{ __('The name of the group cannot be empty') }}"
        data-msg-duplicate-group="{{ __('A group with that name already exists') }}"
        data-msg-group-exists="{{ __('A group with that name already exists') }}"
        data-empty-hint="{{ __('Drag modules here or create a group') }}"
        data-tip-group-expand="{{ __('Hide or show group in the menu') }}"
        data-tip-panel-toggle="{{ __('Expand or collapse group list') }}"
        data-label-change="{{ __('Change') }}"
        data-label-cancel="{{ __('Cancel') }}"
    >
        <p class="cabinet-menu-config-lead mb-2">
            {{ __('Menu configuration lead') }}
        </p>

        <div class="row cabinet-menu-config-layout g-4">
            <div class="col-lg-8">
                <div class="cabinet-menu-config-board">
                    <div class="cabinet-menu-config-board__head">
                        <h2 class="cabinet-menu-config-board__title">{{ __('Menu structure') }}</h2>
                        <p class="cabinet-menu-config-board__hint">{{ __('Drag items and groups to reorder') }}</p>
                    </div>
                    @include('positions.partials.menu-configuration-tree', ['items' => $items])
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="cabinet-menu-config-sidebar">
                    <div class="card shadow-sm cabinet-menu-config-actions">
                        <div class="card-header">
                            <h3 class="card-title mb-0 h6">{{ __('Actions') }}</h3>
                        </div>
                        <div class="card-body d-grid gap-2">
                            <div id="menuConfigStatus" class="alert alert-secondary py-2 px-3 small mb-0 d-none" role="status" aria-live="polite"></div>
                            <button type="button" class="btn btn-success" id="saveChanges">
                                <span class="cabinet-menu-config-btn__idle"><i class="bi bi-check-lg" aria-hidden="true"></i>{{ __('Save Changes') }}</span>
                                <span class="cabinet-menu-config-btn__busy d-none"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>{{ __('Menu configuration saving') }}</span>
                                <span class="cabinet-menu-config-btn__done d-none"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('Menu configuration saved') }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addNewDir">
                                <i class="bi bi-folder-plus" aria-hidden="true"></i>{{ __('Create a group') }}
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetAllChanges">
                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>{{ __('Return the standard layout') }}
                            </button>
                        </div>
                    </div>

                    <div class="card shadow-sm mt-3">
                        <div class="card-body cabinet-menu-config-tips">
                            <p class="fw-semibold text-body mb-2">{{ __('Tips') }}</p>
                            <ul class="mb-0 ps-3">
                                <li>{{ __('Menu configuration tip drag') }}</li>
                                <li>{{ __('Menu configuration tip eye') }}</li>
                                <li>{{ __('Menu configuration tip chevron') }}</li>
                                <li>{{ __('Menu configuration tip save') }}</li>
                                <li>{{ __('Menu configuration tip sidebar refresh') }}</li>
                            </ul>
                            <a href="{{ route('menu.config', ['refresh_sidebar' => 1]) }}" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Menu configuration refresh sidebar') }}
                            </a>
                            @if(!empty($sidebarMenuStale))
                                <p class="small text-success mb-0 mt-2">{{ __('Menu configuration sidebar refreshed') }}</p>
                            @endif
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
            <div class="toast align-items-center text-bg-success border-0 toast-success hide" role="alert" aria-live="polite" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body success-msg"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <div class="toast align-items-center text-bg-danger border-0 toast-error hide" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body error-msg"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <div class="modal fade" id="addNewDir" tabindex="-1" aria-labelledby="addNewDirLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addNewDirLabel">{{ __('Enter the name of the group') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="dir">{{ __('Group name') }}</label>
                    <input type="text" class="form-control" name="dir" id="dir" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="createDirectory">{{ __('Add') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="removeModal" tabindex="-1" aria-labelledby="removeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeModalLabel">{{ __('Deleting a group') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">{{ __('All menu items located in it will be automatically taken out.') }}</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button id="removeSelectedBlock" type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Remove') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetAllChanges" tabindex="-1" aria-labelledby="resetAllChangesLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetAllChangesLabel">{{ __('You can restore the default values') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{ __('If you return the default values, the order of the menu items will be determined by the administrators.') }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button id="restore" type="button" class="btn btn-danger">
                        <span class="cabinet-menu-config-restore__idle">{{ __('Restore standard sorting') }}</span>
                        <span class="cabinet-menu-config-restore__busy d-none"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>{{ __('Menu configuration restoring') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>{{-- #cabinetMenuConfigRoot --}}

    @slot('js')
        <script src="{{ asset('plugins/sortable/sortable.min.js') }}"></script>
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-configuration-menu.js') }}?v={{ @filemtime(public_path('js/cabinet-configuration-menu.js')) ?: time() }}"></script>
        <script>
            if (window.toastr) {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-right',
                    timeOut: 5000,
                    preventDuplicates: true,
                };
            }
        </script>
    @endslot
@endcomponent
