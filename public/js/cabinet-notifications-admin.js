(function () {
    'use strict';

    var page = document.querySelector('.cabinet-notifications-admin-page');
    if (!page) {
        return;
    }

    var testTelegramUrl = page.getAttribute('data-test-telegram-url');
    var testEmailUrl = page.getAttribute('data-test-email-url');
    var previewModalBase = page.getAttribute('data-preview-modal-url');
    var csrf = document.querySelector('meta[name="csrf-token"]');

    if (typeof toastr !== 'undefined') {
        toastr.options = { closeButton: true, timeOut: 6000, progressBar: true };
    }

    function notify(type, message) {
        if (typeof toastr !== 'undefined') {
            toastr[type](message);
        } else {
            alert(message);
        }
    }

    function postJson(url, body, btn) {
        if (btn) {
            btn.disabled = true;
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            }).catch(function () {
                return { ok: false, data: { message: 'Invalid response' } };
            });
        }).finally(function () {
            if (btn) {
                btn.disabled = false;
            }
        });
    }

    page.querySelectorAll('.cabinet-notify-btn-test-tg').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var eventId = btn.getAttribute('data-event-id');
            postJson(testTelegramUrl, { event_id: eventId }, btn).then(function (result) {
                if (result.ok && result.data.ok) {
                    notify('success', result.data.message || 'OK');
                } else {
                    notify('error', (result.data && result.data.message) || 'Error');
                }
            });
        });
    });

    page.querySelectorAll('.cabinet-notify-btn-test-email').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!window.confirm(document.documentElement.lang === 'ru'
                ? 'Отправить тестовое письмо на ваш email аккаунта?'
                : 'Send test email to your account address?')) {
                return;
            }
            var eventId = btn.getAttribute('data-event-id');
            postJson(testEmailUrl, { event_id: eventId }, btn).then(function (result) {
                if (result.ok && result.data.ok) {
                    notify('success', result.data.message || 'OK');
                } else {
                    notify('error', (result.data && result.data.message) || 'Error');
                }
            });
        });
    });

    page.querySelectorAll('.cabinet-notify-btn-preview-modal').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var eventId = btn.getAttribute('data-event-id');
            var modalEl = document.getElementById('cabinetNotifyPreviewModal');
            var titleEl = document.getElementById('cabinetNotifyPreviewModalTitle');
            var bodyEl = document.getElementById('cabinetNotifyPreviewModalBody');
            if (!modalEl || !titleEl || !bodyEl) {
                return;
            }

            btn.disabled = true;
            fetch(previewModalBase + '/' + encodeURIComponent(eventId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data.ok) {
                    notify('error', data.message || 'Error');
                    return;
                }
                titleEl.textContent = data.title || '';
                bodyEl.innerHTML = data.html || '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            }).catch(function () {
                notify('error', 'Preview failed');
            }).finally(function () {
                btn.disabled = false;
            });
        });
    });

    var searchInput = page.querySelector('.cabinet-notify-table-search');
    var table = document.getElementById('cabinetNotifyTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function () {
            var q = searchInput.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = !q || row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
})();
