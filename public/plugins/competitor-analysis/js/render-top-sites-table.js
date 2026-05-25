function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function maxAnalysedSitesPerPhrase(analysedSites) {
    let max = 0;
    $.each(analysedSites || {}, function (_, sites) {
        if (sites && typeof sites === 'object') {
            max = Math.max(max, Object.keys(sites).length);
        }
    });

    return max;
}

/** Колонки 11–20 — только если в данных есть позиции > 10 и запрошен топ-20. */
function syncMetaTableColumns(requestedCount, analysedSites) {
    const want = parseInt(String(requestedCount || '10'), 10) === 20 ? 20 : 10;
    const have = maxAnalysedSitesPerPhrase(analysedSites);
    const showPositions = Math.min(want, have > 0 ? have : want);

    if (showPositions <= 10) {
        $('.extra-th').hide();
    } else {
        $('.extra-th').show();
    }
}

/** Кнопка «глаз» + выпадающий список фраз (Bootstrap dropdown) */
function buildPhrasesEyeToggle(phrases) {
    const list = Array.isArray(phrases) ? phrases : [];
    const countLabel = list.length ? String(list.length) : '0';
    const menuItems = list.map(function (phrase) {
        return '<li><span class="dropdown-item-text">' + escapeHtml(String(phrase)) + '</span></li>';
    }).join('');

    return '<div class="dropdown position-static cabinet-ca-phrases-dropdown d-inline-block">' +
        '<button type="button" class="btn btn-sm btn-outline-secondary cabinet-ca-phrases-toggle" ' +
        'data-bs-toggle="dropdown" aria-expanded="false" ' +
        'title="Фразы, в выдаче которых встречается страница">' +
        '<i class="fas fa-eye" aria-hidden="true"></i>' +
        '<span class="badge text-bg-primary ms-1">' + countLabel + '</span>' +
        '</button>' +
        '<ul class="dropdown-menu dropdown-menu-end shadow-sm cabinet-ca-phrases-dropdown-menu">' +
        (menuItems || '<li><span class="dropdown-item-text text-muted">—</span></li>') +
        '</ul></div>';
}

function initPhrasesEyeDropdowns($root) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
        return;
    }

    ($root || $('#sites-tables, #urls-table')).find('.cabinet-ca-phrases-toggle[data-bs-toggle="dropdown"]').each(function () {
        const existing = bootstrap.Dropdown.getInstance(this);
        if (existing) {
            existing.dispose();
        }
        new bootstrap.Dropdown(this, {
            popperConfig: function (defaultConfig) {
                const cfg = typeof defaultConfig === 'function' ? defaultConfig() : (defaultConfig || {});

                return Object.assign({}, cfg, {strategy: 'fixed'});
            },
        });
    });
}

function normalizeHostForMatch(host) {
    return String(host || '')
        .toLowerCase()
        .replace(/^www\./, '')
        .trim();
}

