/**
 * DataTables export: в заголовках только короткое имя колонки, без подсказок (?).
 */
(function (window, $) {
    'use strict';

    function cleanExportHeader(node, data) {
        if (node && node.getAttribute) {
            var explicit = node.getAttribute('data-export-title');
            if (explicit) {
                return String(explicit).replace(/\s+/g, ' ').trim();
            }

            var clone = node.cloneNode(true);
            var $clone = $(clone);
            $clone.find('.__helper-link, .ui_tooltip, .ui_tooltip_content, .fa, input, select, textarea, script, style').remove();
            var text = String($clone.text() || '').replace(/\s+/g, ' ').trim();
            if (text) {
                return text;
            }
        }

        if (typeof data === 'string' && data.indexOf('<') !== -1) {
            var $html = $('<div>').html(data);
            $html.find('.__helper-link, .ui_tooltip, .ui_tooltip_content, .fa, input, select, textarea').remove();
            return String($html.text() || '').replace(/\s+/g, ' ').trim();
        }

        return data == null ? '' : String(data).replace(/\s+/g, ' ').trim();
    }

    function exportOptions(extra) {
        var opts = {
            format: {
                header: function (data, columnIdx, node) {
                    return cleanExportHeader(node, data);
                }
            }
        };

        if (extra && typeof extra === 'object') {
            $.extend(true, opts, extra);
        }

        return opts;
    }

    function customizeDataKeepTitleRow(data) {
        if (Array.isArray(data.headerStructure) && data.headerStructure.length > 1) {
            data.headerStructure = [data.headerStructure[data.headerStructure.length - 1]];
        }
        if (Array.isArray(data.header)) {
            data.header = data.header.map(function (cell, idx) {
                return cleanExportHeader(null, cell);
            });
        }
    }

    function exportButtons(labels, extraExportOptions) {
        labels = labels || {};
        var eo = exportOptions(extraExportOptions);

        return [
            {extend: 'copyHtml5', text: labels.copy || 'Copy', exportOptions: eo},
            {extend: 'csvHtml5', text: labels.csv || 'CSV', exportOptions: eo},
            {
                extend: 'excelHtml5',
                text: labels.excel || 'Excel',
                exportOptions: eo,
                customizeData: customizeDataKeepTitleRow
            }
        ];
    }

    window.relevanceCleanExportHeader = cleanExportHeader;
    window.relevanceDtExportOptions = exportOptions;
    window.relevanceDtExportButtons = exportButtons;
    window.relevanceDtCustomizeExportData = customizeDataKeepTitleRow;
})(window, jQuery);
