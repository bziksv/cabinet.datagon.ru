{{-- Модалка безвозвратного удаления проекта SEO-чеклиста --}}
<div class="modal fade" id="cabinetScDeleteProjectModal" tabindex="-1" aria-labelledby="cabinetScDeleteProjectModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cabinetScDeleteProjectModalTitle">{{ __('Delete checklist') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" data-sc-delete-project-lead>
                    {{ __('Delete this SEO checklist permanently?') }}
                </p>
                <p class="mb-0 small text-secondary">
                    <span data-sc-delete-project-domain></span>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <form method="post" data-sc-delete-project-form class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm" data-sc-delete-project-submit>
                        {{ __('Delete permanently') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
