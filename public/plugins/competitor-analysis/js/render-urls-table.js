function textAnalyzerRedirectHref(url) {
    return '/redirect-to-text-analyzer/' + String(url).replace(/\\|\//g, 'abc');
}

function urlsTableAnalyzeTip() {
    if (window.competitorLocalization && window.competitorLocalization.analyzeText) {
        return String(window.competitorLocalization.analyzeText);
    }

    return 'Проанализировать текст';
}

function buildUrlTextAnalyzeAction(url) {
    const tip = urlsTableAnalyzeTip();
    const tipAttr = typeof escapeHtml === 'function' ? escapeHtml(tip) : tip;
    const href = typeof escapeHtml === 'function'
        ? escapeHtml(textAnalyzerRedirectHref(url))
        : textAnalyzerRedirectHref(url);

    return '<a class="btn btn-tool cabinet-ca-url-analyze" href="' + href + '" ' +
        'target="_blank" rel="noopener noreferrer" ' +
        'title="' + tipAttr + '" ' +
        'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' + tipAttr + '" ' +
        'aria-label="' + tipAttr + '">' +
        '<i class="fas fa-align-left" aria-hidden="true"></i>' +
        '</a>';
}

function disposeUrlsTableTooltips($root) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }

    ($root || $('#urls-table')).find('[data-bs-toggle="tooltip"]').each(function () {
        const inst = bootstrap.Tooltip.getInstance(this);
        if (inst) {
            inst.dispose();
        }
    });
}

function initUrlsTableTooltips($root) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }

    disposeUrlsTableTooltips($root);
    ($root || $('#urls-table')).find('.cabinet-ca-url-analyze[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip(this, {
            placement: 'top',
            container: 'body',
            trigger: 'hover',
        });
    });
}

function competitorDataTablesLanguage() {
    return window.competitorDataTablesLanguage || {
        search: 'Поиск:',
        lengthMenu: 'Показать _MENU_ записей',
        info: 'Показано от _START_ до _END_ из _TOTAL_ записей',
        infoEmpty: 'Показано от 0 до 0 из 0 записей',
        infoFiltered: '(отфильтровано из _MAX_ всего)',
        zeroRecords: 'Не найдено совпадающих записей',
        emptyTable: 'В таблице нет данных',
        paginate: {
            first: '«',
            last: '»',
            next: '»',
            previous: '«',
        },
    };
}

function renderUrlsTable(urls, pageLength) {
    disposeUrlsTableTooltips($('#urls-table'));

    $.each(urls || {}, function (key, value) {
        const phrases = (value && (value.phrases || value['phrases'])) || [];
        const phrasesBlock = typeof buildPhrasesList === 'function'
            ? buildPhrasesList(phrases)
            : (typeof buildPhrasesEyeToggle === 'function' ? buildPhrasesEyeToggle(phrases) : '');

        const linkText = typeof escapeHtml === 'function' ? escapeHtml(key) : key;
        const linkHref = String(key).replace(/"/g, '&quot;');
        const analyzeBtn = buildUrlTextAnalyzeAction(key);
        const count = value && value['count'] != null ? value['count'] : 0;

        $('#urls-tbody').append(
            "<tr class='render'>" +
            "   <td class='word-wrap cabinet-ca-url-link-cell'>" +
            "       <div class='cabinet-ca-url-row'>" +
            "           <a class='cabinet-ca-url-link' href=\"" + linkHref + "\" target='_blank' rel='noopener'>" + linkText + "</a>" +
            "           " + analyzeBtn +
            "       </div>" +
            "   </td>" +
            "   <td class='cabinet-ca-url-phrases-cell'>" + phrasesBlock + "</td>" +
            "   <td class='cabinet-ca-url-count-cell'><span class='cabinet-ca-repeat-badge'>" + count + "</span></td>" +
            "</tr>"
        );
    });

    if ($.fn.dataTable && $.fn.dataTable.isDataTable('#urls-table')) {
        try {
            $('#urls-table').DataTable().destroy();
        } catch (e) {
            // ignore
        }
    }

    try {
        $('#urls-table').dataTable({
            order: [[2, 'desc']],
            pageLength: pageLength,
            searching: true,
            autoWidth: false,
            dom: '<"row align-items-center g-2 cabinet-ca-dt-toolbar"<"col-auto"B><"col-auto ms-auto"f>>rt' +
                '<"row align-items-center g-2 cabinet-ca-dt-footer"<"col-auto"i><"col-auto ms-auto"p>>',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Экспорт в Excel',
                    title: 'Анализ URL',
                    className: 'btn btn-secondary',
                },
            ],
            drawCallback: function () {
                initUrlsTableTooltips($('#urls-table'));
            },
            language: competitorDataTablesLanguage(),
        });
    } catch (e) {
        // Без кнопок Excel — таблица всё равно должна показаться
        try {
            $('#urls-table').dataTable({
                order: [[2, 'desc']],
                pageLength: pageLength,
                searching: true,
                autoWidth: false,
                language: competitorDataTablesLanguage(),
            });
        } catch (e2) {
            if (typeof console !== 'undefined' && console.warn) {
                console.warn('urls DataTable init failed', e2);
            }
        }
    }

    $('#urls-table').closest('.table-responsive').removeClass('cabinet-ca-urls-table-responsive');
    initUrlsTableTooltips($('#urls-table'));

    $(document).off('focus.cabinetCaPhrasesField', '#urls-table .cabinet-ca-phrases-field')
        .on('focus.cabinetCaPhrasesField', '#urls-table .cabinet-ca-phrases-field', function () {
            const el = this;
            setTimeout(function () {
                el.select();
            }, 0);
        });

    $('.urls.mt-5').show();
}
