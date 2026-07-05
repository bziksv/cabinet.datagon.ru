(function ($, cfg) {
    'use strict';

    if (!$ || !cfg) {
        return;
    }

    var isSubmitting = false;
    var $submitBtn = null;
    var submitBtnDefaultHtml = '';
    var pendingHistoryDelete = null;
    var historyDeleteInFlight = false;
    var historyPollTimer = null;
    var historyPollInFlight = false;

    function queueLoaderHtml(label) {
        return '<span class="cabinet-mon-comp-positions-history-state">' +
            '<span class="cabinet-mon-loader cabinet-mon-loader--sm" role="presentation">' +
            '<i class="fas fa-circle-notch cabinet-mon-loader__icon" aria-hidden="true"></i>' +
            '</span> ' + label + '</span>';
    }

    function historyDeleteBtn(recordId) {
        return '<button type="button" class="btn btn-outline-danger btn-sm remove-error-results" data-id="' + recordId + '">' +
            '<i class="bi bi-trash" aria-hidden="true"></i></button>';
    }

    function historyPendingLabel(response) {
        if (Number(response.queue_total) > 0 && Number(response.queue_position) > 0) {
            return cfg.i18n.pendingPosition
                .replace(':position', Number(response.queue_position))
                .replace(':total', Number(response.queue_total));
        }

        return cfg.i18n.pendingWaiting;
    }

    function historyProgressLabel(response) {
        if (response.state === 'pending') {
            return historyPendingLabel(response);
        }
        if (response.stale) {
            return cfg.i18n.stale;
        }
        if (response.state === 'in queue') {
            return cfg.i18n.inQueue;
        }
        if (response.progress_percent !== null && response.progress_total > 0) {
            return cfg.i18n.inProgress + ' ' + response.progress_percent + '%';
        }

        return cfg.i18n.inProgress;
    }

    function historyProgressPercent(response) {
        if (Number(response.progress_total) > 0 && response.progress_percent !== null && response.progress_percent !== undefined) {
            return Math.min(100, Math.max(0, Number(response.progress_percent)));
        }

        return null;
    }

    function historyProgressBarHtml(response) {
        if (response.state === 'in queue') {
            return '<div class="progress cabinet-mon-comp-positions-history-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100">' +
                '<div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:35%"></div></div>';
        }

        var percent = historyProgressPercent(response);
        var safePercent = percent === null ? 0 : percent;

        return '<div class="progress cabinet-mon-comp-positions-history-progress__bar" role="progressbar"' +
            ' aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + safePercent + '">' +
            '<div class="progress-bar bg-primary" style="width:' + safePercent + '%"></div></div>';
    }

    function historyProgressMeta(response) {
        if (Number(response.progress_total) > 0) {
            return cfg.i18n.progressCount
                .replace(':done', Number(response.progress_done))
                .replace(':total', Number(response.progress_total));
        }

        return '';
    }

    function historyActionsContainer($row) {
        var $cell = $row.children('td').eq(2);
        var $actions = $cell.find('.cabinet-mon-comp-positions-history-actions');
        if (!$actions.length) {
            $actions = $('<div class="cabinet-mon-comp-positions-history-actions"></div>');
            $cell.empty().append($actions);
        }

        return $actions;
    }

    function renderHistoryActionsCell($row, response, recordId) {
        var $actions = historyActionsContainer($row);
        var meta = historyProgressMeta(response);
        var progressBlock = '';

        if (response.state === 'ready') {
            $actions.html(
                '<a class="btn btn-outline-primary btn-sm" href="' + cfg.routes.changesDatesResult + '/' + recordId +
                '" target="_blank" rel="noopener noreferrer">' + cfg.i18n.show + '</a>' + historyDeleteBtn(recordId)
            );
            $row.removeClass('need-check analyse-pending');
            return;
        }

        if (response.state === 'fail') {
            $actions.html(
                '<span class="text-danger">' + cfg.i18n.fail + '</span>' + historyDeleteBtn(recordId)
            );
            $row.removeClass('need-check analyse-pending');
            return;
        }

        if (response.state === 'pending') {
            $actions.html(
                '<span class="cabinet-mon-comp-positions-history-state text-secondary">' +
                '<i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>' +
                historyPendingLabel(response) + '</span>' + historyDeleteBtn(recordId)
            );
            $row.addClass('need-check');
            return;
        }

        progressBlock = '<div class="cabinet-mon-comp-positions-history-progress">' +
            queueLoaderHtml(historyProgressLabel(response)) +
            historyProgressBarHtml(response);
        if (meta) {
            progressBlock += '<span class="cabinet-mon-comp-positions-history-progress__meta">' + meta + '</span>';
        }
        if (response.stale) {
            progressBlock += '<span class="cabinet-mon-comp-positions-history-progress__stale">' + cfg.i18n.staleHint + '</span>';
        }
        progressBlock += '</div>';

        $actions.html(progressBlock + historyDeleteBtn(recordId));
    }

    function collectActiveHistoryIds() {
        var ids = [];

        $('#changeDatesTbody tr.need-check').each(function () {
            var id = $(this).attr('data-id');
            if (id) {
                ids.push(String(id));
            }
        });

        return ids;
    }

    function scheduleHistoryPoll(delayMs) {
        if (historyPollTimer) {
            window.clearTimeout(historyPollTimer);
        }

        historyPollTimer = window.setTimeout(function () {
            historyPollTimer = null;
            pollActiveHistoryReports();
        }, delayMs);
    }

    function pollActiveHistoryReports() {
        if (historyPollInFlight) {
            scheduleHistoryPoll(1000);
            return;
        }

        var ids = collectActiveHistoryIds();
        if (!ids.length || !cfg.routes.changesDatesCheckBatch) {
            return;
        }

        historyPollInFlight = true;

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: cfg.routes.changesDatesCheckBatch,
            data: {
                _token: cfg.csrf,
                ids: ids,
            },
        }).done(function (batch) {
            var records = (batch && batch.records) ? batch.records : {};
            var hasActive = false;

            ids.forEach(function (id) {
                var response = records[id];
                if (!response) {
                    return;
                }

                var $row = $('#analyse-in-queue-' + id);
                if (!$row.length) {
                    return;
                }

                renderHistoryActionsCell($row, response, id);

                if (response.state !== 'ready' && response.state !== 'fail' && !response.stale) {
                    hasActive = true;
                }
            });

            if (hasActive) {
                scheduleHistoryPoll(3000);
            }
        }).fail(function () {
            scheduleHistoryPoll(5000);
        }).always(function () {
            historyPollInFlight = false;
        });
    }

    function startHistoryPolling(delayMs) {
        scheduleHistoryPoll(typeof delayMs === 'number' ? delayMs : 500);
    }

    function selectedRegionLabel() {
        return String($('#searchEngines option:selected').text()).trim();
    }

    function selectedCompetitorsRaw() {
        var $sel = $('#comp-dynamics-competitors');
        if (!$sel.length) {
            return [];
        }

        return $sel.val() || [];
    }

    function selectedCompetitorsForSubmit() {
        var selected = selectedCompetitorsRaw();
        var all = cfg.competitorDomains || [];

        if (!all.length || !selected.length || selected.length >= all.length) {
            return null;
        }

        return selected;
    }

    function competitorsSelectionKey(selectedOrNull) {
        if (!selectedOrNull || !selectedOrNull.length) {
            return '__all__';
        }

        return selectedOrNull.slice().sort().join('|');
    }

    function competitorsSelectionKeyCurrent() {
        return competitorsSelectionKey(selectedCompetitorsForSubmit());
    }

    function competitorsSummaryText(selectedOrNull) {
        var allCount = (cfg.competitorDomains || []).length;
        var own = cfg.ownDomain || '';

        if (!selectedOrNull || !selectedOrNull.length || selectedOrNull.length >= allCount) {
            return cfg.i18n.competitorsAll
                .replace(':own', own)
                .replace(':count', String(allCount));
        }

        return cfg.i18n.competitorsSelected
            .replace(':own', own)
            .replace(':count', String(selectedOrNull.length));
    }

    function rangeCellHtml(dateRange, selectedOrNull) {
        return '<td class="cabinet-mon-comp-dynamics-table__range">' +
            '<div>' + dateRange + '</div>' +
            '<div class="cabinet-mon-comp-dynamics-table__competitors small text-secondary">' +
            competitorsSummaryText(selectedOrNull) + '</div></td>';
    }

    function initCompetitorsSelect() {
        var $sel = $('#comp-dynamics-competitors');
        if (!$sel.length || typeof $.fn.select2 !== 'function') {
            return;
        }

        $sel.select2({
            width: '100%',
            placeholder: $sel.data('placeholder') || '',
            closeOnSelect: false,
        });

        var storageKey = 'lr_redbox_monitoring_dynamics_competitors_' + cfg.projectId;
        var saved = localStorage.getItem(storageKey);
        if (saved) {
            try {
                var urls = JSON.parse(saved);
                if (Array.isArray(urls) && urls.length) {
                    $sel.val(urls).trigger('change');
                }
            } catch (e) {
                // ignore invalid storage
            }
        }

        $sel.on('change', function () {
            localStorage.setItem(storageKey, JSON.stringify($sel.val() || []));
            refreshHistoryEstimateHint();
        });
    }

    function setSubmitting(active) {
        isSubmitting = active;
        if (!$submitBtn || !$submitBtn.length) {
            return;
        }
        $submitBtn.prop('disabled', active);
        if (active) {
            $submitBtn.html(
                '<span class="cabinet-mon-loader cabinet-mon-loader--sm me-1" role="presentation">' +
                '<i class="fas fa-circle-notch cabinet-mon-loader__icon" aria-hidden="true"></i></span>' +
                cfg.i18n.submitting
            );
        } else {
            $submitBtn.html(submitBtnDefaultHtml);
        }
    }

    function findActiveRow(dateRange, regionId, competitorsKey) {
        var $match = $('#changeDatesTbody tr').filter(function () {
            var $row = $(this);
            if ($row.attr('id') === 'empty-row') {
                return false;
            }
            if (!$row.hasClass('need-check') && !$row.hasClass('analyse-pending')) {
                return false;
            }

            return $row.attr('data-range') === dateRange
                && String($row.attr('data-region')) === String(regionId)
                && String($row.attr('data-competitors-key') || '__all__') === String(competitorsKey);
        }).first();

        return $match.length ? $match : null;
    }

    function flashRow($row) {
        if (!$row || !$row.length) {
            return;
        }
        $row.addClass('cabinet-mon-comp-dynamics-row-flash');
        window.setTimeout(function () {
            $row.removeClass('cabinet-mon-comp-dynamics-row-flash');
        }, 1800);
    }

    function prependPendingRow(dateRange, regionId, pendingId, competitorsKey, selectedOrNull) {
        $('#empty-row').remove();
        $('#changeDatesTbody').prepend(
            '<tr id="analyse-pending-' + pendingId + '" class="need-check analyse-pending cabinet-mon-comp-dynamics-row-pending"' +
            ' data-range="' + dateRange + '" data-region="' + regionId + '" data-competitors-key="' + competitorsKey + '">' +
            rangeCellHtml(dateRange, selectedOrNull) +
            '<td class="cabinet-mon-comp-dynamics-table__region">' +
            '<span class="cabinet-mon-comp-dynamics-table__region-text" title="' + selectedRegionLabel() + '">' +
            selectedRegionLabel() + '</span></td>' +
            '<td class="text-end"><div class="cabinet-mon-comp-positions-history-actions">' +
            queueLoaderHtml(cfg.i18n.submitting) +
            '</div></td></tr>'
        );
    }

    function removePendingRow(pendingId) {
        $('#analyse-pending-' + pendingId).remove();
        if (!$('#changeDatesTbody tr').length) {
            $('#changeDatesTbody').html(
                '<tr id="empty-row"><td class="text-center text-secondary" colspan="3">' +
                cfg.i18n.empty + '</td></tr>'
            );
        }
    }

    function finalizeSubmittedRow(pendingId, recordId, response) {
        var $row = $('#analyse-pending-' + pendingId);
        if (!$row.length) {
            return;
        }

        $row.attr('id', 'analyse-in-queue-' + recordId);
        $row.attr('data-id', recordId);
        $row.addClass('need-check');
        $row.removeClass('analyse-pending cabinet-mon-comp-dynamics-row-pending');

        if (response.queued) {
            renderHistoryActionsCell($row, {
                state: 'pending',
                queue_position: response.queuePosition,
                queue_total: response.queueTotal,
            }, recordId);
        } else {
            renderHistoryActionsCell($row, {
                state: 'in queue',
            }, recordId);
        }

        startHistoryPolling();
    }

    function refreshHistoryEstimateHint() {
        var dateRange = $('#date-range').val();
        var $hint = $('#comp-positions-history-estimate');

        if (!$hint.length || !dateRange || !cfg.routes.estimateChangesDates) {
            return;
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: cfg.routes.estimateChangesDates,
            data: {
                _token: cfg.csrf,
                projectId: cfg.projectId,
                region: $('#searchEngines').val(),
                dateRange: dateRange,
            },
        }).done(function (estimate) {
            if (!estimate.large) {
                $hint.addClass('d-none').empty();
                return;
            }

            $hint.removeClass('d-none').html(
                cfg.i18n.largeHint
                    .replace(':snapshots', Number(estimate.snapshots).toLocaleString('ru-RU'))
                    .replace(':days', Number(estimate.calendarDays).toLocaleString('ru-RU'))
                    .replace(':minutes', Number(estimate.estimatedMinutes).toLocaleString('ru-RU'))
            );
        }).fail(function () {
            $hint.addClass('d-none').empty();
        });
    }

    function submitHistoryAnalyse(dateRange, regionId, pendingId, competitorsKey, selectedOrNull) {
        var data = {
            _token: cfg.csrf,
            projectId: cfg.projectId,
            region: regionId,
            dateRange: dateRange,
        };

        if (selectedOrNull && selectedOrNull.length) {
            data.competitors = selectedOrNull;
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: cfg.routes.historyPositions,
            data: data,
        }).done(function (response) {
            if (response.duplicate) {
                removePendingRow(pendingId);
                var $existing = $('#analyse-in-queue-' + response.analyseId);
                if (!$existing.length) {
                    $('#empty-row').remove();
                    $('#changeDatesTbody').prepend(
                        '<tr id="analyse-in-queue-' + response.analyseId + '" class="need-check"' +
                        ' data-id="' + response.analyseId + '" data-range="' + dateRange + '" data-region="' + regionId + '"' +
                        ' data-competitors-key="' + competitorsKey + '">' +
                        rangeCellHtml(dateRange, selectedOrNull) +
                        '<td class="cabinet-mon-comp-dynamics-table__region">' +
                        '<span class="cabinet-mon-comp-dynamics-table__region-text">' + selectedRegionLabel() + '</span></td>' +
                        '<td class="text-end"><div class="cabinet-mon-comp-positions-history-actions">' +
                        queueLoaderHtml(cfg.i18n.inQueue) + historyDeleteBtn(response.analyseId) +
                        '</div></td></tr>'
                    );
                    $existing = $('#analyse-in-queue-' + response.analyseId);
                }
                flashRow($existing);
                startHistoryPolling();
                setSubmitting(false);
                return;
            }

            finalizeSubmittedRow(pendingId, response.analyseId, response);
            setSubmitting(false);
        }).fail(function () {
            removePendingRow(pendingId);
            setSubmitting(false);
            window.alert(cfg.i18n.submitFail);
        });
    }

    function startHistoryAnalyse(dateRange, regionId) {
        var competitorsKey = competitorsSelectionKeyCurrent();
        var selectedOrNull = selectedCompetitorsForSubmit();
        var allCount = (cfg.competitorDomains || []).length;

        if (allCount > 0 && !selectedCompetitorsRaw().length) {
            window.alert(cfg.i18n.competitorsRequired);
            return;
        }

        var activeRow = findActiveRow(dateRange, regionId, competitorsKey);
        if (activeRow) {
            flashRow(activeRow);
            window.alert(cfg.i18n.duplicateActive);
            return;
        }

        if (isSubmitting) {
            return;
        }

        setSubmitting(true);
        var pendingId = String(Date.now());
        prependPendingRow(dateRange, regionId, pendingId, competitorsKey, selectedOrNull);

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: cfg.routes.estimateChangesDates,
            data: {
                _token: cfg.csrf,
                projectId: cfg.projectId,
                region: regionId,
                dateRange: dateRange,
            },
        }).done(function (estimate) {
            if (estimate.large) {
                var msg = cfg.i18n.largeConfirm
                    .replace(':snapshots', Number(estimate.snapshots).toLocaleString('ru-RU'))
                    .replace(':days', Number(estimate.calendarDays).toLocaleString('ru-RU'))
                    .replace(':minutes', Number(estimate.estimatedMinutes).toLocaleString('ru-RU'));
                if (!window.confirm(msg)) {
                    removePendingRow(pendingId);
                    setSubmitting(false);
                    return;
                }
            }
            submitHistoryAnalyse(dateRange, regionId, pendingId, competitorsKey, selectedOrNull);
        }).fail(function () {
            submitHistoryAnalyse(dateRange, regionId, pendingId, competitorsKey, selectedOrNull);
        });
    }

    function hideHistoryDeleteModal() {
        var modalEl = document.getElementById('removeHistoryReport');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }

    function initHistoryDeleteModal() {
        $(document).on('click', '.remove-error-results', function (e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            pendingHistoryDelete = {
                id: $(this).attr('data-id'),
                $row: $row,
            };
            $('#comp-dynamics-delete-range').text($row.attr('data-range') || '');
            var modalEl = document.getElementById('removeHistoryReport');
            if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });

        $('#remove-history-report-confirm').on('click', function () {
            if (!pendingHistoryDelete || historyDeleteInFlight) {
                return;
            }

            var payload = pendingHistoryDelete;
            var $confirmBtn = $(this);
            var busy = window.cabinetButtonBusy;

            if (busy && !busy.set($confirmBtn, { label: cfg.i18n.deleting, blockModal: true })) {
                return;
            }

            historyDeleteInFlight = true;

            $.ajax({
                type: 'POST',
                url: cfg.routes.changesDatesRemove,
                data: {
                    _token: cfg.csrf,
                    id: payload.id,
                },
            }).done(function () {
                payload.$row.remove();
                hideHistoryDeleteModal();
                if (!$('#changeDatesTbody tr').length) {
                    $('#changeDatesTbody').html(
                        '<tr id="empty-row"><td class="text-center text-secondary" colspan="3">' +
                        cfg.i18n.empty + '</td></tr>'
                    );
                }
                pendingHistoryDelete = null;
            }).fail(function () {
                window.alert(cfg.i18n.deleteFail);
            }).always(function () {
                historyDeleteInFlight = false;
                if (busy) {
                    busy.clear($confirmBtn, { blockModal: true });
                }
            });
        });

        var deleteModalEl = document.getElementById('removeHistoryReport');
        if (deleteModalEl) {
            deleteModalEl.addEventListener('hidden.bs.modal', function () {
                pendingHistoryDelete = null;
                historyDeleteInFlight = false;
                if (window.cabinetButtonBusy) {
                    window.cabinetButtonBusy.clear($('#remove-history-report-confirm'), { blockModal: true });
                }
            });
        }
    }

    function needCheck() {
        if (collectActiveHistoryIds().length) {
            startHistoryPolling();
        }
    }

    function initDateRangePicker() {
        if (!window.cabinetMonitoringDateRange) {
            return;
        }

        window.cabinetMonitoringDateRange.init({
            $el: $('#date-range'),
            projectId: cfg.projectId,
            calendarUrl: cfg.routes.calendarPositions,
            getRegionId: function () {
                return $('#searchEngines').val() || null;
            },
            i18n: cfg.dateRangeI18n,
            includeModeRadios: false,
            onApply: function () {
                refreshHistoryEstimateHint();
            },
        });
    }

    $(function () {
        $submitBtn = $('#competitors-history-positions');
        submitBtnDefaultHtml = $submitBtn.html();

        var filter = localStorage.getItem('lr_redbox_monitoring_selected_filter');
        if (filter !== null) {
            filter = JSON.parse(filter);
            $('#searchEngines option[value="' + filter.val + '"]').prop('selected', true);
        }

        $('#searchEngines').on('change', function () {
            localStorage.setItem('lr_redbox_monitoring_selected_filter', JSON.stringify({ val: $(this).val() }));
            refreshHistoryEstimateHint();
        });

        initDateRangePicker();
        initCompetitorsSelect();
        refreshHistoryEstimateHint();

        $submitBtn.on('click', function () {
            var dateRange = $('#date-range').val();
            var regionId = $('#searchEngines').val();
            if (!dateRange || !regionId) {
                return;
            }

            startHistoryAnalyse(dateRange, regionId);
        });

        initHistoryDeleteModal();
        needCheck();

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                startHistoryPolling(0);
            }
        });
    });
}(window.jQuery, window.cabinetMonCompDynamicsConfig));