/** Список доменов из textarea (ozon.ru, market.yandex.ru …) */
function parseDomainLines(text) {
    return String(text || '')
        .split(/\r?\n/)
        .map(function (line) {
            return normalizeHostForMatch(line.replace(/^https?:\/\//i, '').split('/')[0]);
        })
        .filter(function (line) {
            return line.length > 0;
        });
}

function hostMatchesDomainList(host, domainList) {
    const h = normalizeHostForMatch(host);
    if (!h || !domainList.length) {
        return false;
    }

    return domainList.some(function (entry) {
        if (!entry) {
            return false;
        }
        if (h === entry || h.endsWith('.' + entry)) {
            return true;
        }
        return entry.indexOf('.') >= 0 && h.indexOf(entry) >= 0;
    });
}

function formatSerpUrlDisplay(link) {
    try {
        const url = new URL(link);
        const host = url.hostname.replace(/^www\./i, '');
        let path = url.pathname + url.search;
        if (!path || path === '/') {
            path = '/';
        } else if (path.length > 52) {
            path = path.slice(0, 49) + '…';
        }

        return {host: host, path: path, href: link};
    } catch (e) {
        return {host: link, path: '', href: link};
    }
}

function resolveParseStatus(info) {
    if (info && info.parse_status) {
        return info.parse_status
    }
    if (info && info.danger) {
        return 'blocked'
    }

    return 'ok'
}

function metaTableCellClass(status) {
    if (status === 'blocked') {
        return 'cabinet-ca-protected-cell'
    }
    if (status === 'fetch_failed') {
        return 'cabinet-ca-fetch-failed-cell'
    }
    if (status === 'meta_empty') {
        return 'cabinet-ca-meta-empty-cell'
    }

    return ''
}

function formatDomainLabel(url) {
    try {
        return url.hostname.replace(/^www\./i, '')
    } catch (e) {
        return String(url)
    }
}

function buildMetaInfoBlock(url, info, messages, btnGroup) {
    const status = resolveParseStatus(info)
    const host = formatDomainLabel(url)
    let body = ''
    let expanded = false

    if (status === 'fetch_failed') {
        body = '<div class="cabinet-ca-status-notice cabinet-ca-status-notice--fetch">' +
            '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i>' +
            '<span>' + escapeHtml(messages.fetchFailed || messages.protected) + '</span></div>'
        expanded = true
    } else if (status === 'blocked') {
        body = '<div class="cabinet-ca-status-notice cabinet-ca-status-notice--blocked">' +
            '<i class="fas fa-shield-alt" aria-hidden="true"></i>' +
            '<span>' + escapeHtml(messages.protected) + '</span></div>'
        expanded = true
    } else {
        let hasMeta = false

        if (info && info.meta) {
            $.each(info.meta, function (key, values) {
                if (values && values.length > 0) {
                    hasMeta = true
                    body +=
                        '<div class="cabinet-ca-meta-tag-block">' +
                        '   <div class="cabinet-ca-meta-tag-block__label">' + escapeHtml(key) + '</div>' +
                        '   <div class="cabinet-ca-meta-tag-block__text">' +
                        values.map(function (v) { return escapeHtml(v); }).join('<br>') +
                        '   </div>' +
                        '</div>'
                }
            })
        }

        if (status === 'meta_empty' || !hasMeta) {
            body = '<div class="cabinet-ca-status-notice cabinet-ca-status-notice--empty">' +
                '<i class="fas fa-info-circle" aria-hidden="true"></i>' +
                '<span>' + escapeHtml(messages.metaEmpty || messages.protected) + '</span></div>' + body
            expanded = !hasMeta
        }
    }

    return getStub(host, btnGroup, body, expanded)
}

function renderTopSites(analysedSites, messages, requestedCount) {
    $.each(analysedSites, function (phrase, sites) {
        let tr = '<tr class="render">'
        tr += '<td>' + escapeHtml(phrase) + '</td>'
        $.each(sites, function (site, info) {
            let url = new URL(site)
            let btnGroup = getBtnGroup(url, messages)
            const status = resolveParseStatus(info)
            const infoBlock = buildMetaInfoBlock(url, info, messages, btnGroup)
            tr += '<td class="' + metaTableCellClass(status) + '">' + infoBlock + '</td>'
        })
        tr += '</tr>'
        $('#top-sites-body').append(tr)
    })

    syncMetaTableColumns(
        requestedCount || $('.form-select.count').val() || '10',
        analysedSites
    );

    $('.top-sites.mt-5').show()
}

function serpRowsByFullUrl(url) {
    return $('.cabinet-ca-serp-row').filter(function () {
        return $(this).attr('data-full-url') === url;
    });
}

function serpRowsByHost(host) {
    return $('.cabinet-ca-serp-row').filter(function () {
        return $(this).attr('data-order') === host;
    });
}

/**
 * URL/домены, встречающиеся в SERP двух и более фраз текущего региона.
 */
function collectSerpDuplicateHighlights(analysedSites) {
    const urlPhrases = {};
    const hostPhrases = {};

    $.each(analysedSites, function (phrase, sites) {
        $.each(sites, function (link) {
            if (!urlPhrases[link]) {
                urlPhrases[link] = {};
            }
            urlPhrases[link][phrase] = true;

            const host = formatSerpUrlDisplay(link).host;
            if (!hostPhrases[host]) {
                hostPhrases[host] = {};
            }
            hostPhrases[host][phrase] = true;
        });
    });

    const duplicateUrls = Object.keys(urlPhrases).filter(function (url) {
        return Object.keys(urlPhrases[url]).length > 1;
    });
    const duplicateHosts = Object.keys(hostPhrases).filter(function (host) {
        return Object.keys(hostPhrases[host]).length > 1;
    });

    return {duplicateUrls: duplicateUrls, duplicateHosts: duplicateHosts};
}

function disposeSerpPhraseTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }

    $('#sites-tables [data-bs-toggle="tooltip"]').each(function () {
        const inst = bootstrap.Tooltip.getInstance(this);
        if (inst) {
            inst.dispose();
        }
    });
}

function initSerpPhraseTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }

    $('#sites-tables .cabinet-ca-phrase-card__header[data-bs-toggle="tooltip"]').each(function () {
        const existing = bootstrap.Tooltip.getInstance(this);
        if (existing) {
            existing.dispose();
        }
        new bootstrap.Tooltip(this, {
            placement: 'top',
            container: 'body',
            trigger: 'hover',
        });
    });
}

function initSerpToolbarTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }

    $('.site-block-buttons [data-bs-toggle="tooltip"]').each(function () {
        const existing = bootstrap.Tooltip.getInstance(this);
        if (existing) {
            existing.dispose();
        }
        new bootstrap.Tooltip(this, {
            placement: 'top',
            container: 'body',
        });
    });
}

function resetSerpResultsDom() {
    disposeSerpPhraseTooltips();
    clearSerpHighlights();
    $('#sites-tables').off('mouseenter.serpHighlight mouseleave.serpHighlight');
    $('#sites-tables').empty();
}

function renderTopSitesV2(analysedSites, messages) {
    $.each(analysedSites, function (phrase, sites) {
        let newTable = '' +
            '<div class="cabinet-ca-phrase-card render">' +
            '   <div class="cabinet-ca-phrase-card__header" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' + escapeHtml(phrase) + '">' +
            '       <h3 class="cabinet-ca-phrase-card__title">' + escapeHtml(phrase) + '</h3>' +
            '   </div>' +
            '   <div class="card-body p-0 d-flex flex-column">' +
            '       <div class="cabinet-ca-phrase-card__cols">' +
            '           <span>#</span>' +
            '           <span>' + escapeHtml(messages.domain) + '</span>' +
            '           <span></span>' +
            '       </div>'

        let iterator = 1
        $.each(sites, function (link, object) {
            let url = new URL(link)
            let display = formatSerpUrlDisplay(link)
            let btnGroup = getBtnGroup(url, messages)
            newTable +=
                '<div class="cabinet-ca-serp-row await-color" ' +
                'data-order="' + escapeHtml(display.host) + '" ' +
                'data-full-url="' + escapeHtml(link) + '" ' +
                'data-main-page="' + (object['mainPage'] ? 'true' : 'false') + '" ' +
                'title="' + escapeHtml(link) + '">' +
                '    <span class="cabinet-ca-serp-rank">' + iterator + '</span>' +
                '    <div class="cabinet-ca-serp-url">' +
                '        <span class="cabinet-ca-serp-domain">' + escapeHtml(display.host) + '</span>' +
                (display.path ? '<span class="cabinet-ca-serp-path">' + escapeHtml(display.path) + '</span>' : '') +
                '    </div>' +
                '    <div>' + btnGroup + '</div>' +
                '</div>'

            iterator++
        })
        newTable += '</div></div>'

        $('#sites-tables').append(newTable)
    })

    const highlightMeta = collectSerpDuplicateHighlights(analysedSites);
    colorButtonsActions(highlightMeta.duplicateHosts, highlightMeta.duplicateUrls);

    $('#sites-block').show()

    showEquivalentElements()
    initSerpPhraseTooltips()
    initSerpToolbarTooltips()

    let keyCount = Object.keys(analysedSites).length;
    let filename = `export-${keyCount}.xlsx`;
    let $exportButton = $('.site-block-buttons').find('#exportXLS');

    $exportButton.unbind().click(() => exportAnalysedSitesToExcel(analysedSites, filename));
}

