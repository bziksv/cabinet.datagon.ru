<div class="cabinet-notify-preview-modal">
    <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">
            <i class="bi bi-telegram text-info me-2" aria-hidden="true"></i>
            {{ __('Connect Telegram bot') }}
        </h5>
    </div>
    <div class="modal-body pt-2">
        <p class="mb-3">
            {{ __('Connect our Telegram bot so you do not miss project updates: statuses, monitoring alerts, and other important events.') }}
        </p>
        <ul class="list-unstyled small text-secondary mb-0">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Project and task status notifications') }}</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Monitoring: domain issues, limits, DNS changes') }}</li>
            <li><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Clustering and analysis completion alerts') }}</li>
        </ul>
    </div>
    <div class="modal-footer border-0 flex-wrap gap-2 justify-content-between">
        <button type="button" class="btn btn-outline-secondary" disabled>{{ __('Remind me in 2 weeks') }}</button>
        <a href="{{ $telegramBotSubscribeUrl ?? '#' }}" class="btn btn-primary" target="_blank" rel="noopener">
            <i class="bi bi-telegram me-1"></i>{{ __('Subscribe to notifications') }}
        </a>
    </div>
</div>
