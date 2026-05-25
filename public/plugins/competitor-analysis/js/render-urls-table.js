function renderUrlsTable(urls, pageLength) {
    $.each(urls, function (key, value) {
        const phrases = value.phrases || value['phrases'] || [];
        const phrasesBlock = buildPhrasesEyeToggle(phrases);

        const linkText = typeof escapeHtml === 'function' ? escapeHtml(key) : key;
        const linkHref = String(key).replace(/"/g, '&quot;');
        $('#urls-tbody').append(
            "<tr class='render'>" +
            "   <td class='word-wrap'><a class='cabinet-ca-url-link' href=\"" + linkHref + "\" target='_blank' rel='noopener'>" + linkText + "</a></td>" +
            "   <td class='cabinet-ca-url-phrases-cell'>" + phrasesBlock + "</td>" +
            "   <td class='cabinet-ca-url-count-cell'><span class='cabinet-ca-repeat-badge'>" + value['count'] + "</span></td>" +
            "</tr>"
        );
    });

    if ($.fn.dataTable && $.fn.dataTable.isDataTable('#urls-table')) {
        $('#urls-table').DataTable().destroy();
    }

    $('#urls-table').dataTable({
        order: [[2, 'desc']],
        pageLength: pageLength,
        searching: true,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Экспорт в Excel',
                title: 'Анализ URL',
                className: 'btn btn-secondary',
            },
        ],
        language: {
            paginate: {
                first: '«',
                last: '»',
                next: '»',
                previous: '«',
            },
        },
        drawCallback: function () {
            if (typeof initPhrasesEyeDropdowns === 'function') {
                initPhrasesEyeDropdowns($('#urls-table'));
            }
        },
    });

    $('#urls-table').closest('.table-responsive').addClass('cabinet-ca-urls-table-responsive');

    if (typeof initPhrasesEyeDropdowns === 'function') {
        initPhrasesEyeDropdowns($('#urls-table'));
    }

    $('.urls.mt-5').show();

    setTimeout(function () {
        $('#render-bar').hide(300);
    }, 1000);
}
