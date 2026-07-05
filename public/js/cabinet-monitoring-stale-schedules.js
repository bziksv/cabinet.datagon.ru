/**
 * Зависшие расписания мониторинга (/users, /monitoring/admin).
 * window.cabinetMonitoringStaleSchedulesConfig: { idPrefix, staleListUrl, staleDisableUrl, ... }
 */
(function ($, window) {
    'use strict';

    const cfg = window.cabinetMonitoringStaleSchedulesConfig;
    if (!cfg || !cfg.idPrefix) {
        return;
    }

    const p = cfg.idPrefix;
    let staleTable = null;

    function $sel(suffix) {
        return $('#' + p + suffix);
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

    function staleDays() {
        return parseInt($sel('-days').val(), 10) || cfg.staleInactiveDays || 90;
    }

    function updateStaleKpi(summary) {
        if (!summary) {
            return;
        }
        $sel('-kpi-projects').text(summary.projects);
        $sel('-kpi-users').text(summary.users);
        $sel('-kpi-regions').text(summary.auto_regions);
        $sel('-kpi-keywords').text(summary.keywords);
        $sel('-panel').find('.badge.text-bg-warning').first().text(summary.projects);
    }

    function initStaleTable() {
        if (!$sel('-table').length || staleTable) {
            return;
        }

        staleTable = $sel('-table').DataTable({
            dom: 'rt<"d-flex justify-content-between align-items-center p-2"ip>',
            paging: true,
            pageLength: 25,
            searching: false,
            ordering: true,
            order: [[4, 'desc']],
            processing: true,
            serverSide: true,
            language: {
                emptyTable: cfg.i18n.staleEmpty,
                processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>',
            },
            ajax: {
                url: cfg.staleListUrl,
                type: 'GET',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                data: function (d) {
                    d.inactive_days = staleDays();
                    d.free_only = $sel('-free-only').is(':checked') ? 1 : 0;
                },
                dataSrc: function (json) {
                    if (json.summary) {
                        updateStaleKpi(json.summary);
                    }
                    return json.data || [];
                },
            },
            columnDefs: [
                {orderable: false, targets: [6]},
            ],
            columns: [
                {
                    data: 'url',
                    name: 'url',
                    render: function (val, type, row) {
                        return (
                            '<a href="' +
                            escHtml(row.monitoring_url) +
                            '" class="text-break" target="_blank" rel="noopener">' +
                            escHtml(row.url) +
                            '</a>' +
                            (row.name ? '<br><small class="text-secondary">' + escHtml(row.name) + '</small>' : '')
                        );
                    },
                },
                {
                    data: 'user_email',
                    name: 'email',
                    render: function (val, type, row) {
                        return (
                            '<a href="' +
                            escHtml(cfg.usersEditUrlTemplate.replace('__ID__', row.user_id)) +
                            '">' +
                            escHtml(val) +
                            '</a>'
                        );
                    },
                },
                {
                    data: 'last_online_at',
                    name: 'last_online_at',
                    render: function (val, type, row) {
                        return escHtml(val || cfg.i18n.never) + '<br><small class="text-secondary">' + escHtml(row.last_online_human) + '</small>';
                    },
                },
                {
                    data: 'tariff',
                    name: 'tariff',
                    render: function (val) {
                        return escHtml(val || '—');
                    },
                },
                {
                    data: 'keywords_count',
                    name: 'keywords_count',
                    className: 'text-end',
                    render: function (val) {
                        return escHtml(Number(val || 0).toLocaleString('ru-RU'));
                    },
                },
                {
                    data: 'auto_regions',
                    name: 'auto_regions',
                    render: function (val, type, row) {
                        const schedules = row.schedules;
                        if (!schedules || !schedules.length) {
                            return '—';
                        }
                        return schedules
                            .slice(0, 3)
                            .map(function (s) {
                                return '<span class="badge text-bg-light text-dark border me-1 mb-1">' + escHtml(s) + '</span>';
                            })
                            .join('') +
                            (schedules.length > 3
                                ? '<span class="text-secondary small">+' + (schedules.length - 3) + '</span>'
                                : '');
                    },
                },
                {
                    data: null,
                    className: 'text-end text-nowrap',
                    render: function (data, type, row) {
                        return (
                            '<button type="button" class="btn btn-sm btn-outline-warning cabinet-stale-off-project" data-project-id="' +
                            row.project_id +
                            '" title="' +
                            escHtml(cfg.i18n.disableProject) +
                            '"><i class="bi bi-pause-circle"></i></button> ' +
                            '<button type="button" class="btn btn-sm btn-outline-danger cabinet-stale-off-user" data-user-id="' +
                            row.user_id +
                            '" title="' +
                            escHtml(cfg.i18n.disableUser) +
                            '"><i class="bi bi-person-x"></i></button>'
                        );
                    },
                },
            ],
        });

        $sel('-collapse').on('shown.bs.collapse', function () {
            staleTable.columns.adjust();
        });
    }

    function disableStale(payload, $btn) {
        if (!window.confirm(cfg.i18n.confirmDisable)) {
            return;
        }
        if (window.cabinetButtonBusy && $btn.length) {
            window.cabinetButtonBusy.set($btn, {label: cfg.i18n.disabling || '…'});
        } else {
            $btn.prop('disabled', true);
        }
        postJson(cfg.staleDisableUrl, payload)
            .then(function (res) {
                if (typeof toastr !== 'undefined') {
                    toastr.success(res.data.message || cfg.i18n.disabled);
                }
                if (staleTable) {
                    staleTable.ajax.reload(null, false);
                }
                if (cfg.reloadUsersTable && $('#service-users').length && $.fn.DataTable.isDataTable('#service-users')) {
                    $('#service-users').DataTable().ajax.reload(null, false);
                }
            })
            .catch(function () {
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.error);
                }
            })
            .finally(function () {
                if (window.cabinetButtonBusy && $btn.length) {
                    window.cabinetButtonBusy.clear($btn);
                } else {
                    $btn.prop('disabled', false);
                }
            });
    }

    $(function () {
        if (!$sel('-panel').length) {
            return;
        }

        initStaleTable();

        $sel('-apply').add($sel('-reload')).on('click', function () {
            if (staleTable) {
                staleTable.ajax.reload();
            }
        });

        $sel('-filter-users').on('click', function () {
            $('#filter-stale-monitoring').val('1');
            $('#cabinet-users-filters-apply').trigger('click');
        });

        $sel('-panel').on('click', '.cabinet-stale-off-project', function () {
            disableStale({project_id: $(this).data('project-id')}, $(this));
        });

        $sel('-panel').on('click', '.cabinet-stale-off-user', function () {
            disableStale({user_id: $(this).data('user-id')}, $(this));
        });

        if (window.location.hash === '#stale-schedules' || window.location.hash === '#' + p + '-panel') {
            $sel('-collapse').collapse('show');
        }
    });
})(jQuery, window);
