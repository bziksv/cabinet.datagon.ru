function renderPhrasesTable(phrases, count, words, onReady) {
    $('.phrases').show()
    $('#phrasesTBody').empty()

    if ($.fn.DataTable.fnIsDataTable($('#phrases'))) {
        $('#phrases').dataTable().fnDestroy();
    }

    var keys = Object.keys(phrases || {})
    keys.sort(function (a, b) {
        var topA = parseFloat((phrases[a] && (phrases[a].tfidfTop != null ? phrases[a].tfidfTop : phrases[a].score)) || 0)
        var topB = parseFloat((phrases[b] && (phrases[b].tfidfTop != null ? phrases[b].tfidfTop : phrases[b].score)) || 0)
        if (topB !== topA) {
            return topB - topA
        }

        var sitesA = parseInt((phrases[a] && phrases[a].numberOccurrences) || 0, 10)
        var sitesB = parseInt((phrases[b] && phrases[b].numberOccurrences) || 0, 10)

        return sitesB - sitesA
    })
    var rowIndex = 0
    var batchSize = 50
    var htmlParts = []

    function buildRows() {
        var end = Math.min(rowIndex + batchSize, keys.length)
        for (; rowIndex < end; rowIndex++) {
            htmlParts.push(renderPhraseTr(keys[rowIndex], phrases[keys[rowIndex]]))
        }

        if (rowIndex < keys.length) {
            setTimeout(buildRows, 0)
            return
        }

        $('#phrasesTBody').html(htmlParts.join(''))
        initPhrasesDataTable(count, words, onReady)
    }

    if (keys.length === 0) {
        initPhrasesDataTable(count, words, onReady)
        return
    }

    buildRows()
}

function initPhrasesDataTable(count, words, onReady) {
    var table = $('#phrases').DataTable({
        deferRender: true,
        order: [[1, 'desc']],
        pageLength: count,
        searching: true,
        dom: 'lBfrtip',
        orderCellsTop: false,
        buttons: [
            {extend: 'copy', text: words.copy || 'Копировать'},
            {extend: 'csv', text: words.csv || 'CSV'},
            {extend: 'excelHtml5', text: words.excel || 'Excel'},
        ],
        language: {
            paginate: {
                first: '«',
                last: '»',
                next: '»',
                previous: '«'
            },
        },
        oLanguage: {
            sSearch: (words.search || 'Поиск') + ':',
            sLengthMenu: (words.show || 'Показать') + ' _MENU_ ' + (words.records || 'записей'),
            sEmptyTable: words.noRecords || 'Нет данных',
            sInfo: (words.showing || 'Показано') + ' ' + (words.from || 'с') + '  _START_ ' + (words.to || 'по') + ' _END_ ' + (words.of || 'из') + ' _TOTAL_ ' + (words.entries || 'записей'),
        },
        drawCallback: function () {
            syncPhrasesHeaderSticky()
            decoratePhrasesWordCells()
        },
        initComplete: function () {
            ensurePhrasesTableScrollWrap()
            syncPhrasesHeaderSticky()
            decoratePhrasesWordCells()
            bindPhrasesCopyHandler(words.successCopied || words.copySuccess || 'Успешно скопированно')
            window.requestAnimationFrame(function () {
                syncPhrasesHeaderSticky()
            })
            setTimeout(syncPhrasesHeaderSticky, 100)
        }
    });

    ensurePhrasesTableScrollWrap()

    setTimeout(function () {
        $('.buttons-html5').addClass('btn btn-secondary')
        syncPhrasesHeaderSticky()
        decoratePhrasesWordCells()
        bindPhrasesCopyHandler(words.successCopied || words.copySuccess || 'Успешно скопированно')

        function isPhrasesInRange(min, max, target, settings) {
            if (settings.nTable.id !== 'phrases') {
                return true;
            }

            return (isNaN(min) && isNaN(max)) ||
                (isNaN(min) && target <= max) ||
                (min <= target && isNaN(max)) ||
                (min <= target && target <= max);
        }

        var filters = [
            {min: '#phrasesMinTfidfTop', max: '#phrasesMaxTfidfTop', col: 1},
            {min: '#phrasesMinTfidfSite', max: '#phrasesMaxTfidfSite', col: 2},
            {min: '#phrasesMinBm25Top', max: '#phrasesMaxBm25Top', col: 3},
            {min: '#phrasesMinBm25Site', max: '#phrasesMaxBm25Site', col: 4},
            {min: '#phrasesMinSites', max: '#phrasesMaxSites', col: 5},
            {min: '#phrasesMinMedian', max: '#phrasesMaxMedian', col: 6},
            {min: '#phrasesMinAvg', max: '#phrasesMaxAvg', col: 7},
            {min: '#phrasesMinOurSite', max: '#phrasesMaxOurSite', col: 8},
        ];

        filters.forEach(function (filter) {
            $.fn.dataTable.ext.search.push(function (settings, data) {
                var min = parseFloat($(filter.min).val());
                var max = parseFloat($(filter.max).val());
                var value = parseFloat(data[filter.col]);
                return isPhrasesInRange(min, max, value, settings);
            });
            $(filter.min + ', ' + filter.max).on('keyup change', function () {
                table.draw();
            });
        });

        if (typeof onReady === 'function') {
            onReady();
        }
    }, 400);
}

