/**
 * Мастер создания/редактирования проекта мониторинга (/monitoring/create).
 */
(function (window, $) {
    'use strict';

    const cfg = window.cabinetMonCreateConfig || {};
    const DATA_TABLE_ID = '#myTable';
    const REGIONS_CLASS = '.cabinet-mon-create-region-select';

    let stepper = null;
    let dataTable = null;
    let dataTableEditor = null;
    let projectId = null;
    let projectSaving = false;
    let projectSavedSnapshot = null;

    function setWizardBusy(busy) {
        const $next = $('.cabinet-mon-create__btn-next');
        if ($next.length) {
            $next.prop('disabled', !!busy);
            $next.toggleClass('disabled', !!busy);
        }
    }

    function getProjectId() {
        if (projectId) {
            return projectId;
        }
        if (window.hash && typeof window.hash.getParam === 'function') {
            const raw = window.hash.getParam('id');
            if (raw && raw[0]) {
                projectId = parseInt(raw[0], 10) || null;
            }
        }
        return projectId;
    }

    function setProjectId(id) {
        projectId = parseInt(id, 10) || null;
        if (window.hash && projectId) {
            window.hash.setHash({ id: [String(projectId)] });
        }
        updateStatusBanner();
        if (projectId) {
            loadKeywordGroups();
            initDataTable(projectId);
        }
    }

    function updateStatusBanner() {
        const $banner = $('#cabinet-mon-create-status');
        if (!$banner.length) {
            return;
        }
        const id = getProjectId();
        if (!id) {
            $banner
                .removeClass('alert-success alert-warning')
                .addClass('alert-info')
                .html(cfg.i18n.statusNew || 'Сначала сохраните проект на шаге 1.');
            return;
        }
        const name =
            $('input[name="name"]').val() ||
            (cfg.i18n.statusProject || 'Проект') + ' #' + id;
        $banner
            .removeClass('alert-info alert-warning')
            .addClass('alert-success')
            .html(
                (cfg.i18n.statusSaved || 'Проект сохранён:') +
                    ' <strong>' +
                    $('<span>').text(name).html() +
                    '</strong> (ID ' +
                    id +
                    ')'
            );
    }

    function domainPattern() {
        return /^[a-z0-9а-яё.-]+\.[a-zа-я]{2,12}$/i;
    }

    function normalizeDomain(url) {
        return String(url || '')
            .trim()
            .toLowerCase()
            .replace(/^https?:\/\//, '')
            .replace(/^www\./, '')
            .replace(/\/.*$/, '');
    }

    function readProjectForm() {
        return {
            name: String($('input[name="name"]').val() || '').trim(),
            url: normalizeDomain($('input[name="url"]').val()),
        };
    }

    function projectFormSnapshot() {
        const f = readProjectForm();
        return f.name + '|' + f.url;
    }

    function showError(msg) {
        if (typeof toastr !== 'undefined') {
            toastr.error(msg);
        }
    }

    function showSuccess(msg) {
        if (typeof toastr !== 'undefined') {
            toastr.success(msg);
        }
    }

    const Modes = {
        template: null,
        data: {},
        render: function () {
            if (!Object.keys(this.data).length) {
                return;
            }
            const card = $('<div />');
            $.each(this.data, function (i, data) {
                if (data.length) {
                    card.append(Modes.card(data, i));
                }
            });
            $('.mode-scan').html(card);
            card.find('.times').inputmask('hh:mm', {
                placeholder: window.moment ? moment().format('H:mm') : '12:00',
            });
            card.find('.time-reset').click(function () {
                $(this).closest('.input-group').find('.times').val('');
                const $days = $(this).closest('.form-group').find('.select-days');
                if ($days.length) {
                    $days[0].selectedIndex = -1;
                }
            });
        },
        card: function (data, key) {
            const card = $('<div />', { class: 'card' });
            const cardHeader = $('<div />', { class: 'card-header' });
            const cardBody = $('<div />', { class: 'card-body' });
            cardHeader.append(
                $('<h3 />', { class: 'card-title' })
                    .css('text-transform', 'capitalize')
                    .text(key)
            );
            cardBody.append(Modes.setContent(data));
            return card.append(cardHeader, cardBody);
        },
        setContent: function (data) {
            const template = $('<div />');
            $.each(data, function (i, item) {
                const label = item.name + ' [' + item.lr + ']';
                const inputData = {
                    id: item.id,
                    val: {
                        time: item.time,
                        weekdays: {
                            time: item.weekdays ? item.time : null,
                            days: item.weekdays,
                        },
                        monthday: item.monthday,
                        day: {
                            days: item.day,
                            time: item.day ? item.time : null,
                        },
                    },
                    label: label,
                    lr: item.lr,
                };
                if (item.weekdays || item.monthday || item.day) {
                    inputData.val.time = null;
                }
                template.append(Modes.timeTemplate(inputData).addClass('d-none'));
                template.append(Modes.monthTemplate(inputData).addClass('d-none'));
                template.append(Modes.weeksTemplate(inputData).addClass('d-none'));
                template.append(Modes.rangeTemplate(inputData).addClass('d-none'));
            });
            Modes.template = template;
            return template;
        },
        timeTemplate: function (data) {
            const form = Modes.formGroupTemplate('times', data.lr);
            return form.append(
                $('<label />').text(data.label),
                Modes.inputTime(data, data.val.time)
            );
        },
        weeksTemplate: function (data) {
            const form = Modes.formGroupTemplate('weeks', data.lr);
            const options = [
                { text: 'Понедельник', val: '1' },
                { text: 'Вторник', val: '2' },
                { text: 'Среда', val: '3' },
                { text: 'Четверг', val: '4' },
                { text: 'Пятница', val: '5' },
                { text: 'Суббота', val: '6' },
                { text: 'Воскресенье', val: '0' },
            ];
            const selectContent = $('<select />', {
                name: 'weekdays',
                class: 'form-control select-days mb-2',
                multiple: 'true',
                size: 7,
            }).attr('data-id', data.id);
            $.each(options, function (i, obj) {
                const selected =
                    data.val.weekdays.days &&
                    data.val.weekdays.days.indexOf(obj.val) !== -1;
                selectContent.append(
                    $('<option />', { selected: selected, value: obj.val }).text(obj.text)
                );
            });
            return form.append(
                $('<label />').text(data.label),
                selectContent,
                Modes.inputTime(data, data.val.weekdays.time)
            );
        },
        rangeTemplate: function (data) {
            const form = Modes.formGroupTemplate('ranges', data.lr);
            return form.append(
                $('<label />').text(data.label),
                $('<input />', {
                    class: 'form-control',
                    type: 'number',
                    min: '1',
                    max: '31',
                    name: 'monthday',
                    value: data.val.monthday,
                    placeholder: 'Число месяца 1–31',
                }).attr('data-id', data.id)
            );
        },
        monthTemplate: function (data) {
            const form = Modes.formGroupTemplate('months', data.lr);
            return form.append(
                $('<label />').text(data.label),
                $('<input />', {
                    class: 'form-control mb-2',
                    type: 'number',
                    min: '1',
                    max: '28',
                    name: 'day',
                    value: data.val.day.days,
                    placeholder: 'День месяца 1–28',
                }).attr('data-id', data.id),
                Modes.inputTime(data, data.val.day.time)
            );
        },
        formGroupTemplate: function (mode, lr) {
            return $('<div />', {
                class: 'form-group',
                'data-mode': mode,
                'data-lr': lr,
            });
        },
        inputTime: function (data, val) {
            const group = $('<div />', { class: 'input-group' });
            const input = $('<input />', {
                class: 'form-control times',
                type: 'text',
                name: 'time',
                value: val || '',
                placeholder: 'Время (24 ч)',
            }).attr('data-id', data.id);
            const icon = $('<span />', { class: 'input-group-text time-reset' }).append(
                $('<i />', { class: 'far fa-times-circle' })
            );
            return group.append(input, icon);
        },
        setData: function (data) {
            if (data && Object.keys(data).length > 0) {
                this.data = data;
            }
            return this;
        },
    };

    const Parts = {
        part: null,
        project: function (event) {
            const form = readProjectForm();
            if (!form.name) {
                event.preventDefault();
                showError(cfg.i18n.errName || 'Укажите название проекта');
                return false;
            }
            if (!form.url) {
                event.preventDefault();
                showError(cfg.i18n.errUrl || 'Укажите домен проекта');
                return false;
            }
            if (!domainPattern().test(form.url)) {
                event.preventDefault();
                showError(cfg.i18n.errUrlFormat || 'Домен в формате example.com');
                return false;
            }

            const id = getProjectId();
            const unchanged = projectSavedSnapshot && projectSavedSnapshot === projectFormSnapshot();
            if (id && unchanged) {
                return true;
            }

            event.preventDefault();
            if (projectSaving) {
                return false;
            }

            projectSaving = true;
            setWizardBusy(true);
            const request = id ? cfg.urls.update : cfg.urls.create;
            const payload = id ? { id: id, name: form.name, url: form.url } : form;

            window.axios
                .post(request, payload)
                .then(function (response) {
                    const newId =
                        (response.data && response.data.id) || response.data;
                    setProjectId(newId);
                    projectSavedSnapshot = projectFormSnapshot();
                    $('input[name="url"]').val(form.url);
                    showSuccess(cfg.i18n.saved || 'Проект сохранён');
                    projectSaving = false;
                    setWizardBusy(false);
                    if (stepper) {
                        stepper.next();
                    }
                })
                .catch(function (err) {
                    projectSaving = false;
                    setWizardBusy(false);
                    const msg =
                        (err.response && err.response.data && err.response.data.message) ||
                        cfg.i18n.saveError ||
                        'Не удалось сохранить проект';
                    showError(msg);
                });

            return false;
        },
        keywords: function (event) {
            const id = getProjectId();
            if (!id) {
                event.preventDefault();
                showError(cfg.i18n.needProject || 'Сначала сохраните проект');
                return false;
            }
            if (!$.fn.dataTable.isDataTable(DATA_TABLE_ID)) {
                event.preventDefault();
                showError(cfg.i18n.needTable || 'Таблица запросов ещё не готова');
                return false;
            }
            const count = dataTable.rows({ search: 'applied' }).count();
            if (count < 1) {
                event.preventDefault();
                showError(
                    cfg.i18n.needKeywords ||
                        'Добавьте хотя бы один запрос и нажмите «Добавить запросы»'
                );
                return false;
            }
            return true;
        },
        competitors: function (event) {
            const id = getProjectId();
            if (!id) {
                event.preventDefault();
                showError(cfg.i18n.needProject || 'Сначала сохраните проект');
                return false;
            }
            const textarea = Parts.part.find('#textarea-competitors');
            const list = _.compact(textarea.val().split(/[\r\n]+/));
            let ok = true;
            $.each(list, function (index, value) {
                const domain = normalizeDomain(value);
                if (domain && !domainPattern().test(domain)) {
                    event.preventDefault();
                    ok = false;
                    showError((cfg.i18n.errDomain || 'Неверный домен:') + ' ' + domain);
                }
            });
            if (!ok) {
                return false;
            }
            if (textarea.val().trim().length > 0) {
                window.axios.post(cfg.urls.competitors, {
                    id: id,
                    domains: textarea.val(),
                });
            }
            return true;
        },
        regions: function (event) {
            const id = getProjectId();
            if (!id) {
                event.preventDefault();
                showError(cfg.i18n.needProject || 'Сначала сохраните проект');
                return false;
            }
            if (!$(REGIONS_CLASS).find('option').length) {
                event.preventDefault();
                showError(cfg.i18n.needRegions || 'Выберите хотя бы один регион');
                return false;
            }
            window.axios
                .post(cfg.urls.regions, { action: 'get', id: id })
                .then(function (response) {
                    const data = { google: [], yandex: [] };
                    $.each(response.data, function (i, item) {
                        data[item.engine].push(item);
                    });
                    Modes.setData(data).render();
                    $('#mode-scan').trigger('change');
                });
            return true;
        },
        scan: function () {
            if (cfg.onFreeTariff) {
                return true;
            }
            const id = getProjectId();
            if (!id) {
                return false;
            }
            const inputs = Parts.part.find('input, select');
            const data = [];
            inputs.each(function (i, input) {
                const el = $(input);
                if (el.val().length > 0 && el.data('id')) {
                    data.push({ id: el.data('id'), name: el.attr('name'), val: el.val() });
                }
            });
            window.axios.post(cfg.urls.regions, {
                action: 'update',
                id: id,
                data: data,
            });
            return true;
        },
    };

    function currentPanelTrigger(panel, event) {
        Parts.part = panel;
        switch (panel.attr('id')) {
            case 'project-part':
                return Parts.project(event);
            case 'keywords-part':
                return Parts.keywords(event);
            case 'competitors-part':
                return Parts.competitors(event);
            case 'regions-part':
                return Parts.regions(event);
            case 'scan-part':
                return Parts.scan(event);
            default:
                return true;
        }
    }

    function nextPanelTrigger(panel) {
        if (panel.attr('id') === 'competitors-part') {
            const id = getProjectId();
            if (!id) {
                return;
            }
            window.axios
                .get(cfg.urls.competitors, { params: { id: id } })
                .then(function (response) {
                    panel.find('#textarea-competitors').val(response.data || '');
                });
        }
        if (panel.attr('id') === 'regions-part') {
            const id = getProjectId();
            const selects = panel.find(REGIONS_CLASS);
            selects.val(null).trigger('change');
            selects.find('option').remove();
            if (!id) {
                return;
            }
            window.axios
                .post(cfg.urls.regions, { action: 'get', id: id })
                .then(function (response) {
                    $.each(response.data, function (i, data) {
                        selects.each(function (j, el) {
                            const elem = $(el);
                            if (elem.data('search') === data.engine) {
                                const option = new Option(
                                    data.name + ' [' + data.lr + ']',
                                    data.lr,
                                    true,
                                    true
                                );
                                elem.append(option).trigger('change');
                            }
                        });
                    });
                });
        }
    }

    function initDataTable(pid) {
        if ($.fn.dataTable.isDataTable(DATA_TABLE_ID)) {
            return;
        }
        dataTableEditor = new $.fn.dataTable.Editor({
            ajax: {
                url: cfg.urls.queries,
                data: { id: pid },
            },
            table: DATA_TABLE_ID,
            fields: [
                { name: 'query' },
                { name: 'page' },
                { name: 'group' },
                {
                    name: 'target',
                    type: 'select',
                    options: [
                        { label: '1', value: '1' },
                        { label: '3', value: '3' },
                        { label: '5', value: '5' },
                        { label: '10', value: '10' },
                        { label: '20', value: '20' },
                        { label: '50', value: '50' },
                        { label: '100', value: '100' },
                    ],
                },
            ],
        });

        $(DATA_TABLE_ID).on('click', 'tbody td:not(:last-child) i', function () {
            dataTableEditor.inline($(this).parent(), {
                onBlur: 'submit',
                submit: 'allIfChanged',
            });
        });

        $(DATA_TABLE_ID).on('click', 'td.editor-delete', function (e) {
            e.preventDefault();
            dataTableEditor.remove($(this).closest('tr'), {
                title: cfg.i18n.deleteTitle || 'Удалить',
                message: cfg.i18n.deleteMsg || 'Удалить запись?',
                buttons: cfg.i18n.deleteBtn || 'Удалить',
            });
        });

        const editIcon = function (data, type) {
            if (type === 'display') {
                return (
                    data +
                    ' <i class="fa fa-pencil" style="opacity:0.5;font-size:12px;cursor:pointer"/>'
                );
            }
            return data;
        };

        dataTable = $(DATA_TABLE_ID).DataTable({
            rowId: 'id',
            processing: true,
            autoWidth: false,
            ordering: false,
            searching: false,
            lengthMenu: [10, 30, 50, 100, 500],
            dom: '<"card-header"<"card-title"><"float-end"f><"float-end"l>><"card-body p-0"rt><"card-footer clearfix"p>',
            pagingType: 'simple_numbers',
            language: cfg.dtLang || {},
            serverSide: true,
            ajax: {
                url: cfg.urls.queries,
                type: 'GET',
                data: { id: pid },
            },
            columns: [
                { data: 'query', title: cfg.i18n.colQuery || 'Запрос', render: editIcon },
                {
                    data: 'page',
                    title: cfg.i18n.colPage || 'Релевантная страница',
                    render: editIcon,
                },
                { data: 'group', title: cfg.i18n.colGroup || 'Группа' },
                { data: 'target', title: cfg.i18n.colTarget || 'Цель', render: editIcon },
                {
                    title: '',
                    width: '40px',
                    className: 'dt-center editor-delete',
                    orderable: false,
                    render: function () {
                        return (
                            '<a href="#" class="btn btn-sm btn-default" title="Удалить">' +
                            '<i class="fas fa-trash"></i></a>'
                        );
                    },
                },
            ],
            initComplete: function () {
                const title = $('div.card-title');
                title.text(cfg.i18n.queryList || 'Список запросов');
            },
        });
    }

    function loadKeywordGroups() {
        const id = getProjectId();
        const keywordSelect2 = $('#keyword-groups');
        if (!keywordSelect2.length) {
            return;
        }
        window.axios
            .get(cfg.urls.groups, { params: { id: id || 0 } })
            .then(function (response) {
                keywordSelect2.empty();
                const options = [];
                const data = response.data;
                if ($.isArray(data)) {
                    $.each(data, function (i, val) {
                        const name = val.name || val;
                        options.push(new Option(name, name, false, false));
                    });
                } else if (data) {
                    options.push(new Option(data, data, false, false));
                }
                if (!options.length) {
                    options.push(new Option(cfg.i18n.mainGroup || 'Основная', 'Основная', true, true));
                }
                keywordSelect2.select2({ width: '100%' });
                keywordSelect2.append(options).trigger('change');
            })
            .catch(function () {
                keywordSelect2.select2({ width: '100%' });
            });
    }

    function createQueries(data) {
        const dataSet = { query: {}, page: {}, group: {}, target: {} };
        $.each(data, function (i, v) {
            dataSet.query[i] = v.query;
            dataSet.page[i] = v.page;
            dataSet.group[i] = v.group;
            dataSet.target[i] = v.target;
        });
        dataTableEditor.create(data.length, false);
        dataTableEditor.multiSet(dataSet);
        dataTableEditor.submit();
    }

    function bindRegionsSelect2() {
        $(REGIONS_CLASS).select2({
            placeholder: cfg.i18n.regionPlaceholder || 'Начните вводить город…',
            minimumInputLength: 2,
            width: '100%',
            language: {
                inputTooShort: function () {
                    return cfg.i18n.regionType || 'Введите название региона';
                },
                searching: function () {
                    return cfg.i18n.regionSearching || 'Поиск…';
                },
                noResults: function () {
                    return cfg.i18n.regionNotFound || 'Ничего не найдено';
                },
                errorLoading: function () {
                    return cfg.i18n.regionLoadError || 'Не удалось загрузить список';
                },
            },
            ajax: {
                delay: 400,
                url: cfg.urls.location,
                dataType: 'json',
                data: function (params) {
                    return { name: params.term, searchEngine: $(this).data('search') };
                },
                processResults: function (data) {
                    const list = Array.isArray(data) ? data : [];
                    return {
                        results: $.map(list, function (obj) {
                            const lr = obj.lr != null ? String(obj.lr) : '';
                            const name = obj.name != null ? String(obj.name) : '';
                            return {
                                id: lr,
                                source: obj.source,
                                name: name,
                                text: name + (lr ? ' [' + lr + ']' : ''),
                            };
                        }),
                    };
                },
            },
        });

        $(REGIONS_CLASS).on('select2:select', function (e) {
            const id = getProjectId();
            if (!id) {
                showError(cfg.i18n.needProject || 'Сначала сохраните проект');
                return;
            }
            const data = e.params.data;
            window.axios.post(cfg.urls.regions, {
                action: 'create',
                id: id,
                engine: data.source,
                lr: data.id,
            });
        });

        $(REGIONS_CLASS).on('select2:unselect', function (e) {
            const id = getProjectId();
            if (!id) {
                return;
            }
            const data = e.params.data;
            window.axios.post(cfg.urls.regions, {
                action: 'remove',
                id: id,
                engine: data.source || $(e.params.data.element).parent().data('search'),
                lr: data.id,
            });
            $(data.element).remove();
        });
    }

    function bindKeywordsAdd() {
        $('#add-keywords').on('click', function () {
            const id = getProjectId();
            if (!id) {
                showError(cfg.i18n.needProject || 'Сначала сохраните проект');
                return false;
            }
            if (!$.fn.dataTable.isDataTable(DATA_TABLE_ID)) {
                showError(cfg.i18n.needTable || 'Дождитесь загрузки таблицы');
                return false;
            }

            const csv = $('#csv-keywords');
            const textarea = $('#textarea-keywords');
            const duplicates = $('#remove-duplicates');
            const groupInput = $('#keyword-groups');
            const target = $('select[name="target"]');
            const delimiter = $('#csv-delimiter').val();

            if (csv[0].files.length) {
                if (csv[0].files[0].type !== 'text/csv' && !csv[0].files[0].name.match(/\.csv$/i)) {
                    showError(cfg.i18n.errCsv || 'Загрузите файл .csv');
                    return false;
                }
                Papa.parse(csv[0].files[0], {
                    delimiter: delimiter,
                    skipEmptyLines: 'greedy',
                    complete: function (result) {
                        let rows = result.data;
                        if (duplicates.prop('checked')) {
                            rows = $.unique(rows);
                        }
                        const data = [];
                        $.each(rows, function (i, value) {
                            let group = groupInput.find('option:selected').text();
                            if (value[1] && String(value[1]).trim()) {
                                group = value[1];
                            }
                            group = String(group).replace(/[!\[\]]/g, '');
                            data.push({
                                query: value[0],
                                page: value[2] || '',
                                group: group,
                                target: target.val(),
                            });
                        });
                        if (data.length) {
                            csv.val('');
                            textarea.val('');
                            createQueries(data);
                            showSuccess(
                                (cfg.i18n.added || 'Добавлено запросов:') + ' ' + data.length
                            );
                        }
                    },
                });
                return false;
            }

            if (textarea.val().trim().length > 0) {
                let list = _.compact(textarea.val().split(/[\r\n]+/));
                if (duplicates.prop('checked')) {
                    list = $.unique(list);
                }
                const relevant = $('#relevant-url').val();
                const data = [];
                $.each(list, function (i, value) {
                    data.push({
                        query: value,
                        page: relevant,
                        group: groupInput.find('option:selected').text(),
                        target: target.val(),
                    });
                });
                if (data.length) {
                    textarea.val('');
                    createQueries(data);
                    showSuccess((cfg.i18n.added || 'Добавлено запросов:') + ' ' + data.length);
                    return false;
                }
            }

            showError(cfg.i18n.errKeywords || 'Введите или загрузите список запросов');
            return false;
        });

        $('#create-group').on('click', function () {
            const input = $(this).closest('.input-group').find('input');
            if (input.val()) {
                const keywordSelect2 = $('#keyword-groups');
                keywordSelect2.append(new Option(input.val(), input.val(), false, true)).trigger('change');
                showSuccess(cfg.i18n.groupAdded || 'Группа добавлена');
                input.val('');
            }
        });
    }

    function bindScanMode() {
        $('#mode-scan').on('change', function () {
            const self = $(this);
            const option = self.val();
            const modes = $('.mode-scan').find('.form-group');
            const $calloutInfo = $('.card-body').find('#callout-info');
            modes.addClass('d-none');
            modes.find('code').remove();
            modes.find('input, select').removeAttr('disabled');
            const selected = modes.filter(function () {
                return $(this).data('mode') === option;
            });
            selected.removeClass('d-none');
            const hidden = modes.filter(function () {
                return $(this).hasClass('d-none');
            });
            if (option === 'ranges') {
                $calloutInfo.addClass('callout callout-info');
                $calloutInfo.html(cfg.i18n.rangesHint || '');
            } else {
                $calloutInfo.html('').removeClass('callout callout-info');
            }
            $.each(selected, function (i, elem) {
                const el = $(elem);
                const lr = el.data('lr');
                const hiddenRegion = hidden.filter(function () {
                    return $(this).data('lr') === lr;
                });
                $.each(hiddenRegion, function (inc, region) {
                    const values = $(region).find('input, select').val();
                    if (values.length > 0) {
                        el.find('label').append(
                            $('<code />').text(
                                ' ' +
                                    (cfg.i18n.modeSet || 'Режим задан') +
                                    ': ' +
                                    self.find('option[value="' + $(region).data('mode') + '"]').text()
                            )
                        );
                        el.find('input, select').attr('disabled', 'disabled');
                    }
                });
            });
        });
    }

    function loadExistingProject() {
        const id = getProjectId();
        if (!id) {
            updateStatusBanner();
            return;
        }
        window.axios.post(cfg.urls.edit, { id: id }).then(function (response) {
            if (!response.data) {
                window.location.hash = '';
                projectId = null;
                updateStatusBanner();
                return;
            }
            const project = $(window.stepper._stepsContents[0]);
            project.find('input[name="name"]').val(response.data.name);
            project.find('input[name="url"]').val(response.data.url);
            projectSavedSnapshot = projectFormSnapshot();
            setProjectId(response.data.id);
        });
    }

    function ensureFirstStepVisible(stepperEl) {
        if (!stepperEl) {
            return;
        }
        const panels = stepperEl.querySelectorAll('.bs-stepper-content .content');
        const active = stepperEl.querySelector('.bs-stepper-content .content.active');
        if (panels.length && !active) {
            panels[0].classList.add('active');
            panels[0].classList.add('dstepper-block');
        }
    }

    function init() {
        if (typeof toastr !== 'undefined' && cfg.toastr) {
            toastr.options = cfg.toastr;
        }

        const el = document.querySelector('.bs-stepper');
        if (!el) {
            return;
        }
        if (typeof Stepper === 'undefined') {
            showError('Stepper не загрузился. Обновите страницу (Ctrl+F5).');
            ensureFirstStepVisible(el);
            return;
        }

        stepper = new Stepper(el);
        window.stepper = stepper;
        ensureFirstStepVisible(el);

        el.addEventListener('show.bs-stepper', function (event) {
            const nextStep = event.detail.indexStep;
            let currentStep = nextStep;
            if (currentStep > 0) {
                currentStep--;
            }
            const panels = $('.bs-stepper-content .content');
            const currentPanel = panels.eq(currentStep);
            const nextPanel = panels.eq(nextStep);
            if (!currentPanelTrigger(currentPanel, event)) {
                return;
            }
            nextPanelTrigger(nextPanel);
        });

        bindRegionsSelect2();
        bindKeywordsAdd();
        bindScanMode();
        loadExistingProject();
        if (!getProjectId()) {
            loadKeywordGroups();
        }
    }

    $(init);
})(window, jQuery);