function getStub(host, btnGroup, html, showBlock = false) {

    if (showBlock) {
        return '<div class="card direct-chat direct-chat-primary" style="background: transparent !important; box-shadow: none; border: none">' +
            '        <div class="card-header ui-sortable-handle" style="padding: 0 !important; border: 0">' +
            '            <div class="d-flex justify-content-between align-items-start gap-2">' +
            '<div class="fw-semibold small cabinet-ca-meta-domain">' + escapeHtml(host) + btnGroup + '</div>' +
            '                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">' +
            '                    <i class="fas fa-minus"></i>' +
            '                </button>' +
            '            </div>' +
            '        </div>' +
            '        <div class="card-body pt-2">' +
            '            ' + html +
            '        </div>' +
            '    </div>';
    }

    return '<div class="card direct-chat direct-chat-primary collapsed-card" style="background: transparent !important; box-shadow: none; border: none">' +
        '        <div class="card-header ui-sortable-handle" style="padding: 0 !important; border: 0">' +
        '            <div class="d-flex justify-content-between align-items-start gap-2">' +
        '<div class="fw-semibold small cabinet-ca-meta-domain">' + escapeHtml(host) + btnGroup + '</div>' +
        '                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">' +
        '                    <i class="fas fa-plus"></i>' +
        '                </button>' +
        '            </div>' +
        '        </div>' +
        '        <div class="card-body pt-2" style="display: none;">' +
        html +
        '        </div>' +
        '    </div>';
}

