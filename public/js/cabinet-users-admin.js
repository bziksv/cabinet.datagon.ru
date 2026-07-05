/**
 * /users — пересчёт объёма данных (storage footprint).
 */
(function ($, window) {
    'use strict';

    const cfg = window.cabinetUsersAdminConfig;
    if (!cfg) {
        return;
    }

    function escHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function csrfHeaders() {
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        };
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            headers['X-CSRF-TOKEN'] = token.getAttribute('content');
        }

        return headers;
    }

    function postJson(url, data) {
        if (typeof axios !== 'undefined') {
            return axios.post(url, data, {headers: csrfHeaders()});
        }

        return fetch(url, {
            method: 'POST',
            headers: Object.assign({'Content-Type': 'application/json'}, csrfHeaders()),
            body: JSON.stringify(data),
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json().then(function (json) {
                return {data: json};
            });
        });
    }

    let footprintPageBusy = false;
    let footprintPageKey = '';
    let footprintRefreshAllBusy = false;

    function missingFootprintUserIds(api) {
        const ids = [];
        api.rows({page: 'current'}).every(function () {
            const row = this.data();
            if (row && !row.storage) {
                ids.push(row.id);
            }
        });

        return ids;
    }

    function refreshFootprintUserIds(ids) {
        if (!ids.length || !cfg.footprintRefreshUrl) {
            return Promise.resolve({users: 0});
        }

        return postJson(cfg.footprintRefreshUrl, {user_ids: ids.slice(0, 25)}).then(function (res) {
            return {users: res.data.users || 0};
        });
    }

    function ensureVisibleFootprints(api) {
        if (footprintPageBusy || footprintRefreshAllBusy || !cfg.footprintRefreshUrl || !api) {
            return;
        }

        const ids = missingFootprintUserIds(api);
        if (!ids.length) {
            footprintPageKey = '';
            return;
        }

        const key = String(api.page()) + ':' + ids.join(',');
        if (footprintPageKey === key) {
            return;
        }
        footprintPageKey = key;

        footprintPageBusy = true;
        refreshFootprintUserIds(ids)
            .then(function () {
                footprintPageKey = '';
                api.ajax.reload(null, false);
            })
            .catch(function () {
                footprintPageKey = '';
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.error);
                }
            })
            .finally(function () {
                footprintPageBusy = false;
            });
    }

    function refreshFootprintsAll($btn) {
        if (footprintRefreshAllBusy) {
            return Promise.reject(new Error('busy'));
        }

        let cursorId = 0;
        let totalDone = 0;
        let totalErrors = 0;
        let reloadEvery = 0;
        const limit = 15;
        const direction = 'desc';
        const totalUsers = Math.max(1, parseInt(cfg.usersTotal, 10) || 1);
        const dt = $('#service-users').DataTable();
        const visibleIds = dt ? missingFootprintUserIds(dt) : [];

        footprintRefreshAllBusy = true;
        showFootprintProgress(cfg.i18n.progressPhaseVisible, 0, totalUsers, 0);

        function updateProgress(remaining, phaseTitle) {
            const done = Math.min(totalDone, totalUsers);
            const remain = typeof remaining === 'number' ? remaining : Math.max(0, totalUsers - done);
            const percent = Math.min(100, Math.round((done / totalUsers) * 100));
            updateFootprintProgress(phaseTitle || cfg.i18n.progressTitle, done, totalUsers, remain, percent, totalErrors);
        }

        function maybeReloadTable(force) {
            if (!dt) {
                return;
            }
            reloadEvery += 1;
            if (force || reloadEvery % 3 === 0) {
                dt.ajax.reload(null, false);
            }
        }

        function step() {
            return postJson(cfg.footprintRefreshUrl, {cursor_id: cursorId, limit: limit, direction: direction}).then(function (res) {
                totalDone += res.data.users || 0;
                totalErrors += res.data.errors || 0;
                cursorId = res.data.cursor_id || res.data.last_id || cursorId;
                const total = parseInt(res.data.total, 10) || totalUsers;
                updateProgress(res.data.remaining, cfg.i18n.progressTitle);
                maybeReloadTable(false);
                if (res.data.done) {
                    maybeReloadTable(true);
                    return totalDone;
                }
                return step();
            });
        }

        const start = visibleIds.length
            ? refreshFootprintUserIds(visibleIds).then(function (res) {
                  totalDone += res.users || 0;
                  updateProgress(totalUsers - totalDone, cfg.i18n.progressTitle);
                  maybeReloadTable(true);
              })
            : Promise.resolve();

        return start
            .then(function () {
                updateProgress(totalUsers, cfg.i18n.progressTitle);
                return step();
            })
            .then(function (finalDone) {
                finishFootprintProgress(finalDone, totalUsers, totalErrors);
                return finalDone;
            })
            .catch(function (err) {
                hideFootprintProgress();
                throw err;
            })
            .finally(function () {
                footprintRefreshAllBusy = false;
            });
    }

    function showFootprintProgress(title, done, total, errors) {
        const $panel = $('#cabinet-users-footprint-progress');
        if (!$panel.length) {
            return;
        }
        $panel.removeClass('d-none');
        updateFootprintProgress(title, done, total, total, 0, errors);
    }

    function updateFootprintProgress(title, done, total, remaining, percent, errors) {
        const $panel = $('#cabinet-users-footprint-progress');
        if (!$panel.length) {
            return;
        }
        const pct = Math.max(0, Math.min(100, percent || 0));
        $('#cabinet-users-footprint-progress-title').text(title || cfg.i18n.progressTitle);
        $('#cabinet-users-footprint-progress-percent').text(pct + '%');
        $('#cabinet-users-footprint-progress-bar')
            .css('width', pct + '%')
            .attr('aria-valuenow', pct);
        let status = cfg.i18n.progressStatus
            .replace(':done', Number(done || 0).toLocaleString('ru-RU'))
            .replace(':total', Number(total || 0).toLocaleString('ru-RU'))
            .replace(':remaining', Number(remaining || 0).toLocaleString('ru-RU'));
        if (errors > 0) {
            status += ' · ' + cfg.i18n.progressErrors.replace(':n', errors);
        }
        $('#cabinet-users-footprint-progress-status').text(status);
    }

    function finishFootprintProgress(done, total, errors) {
        updateFootprintProgress(cfg.i18n.progressDone, done, total, 0, 100, errors);
        $('#cabinet-users-footprint-progress-bar')
            .removeClass('progress-bar-animated progress-bar-striped')
            .addClass('bg-success');
        $('#cabinet-users-footprint-progress-spinner').addClass('d-none');
        window.setTimeout(function () {
            hideFootprintProgress();
        }, 4000);
    }

    function hideFootprintProgress() {
        const $panel = $('#cabinet-users-footprint-progress');
        $panel.addClass('d-none');
        $('#cabinet-users-footprint-progress-bar')
            .css('width', '0%')
            .attr('aria-valuenow', 0)
            .addClass('progress-bar-animated progress-bar-striped')
            .removeClass('bg-success');
        $('#cabinet-users-footprint-progress-spinner').removeClass('d-none');
    }

    $(function () {
        $('#cabinet-users-footprint-refresh-all').on('click', function () {
            const $btn = $(this);
            if (footprintRefreshAllBusy) {
                if (typeof toastr !== 'undefined') {
                    toastr.info(cfg.i18n.progressAlreadyRunning);
                }
                return;
            }
            if (!window.confirm(cfg.i18n.confirmRefreshAll)) {
                return;
            }
            $btn.prop('disabled', true);
            refreshFootprintsAll($btn)
                .then(function (totalDone) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(cfg.i18n.refreshedAll.replace(':n', totalDone));
                    }
                })
                .catch(function () {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(cfg.i18n.error);
                    }
                })
                .finally(function () {
                    $btn.prop('disabled', false);
                });
        });

        $('#cabinet-user-storage-refresh').on('click', function () {
            const userId = $(this).data('user-id');
            const $btn = $(this);
            $btn.prop('disabled', true);
            postJson(cfg.footprintRefreshUrl, {user_id: userId})
                .then(function (res) {
                    $('#cabinet-user-storage-summary').text(res.data.label || '—');
                    const $list = $('#cabinet-user-storage-modules').empty();
                    (res.data.footprint.modules || []).forEach(function (mod) {
                        $list.append(
                            $('<li class="d-flex justify-content-between gap-2 py-1 border-bottom"></li>').html(
                                '<span>' +
                                    escHtml(mod.label) +
                                    '</span><span class="text-secondary text-nowrap">' +
                                    escHtml(mod.rows) +
                                    ' · ~' +
                                    escHtml(mod.est_kb) +
                                    ' KB</span>'
                            )
                        );
                    });
                })
                .finally(function () {
                    $btn.prop('disabled', false);
                });
        });
    });

    window.cabinetUsersEnsureVisibleFootprints = ensureVisibleFootprints;
})(jQuery, window);
