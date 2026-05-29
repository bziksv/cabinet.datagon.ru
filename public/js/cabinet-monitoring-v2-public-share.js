(function () {
    'use strict';

    var cfg = window.cabinetMonV2Config || {};
    var i18n = cfg.i18n || {};
    var statsUrl = cfg.projectStatsUrl || '';
    var csrf = cfg.csrf || '';
    var modalEl = document.getElementById('cabinetMonV2PublicShareModal');
    var modal = modalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalEl) : null;
    var activeProjectId = null;

    function escHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function toastSuccess(message) {
        if (window.toastr) {
            toastr.success(message);
        }
    }

    function toastError(message) {
        if (window.toastr) {
            toastr.error(message);
        }
    }

    function formatMastered(summary) {
        if (summary.mastered == null) {
            return '—';
        }
        var value = Number(summary.mastered).toLocaleString('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        if (summary.mastered_percent) {
            value += ' (' + summary.mastered_percent + '%)';
        }
        return value;
    }

    function formatTop10(summary) {
        if (summary.top10 == null) {
            return '—';
        }
        return String(summary.top10) + (summary.diff_top10 || '');
    }

    function renderReport(data) {
        var summary = data.summary || {};
        var engines = data.engines || [];
        var kpis = [
            { label: i18n.words || 'Words', value: summary.words != null ? summary.words : '—' },
            {
                label: i18n.position || 'Position',
                value: summary.middle != null ? Number(summary.middle).toFixed(2) : '—',
            },
            { label: (i18n.top || 'TOP') + '-10', value: formatTop10(summary) },
            { label: i18n.mastered || 'Mastered', value: formatMastered(summary) },
        ];

        var html = '<div class="row g-2 mb-4">';
        kpis.forEach(function (k) {
            html +=
                '<div class="col-6 col-md-3"><div class="cabinet-mon-v2-public-kpi">' +
                '<div class="cabinet-mon-v2-public-kpi__value">' +
                escHtml(k.value) +
                '</div>' +
                '<div class="cabinet-mon-v2-public-kpi__label">' +
                escHtml(k.label) +
                '</div></div></div>';
        });
        html += '</div>';

        if (summary.snapshot_at) {
            html +=
                '<p class="small text-secondary mb-4">' +
                escHtml(i18n.publicShareSnapshotAt || 'Snapshot') +
                ': ' +
                escHtml(summary.snapshot_at) +
                '</p>';
        }

        if (!engines.length) {
            html +=
                '<p class="text-secondary small">' +
                escHtml(i18n.publicShareNoRegions || 'No regions') +
                '</p>';
        } else {
            engines.forEach(function (engine) {
                var title =
                    escHtml((engine.engine || '').charAt(0).toUpperCase() + (engine.engine || '').slice(1)) +
                    ', ' +
                    escHtml(engine.location || '');
                if (engine.lr) {
                    title += ' [' + escHtml(engine.lr) + ']';
                }

                html +=
                    '<div class="card card-outline card-success mb-3">' +
                    '<div class="card-header py-2"><h6 class="card-title mb-0">' +
                    title +
                    '</h6>';
                if (engine.schedule) {
                    html += '<div class="small text-secondary mt-1">' + escHtml(engine.schedule) + '</div>';
                }
                html +=
                    '</div><div class="card-body p-0"><div class="table-responsive">' +
                    '<table class="table table-sm table-bordered table-hover mb-0 cabinet-mon-v2-public-table">' +
                    '<thead class="table-light"><tr>' +
                    '<th>' +
                    escHtml(i18n.publicShareColDate || 'Date') +
                    '</th><th>' +
                    escHtml(i18n.position || 'Position') +
                    '</th><th>TOP-1</th><th>TOP-3</th><th>TOP-5</th><th>TOP-10</th><th>TOP-20</th><th>TOP-50</th><th>TOP-100</th><th>' +
                    escHtml(i18n.mastered || 'Mastered') +
                    '</th></tr></thead><tbody>';

                var rows = engine.rows || [];
                if (!rows.length) {
                    html +=
                        '<tr><td colspan="10" class="text-center text-secondary">' +
                        escHtml(i18n.publicShareNoData || 'No data') +
                        '</td></tr>';
                } else {
                    rows.forEach(function (row) {
                        var dateCell = escHtml(row.date || '—');
                        if (row.period_label) {
                            dateCell +=
                                '<br><small class="text-secondary">' + escHtml(row.period_label) + '</small>';
                        }
                        var titleAttr = row.delta_vs_label
                            ? ' title="' + escHtml(row.delta_vs_label) + '"'
                            : '';
                        html += '<tr><td class="text-nowrap">' + dateCell + '</td><td>' + escHtml(row.middle ?? '—') + '</td>';
                        ['top_1', 'top_3', 'top_5', 'top_10', 'top_20', 'top_50', 'top_100'].forEach(function (key) {
                            html += '<td' + titleAttr + '>' + escHtml(row[key] || '—') + '</td>';
                        });
                        var masteredCell = '—';
                        if (row.mastered != null) {
                            masteredCell = Number(row.mastered).toLocaleString('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            });
                            if (row.mastered_percent) {
                                masteredCell +=
                                    ' <sup class="text-success">' + escHtml(row.mastered_percent) + '%</sup>';
                            }
                        }
                        html += '<td>' + masteredCell + '</td></tr>';
                    });
                }

                html += '</tbody></table></div></div></div>';
            });
        }

        var body = document.getElementById('cabinetMonV2PublicShareModalBody');
        if (body) {
            body.innerHTML = html;
        }
    }

    function updateSharePanel(share) {
        var panel = document.getElementById('cabinetMonV2PublicSharePanel');
        var urlInput = document.getElementById('cabinetMonV2PublicShareUrl');
        var copyBtn = document.getElementById('cabinetMonV2PublicShareCopy');
        var revokeBtn = document.getElementById('cabinetMonV2PublicShareRevoke');
        var createBtn = document.getElementById('cabinetMonV2PublicShareCreate');
        var expiresBadge = document.getElementById('cabinetMonV2PublicShareExpires');
        var ttlSelect = document.getElementById('cabinetMonV2PublicShareTtl');
        var unavailable = document.getElementById('cabinetMonV2PublicShareUnavailable');

        if (!panel || !urlInput) {
            return;
        }

        share = share || {};
        var backendOn =
            share.available !== false &&
            panel.getAttribute('data-feature-available') !== '0';

        if (ttlSelect && share.ttl_days != null) {
            ttlSelect.value = String(share.ttl_days);
        }

        if (unavailable) {
            unavailable.classList.toggle('d-none', backendOn);
        }

        if (!backendOn) {
            if (createBtn) createBtn.disabled = true;
            if (copyBtn) copyBtn.disabled = true;
            if (revokeBtn) revokeBtn.disabled = true;
            return;
        }

        if (createBtn) createBtn.disabled = false;
        var hasLink = !!share.url;
        urlInput.value = share.url || '';
        if (copyBtn) copyBtn.disabled = !hasLink;
        if (revokeBtn) revokeBtn.disabled = !hasLink;
        if (createBtn) {
            createBtn.innerHTML =
                '<i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' +
                escHtml(hasLink ? i18n.publicShareRefresh || 'Refresh link' : i18n.publicShareCreate || 'Create link');
        }

        if (expiresBadge) {
            if (hasLink && (share.expires_label || share.expires_at)) {
                expiresBadge.textContent = share.expires_label || (i18n.validUntil || 'Valid until') + ' ' + share.expires_at;
                expiresBadge.classList.remove('d-none', 'text-bg-secondary');
                expiresBadge.classList.add('text-bg-success');
            } else {
                expiresBadge.classList.add('d-none');
                expiresBadge.classList.remove('text-bg-success');
            }
        }
    }

    function loadModal(projectId, projectLabel) {
        activeProjectId = projectId;
        var subtitle = document.getElementById('cabinetMonV2PublicShareModalSubtitle');
        if (subtitle) {
            subtitle.textContent = projectLabel || '';
        }

        var body = document.getElementById('cabinetMonV2PublicShareModalBody');
        if (body) {
            body.innerHTML =
                '<div class="text-center py-5 text-secondary"><div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
                '<p class="mt-2 mb-0 small">' +
                escHtml(i18n.loading || 'Loading') +
                '…</p></div>';
        }

        if (!statsUrl || !window.jQuery) {
            return;
        }

        window.jQuery
            .get(statsUrl, { projectId: projectId })
            .done(function (data) {
                var project = data.project || {};
                if (subtitle && !projectLabel) {
                    subtitle.textContent = (project.name || '') + ' · ' + (project.url || '');
                }
                renderReport(data);
                updateSharePanel(data.share || {});
            })
            .fail(function () {
                if (body) {
                    body.innerHTML =
                        '<p class="text-danger mb-0">' + escHtml(i18n.loadError || 'Load error') + '</p>';
                }
            });
    }

    function bindEvents() {
        if (!window.jQuery) {
            return;
        }

        var $ = window.jQuery;

        $(document).on('click', '.cabinet-mon-v2-public-share-open', function (event) {
            event.preventDefault();
            var projectId = parseInt($(this).data('id'), 10);
            if (!projectId) {
                return;
            }
            var card = $(this).closest('.cabinet-mon-v2-card');
            var label = '';
            if (card.length) {
                var name = card.find('.cabinet-mon-v2-card__title').text().trim();
                var url = card.find('.cabinet-mon-v2-card__url').text().trim();
                label = name + (url ? ' · ' + url : '');
            }
            if (modal) {
                modal.show();
            }
            loadModal(projectId, label);
        });

        $('#cabinetMonV2PublicShareCopy').on('click', function () {
            var input = document.getElementById('cabinetMonV2PublicShareUrl');
            if (!input || !input.value) {
                return;
            }
            var done = function () {
                var btn = $('#cabinetMonV2PublicShareCopy');
                var html = btn.html();
                btn.html('<i class="bi bi-check2" aria-hidden="true"></i>');
                setTimeout(function () {
                    btn.html(html);
                }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(done).catch(function () {
                    input.select();
                    document.execCommand('copy');
                    done();
                });
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        });

        $('#cabinetMonV2PublicShareCreate').on('click', function () {
            if (!activeProjectId) {
                return;
            }
            var panel = document.getElementById('cabinetMonV2PublicSharePanel');
            var createUrl = panel ? panel.getAttribute('data-create-url') : '';
            var ttl = $('#cabinetMonV2PublicShareTtl').val();
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: createUrl,
                dataType: 'json',
                data: {
                    projectId: activeProjectId,
                    ttl_days: ttl,
                    _token: csrf,
                },
                success: function (data) {
                    updateSharePanel({
                        available: true,
                        url: data.url,
                        expires_at: data.expires_at,
                        expires_label: data.expires_label,
                        ttl_days: data.ttl_days,
                    });
                    if (window.cabinetMonV2List && window.cabinetMonV2List.patchPublicShare) {
                        window.cabinetMonV2List.patchPublicShare(activeProjectId, {
                            active: true,
                            url: data.url,
                            expires_label: data.expires_label || null,
                        });
                    }
                    toastSuccess(data.message || i18n.publicShareCreated || 'Link created');
                },
                error: function (xhr) {
                    var msg =
                        xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : i18n.loadError || 'Error';
                    toastError(msg);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                },
            });
        });

        $('#cabinetMonV2PublicShareRevoke').on('click', function () {
            if (!activeProjectId) {
                return;
            }
            var panel = document.getElementById('cabinetMonV2PublicSharePanel');
            var revokeUrl = panel ? panel.getAttribute('data-revoke-url') : '';
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: revokeUrl,
                dataType: 'json',
                data: {
                    projectId: activeProjectId,
                    _token: csrf,
                },
                success: function (data) {
                    updateSharePanel({ available: true, url: null });
                    if (window.cabinetMonV2List && window.cabinetMonV2List.patchPublicShare) {
                        window.cabinetMonV2List.patchPublicShare(activeProjectId, {
                            active: false,
                            url: null,
                            expires_label: null,
                        });
                    }
                    toastSuccess(data.message || i18n.publicShareRevoked || 'Link revoked');
                },
                error: function (xhr) {
                    var msg =
                        xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : i18n.loadError || 'Error';
                    toastError(msg);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                },
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }
})();
