/**
 * Мониторинг — «умный» поиск: учитывает ввод в другой раскладке (йцукен ↔ qwerty).
 */
(function (global) {
    'use strict';

    var PAIRS = [
        ['`', 'ё'],
        ['q', 'й'],
        ['w', 'ц'],
        ['e', 'у'],
        ['r', 'к'],
        ['t', 'е'],
        ['y', 'н'],
        ['u', 'г'],
        ['i', 'ш'],
        ['o', 'щ'],
        ['p', 'з'],
        ['[', 'х'],
        [']', 'ъ'],
        ['a', 'ф'],
        ['s', 'ы'],
        ['d', 'в'],
        ['f', 'а'],
        ['g', 'п'],
        ['h', 'р'],
        ['j', 'о'],
        ['k', 'л'],
        ['l', 'д'],
        [';', 'ж'],
        ["'", 'э'],
        ['z', 'я'],
        ['x', 'ч'],
        ['c', 'с'],
        ['v', 'м'],
        ['b', 'и'],
        ['n', 'т'],
        ['m', 'ь'],
        [',', 'б'],
        ['.', 'ю'],
        ['/', '.'],
        ['~', 'Ё'],
        ['Q', 'Й'],
        ['W', 'Ц'],
        ['E', 'У'],
        ['R', 'К'],
        ['T', 'Е'],
        ['Y', 'Н'],
        ['U', 'Г'],
        ['I', 'Ш'],
        ['O', 'Щ'],
        ['P', 'З'],
        ['{', 'Х'],
        ['}', 'Ъ'],
        ['A', 'Ф'],
        ['S', 'Ы'],
        ['D', 'В'],
        ['F', 'А'],
        ['G', 'П'],
        ['H', 'Р'],
        ['J', 'О'],
        ['K', 'Л'],
        ['L', 'Д'],
        [':', 'Ж'],
        ['"', 'Э'],
        ['Z', 'Я'],
        ['X', 'Ч'],
        ['C', 'С'],
        ['V', 'М'],
        ['B', 'И'],
        ['N', 'Т'],
        ['M', 'Ь'],
        ['<', 'Б'],
        ['>', 'Ю'],
        ['?', ','],
    ];

    var FLIP = {};
    PAIRS.forEach(function (pair) {
        FLIP[pair[0]] = pair[1];
        FLIP[pair[1]] = pair[0];
    });

    function normalize(value) {
        return String(value == null ? '' : value)
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function flipLayout(value) {
        return String(value == null ? '' : value)
            .split('')
            .map(function (ch) {
                return Object.prototype.hasOwnProperty.call(FLIP, ch) ? FLIP[ch] : ch;
            })
            .join('');
    }

    function needles(query) {
        var q = normalize(query);
        if (!q) {
            return [];
        }
        var flipped = normalize(flipLayout(q));
        var out = [q];
        if (flipped && flipped !== q) {
            out.push(flipped);
        }
        return out;
    }

    function matches(query, haystack) {
        var h = normalize(haystack);
        if (!h) {
            return false;
        }
        var list = needles(query);
        if (!list.length) {
            return true;
        }
        for (var i = 0; i < list.length; i += 1) {
            if (h.indexOf(list[i]) >= 0) {
                return true;
            }
        }
        return false;
    }

    function escapeRegex(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function toDataTableRegex(query) {
        var list = needles(query);
        if (!list.length) {
            return '';
        }
        return list.map(escapeRegex).join('|');
    }

    function wireGlobalDataTableSearch(api) {
        if (!api || !api.table) {
            return;
        }
        var $input = $(api.table().container()).find('.dataTables_filter input');
        if (!$input.length || $input.data('cabinetMonSmartSearch')) {
            return;
        }
        $input.data('cabinetMonSmartSearch', true);
        $input.off('keyup.cabinetMonSmartSearch input.cabinetMonSmartSearch');
        $input.on('keyup.cabinetMonSmartSearch input.cabinetMonSmartSearch', function () {
            api.search(toDataTableRegex(this.value), true, false).draw();
        });
    }

    function wireColumnDataTableSearch(columnApi, $input) {
        if (!columnApi || !$input || !$input.length) {
            return;
        }
        $input.off('keyup.cabinetMonSmartSearch change.cabinetMonSmartSearch');
        $input.on('keyup.cabinetMonSmartSearch change.cabinetMonSmartSearch', function () {
            columnApi.search(toDataTableRegex(this.value), true, false).draw();
        });
    }

    function select2Matcher(params, data) {
        if (!data) {
            return null;
        }
        if (!params.term || !String(params.term).trim()) {
            return data;
        }
        if (matches(params.term, data.text)) {
            return data;
        }
        return null;
    }

    function dataTableInitComplete() {
        wireGlobalDataTableSearch(this.api());
    }

    global.cabinetMonitoringSearch = {
        flipLayout: flipLayout,
        needles: needles,
        matches: matches,
        toDataTableRegex: toDataTableRegex,
        wireGlobalDataTableSearch: wireGlobalDataTableSearch,
        wireColumnDataTableSearch: wireColumnDataTableSearch,
        select2Matcher: select2Matcher,
        dataTableInitComplete: dataTableInitComplete,
    };
})(window);