function ensurePhrasesTableScrollWrap() {
    var $table = $('#phrases')
    if (!$table.length || $table.parent().hasClass('relevance-tlp-table-scroll')) {
        return
    }

    $table.wrap('<div class="relevance-tlp-table-scroll"></div>')
}

function syncPhrasesHeaderSticky() {
    var $filterRow = $('#phrases thead .phrases-thead__filters')
    var $dividerRow = $('#phrases thead .phrases-thead__divider')
    var $scroll = $('#phrases_wrapper .relevance-tlp-table-scroll')

    if (!$filterRow.length || !$scroll.length) {
        return
    }

    var filterHeight = $filterRow.outerHeight() || 0
    var dividerHeight = $dividerRow.length ? ($dividerRow.outerHeight() || 0) : 0

    $scroll.css('--phrases-filter-row-height', filterHeight + 'px')
    $scroll.css('--phrases-titles-row-top', (filterHeight + dividerHeight) + 'px')
}

function formatPhraseMetric(number) {
    var value = parseFloat(number)
    if (isNaN(value) || value === 0) {
        return '0'
    }

    if (value >= 0.01) {
        return (Math.round(value * 10000) / 10000).toString()
    }

    if (typeof crop === 'function') {
        return crop(value)
    }

    return value.toFixed(4).replace(/\.?0+$/, '')
}

function renderPhraseTr(key, item) {
    var links = '';

    $.each(item['occurrences'] || {}, function (elem, value) {
        try {
            var url = new URL(elem)
            links += "<a href='" + elem + "' target='_blank'>" + url.host + "</a>(" + value + ")<br>"
        } catch (e) {
            links += "<a href='" + elem + "' target='_blank'>" + elem + "</a>(" + value + ")<br>"
        }
    });

    var ourSiteCount = parseInt(item['totalRepeatMainPage'], 10) || 0
    var ourSiteWarning = ourSiteCount === 0 ? " class='bg-warning-elem'" : ''
    var median = item['medianInCompetitors'] != null ? item['medianInCompetitors'] : 0
    var avg = item['avgInTotalCompetitors'] != null ? item['avgInTotalCompetitors'] : 0

    return "<tr class='render'>" +
        "<td><div class='phrases-word-cell'>" +
        "<span class='phrases-word-text'>" + escapePhraseHtml(key) + "</span> " +
        "<i class='fa fa-copy copy-icon' role='button' tabindex='0' title='Копировать'></i>" +
        "</div></td>" +
        "<td>" + formatPhraseMetric(item['tfidfTop'] ?? item['score'] ?? 0) + "</td>" +
        "<td>" + formatPhraseMetric(item['tfidfSite'] ?? 0) + "</td>" +
        "<td>" + formatPhraseMetric(item['bm25Top'] ?? 0) + "</td>" +
        "<td>" + formatPhraseMetric(item['bm25Site'] ?? 0) + "</td>" +
        "<td>" + item['numberOccurrences'] +
        "<span class='__helper-link ui_tooltip_w'>" +
        "    <i class='fa fa-paperclip'></i>" +
        "    <span class='ui_tooltip __right' style='min-width: 250px; max-width: 450px;'>" +
        "        <span class='ui_tooltip_content'>" + links + "</span>" +
        "    </span>" +
        "</span>" +
        "</td>" +
        "<td>" + median + "</td>" +
        "<td>" + avg + "</td>" +
        "<td" + ourSiteWarning + ">" + ourSiteCount + "</td>" +
        "</tr>"
}

function escapePhraseHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

function decoratePhrasesWordCells() {
    $('#phrases tbody td:first-child').each(function () {
        var cell = $(this)
        if (cell.find('.phrases-word-text').length) {
            return
        }

        var phraseText = cell.text().trim().replace(/\s+/g, ' ')
        if (!phraseText) {
            return
        }

        cell.empty().append(
            $('<div class="phrases-word-cell"></div>').append(
                $('<span class="phrases-word-text"></span>').text(phraseText),
                ' ',
                $('<i class="fa fa-copy copy-icon" role="button" tabindex="0" title="Копировать"></i>')
            )
        )
    })
}

function bindPhrasesCopyHandler(successMessage) {
    $(document)
        .off('click.phrasesCopy', '#phrases .copy-icon')
        .on('click.phrasesCopy', '#phrases .copy-icon', function (e) {
            e.preventDefault()
            e.stopPropagation()

            var text = $(this).closest('td').find('.phrases-word-text').text().trim()
            if (!text) {
                return
            }

            if (typeof copyUnigramWord === 'function') {
                copyUnigramWord(text, successMessage)
            }
        })
}