function getBtnGroup(url, messages) {
    return '<div class="btn-group">' +
        '   <button type="button" data-bs-toggle="dropdown" aria-expanded="false" class="btn btn-tool dropdown-toggle p-0">' +
        '   <i class="fas fa-external-link-alt"></i>' +
        '   </button>' +
        '       <div role="menu" class="dropdown-menu dropdown-menu-end">' +

        '       <a target="_blank" class="dropdown-item" href="' + escapeHtml(url['href']) + '">' +
        '       <i class="fas fa-external-link-alt me-1"></i> ' + escapeHtml(messages.mainPage) + '</a>' +

        '       <a target="_blank" class="dropdown-item" href="' + escapeHtml(url['origin']) + '">' +
        '       <i class="fas fa-globe me-1"></i> ' + escapeHtml(messages.site) + '</a>' +

        '       <a target="_blank" class="dropdown-item" href="/redirect-to-text-analyzer/' + url['href'].replace(/\\|\//g, 'abc') + '">' +
        '       <i class="fas fa-align-left me-1"></i> ' + escapeHtml(messages.analyzeText) + '</a>' +

        '   </div>' +
        '</div>'
}

function colorButtonsActions(duplicateHosts, duplicateUrls) {
    const strings = window.competitorHighlightStrings || {};

    $('#coloredMainPages').unbind().on('click', function () {
        coloredButtons($(this))
        clearSerpHighlights()

        setSerpHighlight($('.cabinet-ca-serp-row[data-main-page="true"]'), 'main')
    });

    $('#coloredEloquentDomains').unbind().on('click', function () {
        coloredButtons($(this))
        clearSerpHighlights()

        if (!duplicateHosts.length) {
            if (typeof getBrokenScriptMessage === 'function') {
                getBrokenScriptMessage(null, strings.noDuplicateDomains ||
                    'Нет доменов, которые встречаются в выдаче двух и более запросов')
            }

            return
        }

        const palette = getHighlightPalette()
        let colorIndex = 0
        $.each(duplicateHosts, function (key, host) {
            setSerpHighlight(
                serpRowsByHost(host),
                'domain-' + colorIndex,
                palette[colorIndex % palette.length]
            )
            colorIndex++
        })
    })

    $('#coloredEloquentUrls').unbind().on('click', function () {
        coloredButtons($(this))
        clearSerpHighlights()

        if (!duplicateUrls.length) {
            if (typeof getBrokenScriptMessage === 'function') {
                getBrokenScriptMessage(null, strings.noDuplicateUrls ||
                    'Нет одинаковых URL в двух и более колонках запросов')
            }

            return
        }

        const palette = getHighlightPalette()
        let colorIndex = 0
        $.each(duplicateUrls, function (key, url) {
            setSerpHighlight(
                serpRowsByFullUrl(url),
                'url-' + colorIndex,
                palette[colorIndex % palette.length]
            )
            colorIndex++
        })
    })

    $('#coloredEloquentMyText').unbind().on('click', function () {
        coloredButtons($('[data-bs-target="#coloredEloquentMyTextModal"]'))
        clearSerpHighlights()

        let myValues = $('#search-textarea').val()

        let myValuesAr = myValues.split("\n").filter(function (line) {
            return $.trim(line).length > 0
        })

        let elems = []
        $.each($('.cabinet-ca-serp-row'), function () {
            let target = $(this).attr('data-full-url');
            if (target) {
                let elem = $(this);
                $.each(myValuesAr, function (linkKey, link) {
                    if (target.indexOf(link) !== -1) {
                        elems.push(elem)
                    }
                })
            }

        });

        setSerpHighlight([...new Set(elems)], 'custom', '#fef08a')
    })

}

function highlightAggregatorSites() {
    coloredButtons($('[data-bs-target="#coloredAgrigators"]'))

    const agrigatorsAr = parseDomainLines($('#search-agrigators').val())
    if (!agrigatorsAr.length) {
        return
    }

    clearSerpHighlights()

    const elems = []
    $('.cabinet-ca-serp-row').each(function () {
        const host = $(this).attr('data-order')
        const fullUrl = $(this).attr('data-full-url') || ''
        if (hostMatchesDomainList(host, agrigatorsAr)) {
            elems.push($(this))
            return
        }
        let urlHost = ''
        try {
            urlHost = normalizeHostForMatch(new URL(fullUrl).hostname)
        } catch (e) {
            urlHost = normalizeHostForMatch(fullUrl.replace(/^https?:\/\//i, '').split('/')[0])
        }
        if (hostMatchesDomainList(urlHost, agrigatorsAr)) {
            elems.push($(this))
        }
    })

    const highlighted = setSerpHighlight(elems, 'aggregator', '#fce7f3')
    if (!highlighted && typeof getBrokenScriptMessage === 'function') {
        getBrokenScriptMessage(null, window.competitorHighlightStrings?.noAggregators ||
            'Нет строк с доменами из списка агрегаторов в текущей выдаче')
    }
}

$(document).off('click.cabinetCaAggregators', '#coloredAgrigatorsButton')
    .on('click.cabinetCaAggregators', '#coloredAgrigatorsButton', function (e) {
        e.preventDefault()
        highlightAggregatorSites()
    })

function clearSerpHighlightHoverState() {
    $('.cabinet-ca-serp-row').removeClass('cabinet-ca-serp-row--dimmed cabinet-ca-serp-row--focus');
}

function clearSerpHighlights() {
    clearSerpHighlightHoverState();
    $('.cabinet-ca-serp-row').each(function () {
        $(this)
            .removeClass('is-highlighted')
            .removeAttr('data-highlight-group')
            .css({
                'background-color': '',
                'color': '',
                'text-shadow': '',
                'box-shadow': '',
                '--cabinet-ca-row-highlight-bg': '',
                '--cabinet-ca-row-highlight-border': '',
            });
    });
}

function mergeHighlightTargets(elem) {
    if (!elem) {
        return $();
    }
    if (elem instanceof jQuery) {
        return elem;
    }
    if (Array.isArray(elem)) {
        let merged = $();
        elem.forEach(function (item) {
            if (item instanceof jQuery && item.length) {
                merged = merged.add(item);
            }
        });

        return merged;
    }

    return $(elem);
}

function setSerpHighlight(elem, groupKey, color) {
    const $elem = mergeHighlightTargets(elem);
    if (!$elem.length) {
        return 0;
    }

    const swatch = resolveHighlightSwatch(color);
    $elem.addClass('is-highlighted');
    if (groupKey) {
        $elem.attr('data-highlight-group', groupKey);
    }
    $elem.css({
        '--cabinet-ca-row-highlight-bg': swatch.bg,
        '--cabinet-ca-row-highlight-border': swatch.border,
        'background-color': swatch.bg,
        'color': 'inherit',
        'text-shadow': 'none',
        'box-shadow': 'inset 3px 0 0 ' + swatch.border,
    });

    return $elem.length;
}

function resolveHighlightSwatch(color) {
    if (color && typeof color === 'object' && color.bg) {
        return {
            bg: color.bg,
            border: color.border || color.bg,
        };
    }

    const fallback = color || '#dbeafe';

    return {bg: fallback, border: '#2563eb'};
}

function setColorElems(elems) {
    setSerpHighlight(elems, 'custom', '#e0e7ff');
}

function setRandomColor(elem, backgroundColor, defaultColor) {
    if (defaultColor) {
        clearSerpHighlights();
        return;
    }

    setSerpHighlight(elem, 'legacy', backgroundColor || '#dbeafe');
}

function coloredButtons(elem) {
    $('.colored-button').each(function () {
        $(this).removeClass('btn-primary').addClass('btn-outline-secondary');
    });
    elem.removeClass('btn-outline-secondary').addClass('btn-primary');
}

function showEquivalentElements() {
    const $grid = $('#sites-tables');

    $grid.off('mouseenter.serpHighlight', '.cabinet-ca-serp-row');
    $grid.on('mouseenter.serpHighlight', '.cabinet-ca-serp-row', function () {
        const group = $(this).attr('data-highlight-group');
        if (!group || !$(this).hasClass('is-highlighted')) {
            clearSerpHighlightHoverState();

            return;
        }

        $('.cabinet-ca-serp-row.is-highlighted').each(function () {
            const $row = $(this);
            if ($row.attr('data-highlight-group') === group) {
                $row.addClass('cabinet-ca-serp-row--focus').removeClass('cabinet-ca-serp-row--dimmed');
            } else {
                $row.addClass('cabinet-ca-serp-row--dimmed').removeClass('cabinet-ca-serp-row--focus');
            }
        });
    });

    $grid.off('mouseleave.serpHighlight');
    $grid.on('mouseleave.serpHighlight', function () {
        clearSerpHighlightHoverState();
    });
}

function validateColor(background, target) {
    return target.hasClass('is-highlighted');
}

/**
 * Различимые пары фон + полоска слева (один URL/домен — один цвет).
 * @returns {Array<{bg: string, border: string}>}
 */
function getHighlightPalette() {
    return [
        {bg: '#bfdbfe', border: '#2563eb'},
        {bg: '#bbf7d0', border: '#16a34a'},
        {bg: '#fde68a', border: '#d97706'},
        {bg: '#fecdd3', border: '#e11d48'},
        {bg: '#ddd6fe', border: '#7c3aed'},
        {bg: '#a5f3fc', border: '#0891b2'},
        {bg: '#fed7aa', border: '#ea580c'},
        {bg: '#d9f99d', border: '#65a30d'},
        {bg: '#f5d0fe', border: '#c026d3'},
        {bg: '#99f6e4', border: '#0d9488'},
        {bg: '#fbcfe8', border: '#db2777'},
        {bg: '#e9d5ff', border: '#9333ea'},
    ];
}

function getColorsArray() {
    return getHighlightPalette().sort(() => Math.random() - 0.5);
}

function exportAnalysedSitesToExcel(obj, filename = 'report.xlsx') {
    let data = [];

    $.each(obj, function (cols, value) {
        let sites = Object.keys(value);
        $.each(sites, function (index, site) {
            let row = {[cols]: site}
            if (data[index] === undefined) {
                data.push(row)
            } else {
                $.extend( data[index], row );
            }
        })
    })

    exportToExcel(data, filename);
}
