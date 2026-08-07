{{-- Shared Bootstrap confirm modal (see public/js/cabinet-confirm-modal.js) --}}
<div class="modal fade" id="cabinet-confirm-modal" tabindex="-1" aria-labelledby="cabinet-confirm-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cabinet-confirm-title">Подтвердите действие</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" data-cabinet-confirm-text></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-cabinet-confirm-cancel>Отмена</button>
                <button type="button" class="btn btn-primary" data-cabinet-confirm-ok>Подтвердить</button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/cabinet-confirm-modal.js') }}?v={{ @filemtime(public_path('js/cabinet-confirm-modal.js')) ?: time() }}"></script>
