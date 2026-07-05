/**
 * /monitoring/permissions — автосохранение матрицы ролей.
 */
(function ($, window) {
    'use strict';

    const cfg = window.cabinetMonitoringPermissionsConfig;
    if (!cfg || !cfg.saveUrl) {
        return;
    }

    let saveTimer = null;
    let saveInFlight = false;
    let saveQueued = false;

    function updateRoleCount($roleItem) {
        const total = $roleItem.find('.cabinet-mon-perm-switch').length;
        const enabled = $roleItem.find('.cabinet-mon-perm-switch:checked').length;
        const template = cfg.i18n.enabledCount || ':enabled / :total';
        $roleItem.find('.cabinet-mon-perm-role-count').text(
            template.replace(':enabled', enabled).replace(':total', total)
        );
    }

    function flashRoleSaved($roleItem) {
        const $badge = $roleItem.find('.cabinet-mon-perm-role-saved');
        $badge.removeClass('d-none');
        window.setTimeout(function () {
            $badge.addClass('d-none');
        }, 2000);
    }

    function setStatus(text) {
        $('#mon-perm-save-status').text(text || '');
    }

    function saveForm() {
        if (saveInFlight) {
            saveQueued = true;
            return;
        }

        const $form = $('#mon-perm-form');
        if (!$form.length) {
            return;
        }

        saveInFlight = true;
        setStatus(cfg.i18n.saving);
        $form.find('.cabinet-mon-perm-switch').prop('disabled', true);

        const data = $form.serialize();

        const request = typeof axios !== 'undefined'
            ? axios.post(cfg.saveUrl, data)
            : fetch(cfg.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: data,
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json().then(function (json) {
                    return {data: json};
                });
            });

        Promise.resolve(request)
            .then(function (response) {
                if (response.data && response.data.status) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.data.message || cfg.i18n.saved);
                    }
                    setStatus(cfg.i18n.saved);
                    $('.cabinet-mon-perm-role').each(function () {
                        updateRoleCount($(this));
                    });
                }
            })
            .catch(function () {
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.error);
                }
                setStatus(cfg.i18n.error);
            })
            .finally(function () {
                saveInFlight = false;
                $form.find('.cabinet-mon-perm-switch').prop('disabled', false);
                window.setTimeout(function () {
                    setStatus('');
                }, 2500);
                if (saveQueued) {
                    saveQueued = false;
                    saveForm();
                }
            });
    }

    function queueSave($switch) {
        const $roleItem = $switch.closest('.cabinet-mon-perm-role');
        updateRoleCount($roleItem);
        flashRoleSaved($roleItem);

        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(saveForm, 350);
    }

    $(function () {
        $(document).on('change', '.cabinet-mon-perm-switch', function () {
            queueSave($(this));
        });

        if (typeof toastr !== 'undefined') {
            toastr.options = {preventDuplicates: true, timeOut: 2000};
        }
    });
})(jQuery, window);
