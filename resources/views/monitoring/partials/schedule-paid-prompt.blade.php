@if($showMonitoringSchedulePaidPrompt ?? false)
    <div class="modal fade" id="cabinet-monitoring-schedule-paid-modal" tabindex="-1"
         aria-labelledby="cabinet-monitoring-schedule-paid-title" aria-hidden="true"
         data-snooze-url="{{ route('profile.monitoring-schedule-paid-prompt.snooze') }}">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cabinet-monitoring-schedule-paid-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="cabinet-monitoring-schedule-paid-title">
                        <i class="bi bi-calendar-x text-warning me-2" aria-hidden="true"></i>
                        {{ __('Monitoring schedule paid prompt title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="mb-3">
                        {{ __('Monitoring schedule paid prompt body') }}
                    </p>
                    <ul class="list-unstyled small text-secondary mb-0 cabinet-monitoring-schedule-paid-modal__list">
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2" aria-hidden="true"></i>
                            {{ __('Monitoring schedule paid prompt manual') }}
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2" aria-hidden="true"></i>
                            {{ __('Monitoring schedule paid prompt saved') }}
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill text-success me-2" aria-hidden="true"></i>
                            {{ __('Monitoring schedule paid prompt upgrade') }}
                        </li>
                    </ul>
                </div>
                <div class="modal-footer border-0 flex-wrap gap-2 justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" id="cabinet-monitoring-schedule-paid-snooze">
                        {{ __('Monitoring schedule paid prompt snooze') }}
                    </button>
                    <a href="{{ route('tariff.index') }}" class="btn btn-primary">
                        {{ __('Monitoring schedule paid prompt tariffs') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            function snoozeMonitoringSchedulePaidPrompt(callback) {
                var modalEl = document.getElementById('cabinet-monitoring-schedule-paid-modal');
                if (!modalEl) {
                    return;
                }
                var url = modalEl.getAttribute('data-snooze-url');
                var token = document.querySelector('meta[name="csrf-token"]');
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('snooze failed');
                    }
                    return response.json().catch(function () {
                        return {};
                    });
                }).then(function () {
                    if (typeof callback === 'function') {
                        callback();
                    }
                }).catch(function () {
                    if (snoozeBtn) {
                        snoozeBtn.disabled = false;
                    }
                });
            }

            var snoozeBtn;

            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('cabinet-monitoring-schedule-paid-modal');
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return;
                }

                if (window.CabinetModalQueue) {
                    window.CabinetModalQueue.enqueue(modalEl, 20);
                } else {
                    bootstrap.Modal.getOrCreateInstance(modalEl, {backdrop: true, keyboard: true}).show();
                }

                snoozeBtn = document.getElementById('cabinet-monitoring-schedule-paid-snooze');
                if (snoozeBtn) {
                    snoozeBtn.addEventListener('click', function () {
                        snoozeBtn.disabled = true;
                        snoozeMonitoringSchedulePaidPrompt(function () {
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) {
                                modal.hide();
                            }
                        });
                    });
                }
            });
        })();
    </script>
@endif
