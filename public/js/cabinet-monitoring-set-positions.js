/**
 * /monitoring/set-positions — подстановка синтетических позиций за период.
 */
(function ($, window) {
    'use strict';

    const cfg = window.cabinetMonitoringSetPositionsConfig;
    if (!cfg) {
        return;
    }

    const $project = $('#mon-set-pos-project');
    const $engine = $('#mon-set-pos-engine');
    const $range = $('#mon-set-pos-range');
    const $run = $('#mon-set-pos-run');
    const $clearLog = $('#mon-set-pos-clear-log');
    const $logStatus = $('#mon-set-pos-log-status');

    let editor = null;
    let pickerStart = null;
    let pickerEnd = null;
    let runInFlight = false;

    function setLogStatus(text, badgeClass) {
        $logStatus
            .removeClass('text-bg-secondary text-bg-primary text-bg-success text-bg-danger')
            .addClass(badgeClass || 'text-bg-secondary')
            .text(text);
    }

    function appendLog(line) {
        if (!editor) {
            return;
        }
        const prefix = editor.getValue() ? '\n' : '';
        editor.replaceRange(prefix + line, CodeMirror.Pos(editor.lastLine()));
        editor.scrollTo(null, editor.getScrollInfo().height);
    }

    function updateRunButton() {
        const ready = Boolean(
            $project.val()
            && $engine.val()
            && pickerStart
            && pickerEnd
            && !runInFlight
        );
        $run.prop('disabled', !ready);
    }

    function loadEngines(projectId) {
        $engine.empty().append(
            $('<option>', {value: '', text: cfg.i18n.engineLoading})
        ).prop('disabled', true);
        $range.prop('disabled', true).val('');
        pickerStart = null;
        pickerEnd = null;
        updateRunButton();

        if (!projectId) {
            $engine.empty().append(
                $('<option>', {value: '', text: cfg.i18n.engineNeedProject})
            );
            return;
        }

        $.ajax({
            url: cfg.enginesUrl,
            type: 'GET',
            data: {id: projectId},
            dataType: 'json',
        }).done(function (data) {
            $engine.empty().append(
                $('<option>', {value: '', text: cfg.i18n.enginePlaceholder})
            );
            $.each(data, function (_key, value) {
                const label = value.location.name + ' — ' + value.location.lr;
                $engine.append($('<option>', {value: value.id, text: label}));
            });
            $engine.prop('disabled', false);
        }).fail(function () {
            $engine.empty().append(
                $('<option>', {value: '', text: cfg.i18n.engineNeedProject})
            );
            if (typeof toastr !== 'undefined') {
                toastr.error(cfg.i18n.runError);
            }
        });
    }

    function runFill() {
        if (runInFlight || !pickerStart || !pickerEnd) {
            return;
        }

        if (!window.confirm(cfg.i18n.runConfirm)) {
            return;
        }

        runInFlight = true;
        updateRunButton();

        if (window.cabinetButtonBusy) {
            window.cabinetButtonBusy.set($run, {label: cfg.i18n.runBusy});
        }

        setLogStatus(cfg.i18n.logRunning, 'text-bg-primary');
        appendLog('--- ' + cfg.i18n.runStarted + ' ' + pickerStart + ' … ' + pickerEnd + ' ---');

        $.ajax({
            url: cfg.runUrl,
            type: 'GET',
            data: {
                projectId: $project.val(),
                engineId: $engine.val(),
                startDate: pickerStart,
                endDate: pickerEnd,
            },
            dataType: 'json',
        }).done(function (response) {
            if (typeof toastr !== 'undefined') {
                toastr.success((response && response.message) || cfg.i18n.runDone);
            }
            setLogStatus(cfg.i18n.runDone, 'text-bg-success');
            appendLog('--- ' + cfg.i18n.runDone + ' ---');
        }).fail(function () {
            if (typeof toastr !== 'undefined') {
                toastr.error(cfg.i18n.runError);
            }
            setLogStatus(cfg.i18n.runError, 'text-bg-danger');
            appendLog('--- ' + cfg.i18n.runError + ' ---');
        }).always(function () {
            runInFlight = false;
            if (window.cabinetButtonBusy) {
                window.cabinetButtonBusy.clear($run, {label: cfg.i18n.runLabel});
            }
            updateRunButton();
        });
    }

    function bindEcho() {
        if (!window.Echo || !editor) {
            return;
        }

        window.Echo.channel('monitoring').listen('MonitoringPositionInsert', function (event) {
            if (!event.position) {
                return;
            }
            const pos = event.position;
            const query = pos.keyword && pos.keyword.query ? pos.keyword.query : '?';
            const created = pos.created_at || '';
            const line = cfg.i18n.logAdded + ': ' + created + ' ' + query + ' → ' + pos.position;
            appendLog(line);
        });

        window.Echo.channel('monitoring').listen('MonitoringPositionPassed', function (event) {
            const query = event.key && event.key.query ? event.key.query : '?';
            const line = cfg.i18n.logSkipped + ': ' + (event.date || '') + ' ' + query;
            appendLog(line);
        });
    }

    $(function () {
        if (typeof toastr !== 'undefined') {
            toastr.options = {preventDuplicates: true, timeOut: 5000};
        }

        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
        });

        editor = CodeMirror(document.getElementById('mon-set-pos-log-editor'), {
            value: '',
            mode: 'text/plain',
            theme: 'monokai',
            lineNumbers: true,
            readOnly: true,
            lineWrapping: true,
        });

        $range.daterangepicker({
            autoUpdateInput: false,
            locale: {
                format: 'YYYY-MM-DD',
                applyLabel: 'OK',
                cancelLabel: 'Отмена',
            },
        });

        $range.on('apply.daterangepicker', function (_ev, picker) {
            pickerStart = picker.startDate.format('YYYY-MM-DD');
            pickerEnd = picker.endDate.format('YYYY-MM-DD');
            $range.val(pickerStart + ' — ' + pickerEnd);
            updateRunButton();
        });

        $range.on('cancel.daterangepicker', function () {
            $range.val('');
            pickerStart = null;
            pickerEnd = null;
            updateRunButton();
        });

        $project.on('change', function () {
            loadEngines($(this).val());
        });

        $engine.on('change', function () {
            const enabled = Boolean($project.val() && $(this).val());
            $range.prop('disabled', !enabled);
            if (!enabled) {
                $range.val('');
                pickerStart = null;
                pickerEnd = null;
            }
            updateRunButton();
        });

        $run.on('click', runFill);

        $clearLog.on('click', function () {
            if (editor) {
                editor.setValue('');
            }
            setLogStatus(cfg.i18n.logIdle, 'text-bg-secondary');
            if (typeof toastr !== 'undefined') {
                toastr.info(cfg.i18n.logCleared);
            }
        });

        bindEcho();
        setLogStatus(cfg.i18n.logIdle, 'text-bg-secondary');
    });
})(jQuery, window);
