/**
 * Блок «геозависимость» при анализе в 2+ регионах.
 */
function hideGeoDependencyVerdict() {
    $('#cabinet-ca-geo-verdict').hide().empty();
}

function cityFromTabLabel(tabLabel) {
    const raw = String(tabLabel || '').trim();
    const parts = raw.split(/\s*·\s*/);
    if (parts.length >= 2) {
        return parts.slice(1).join(' · ').trim();
    }

    return raw;
}

function buildRegionPairsFromGeoRegions(regions) {
    const list = Array.isArray(regions) ? regions : [];
    const pairs = [];
    for (let i = 0; i < list.length; i++) {
        for (let j = i + 1; j < list.length; j++) {
            const a = list[i] || {};
            const b = list[j] || {};
            const labelA = a.label || a.key || '';
            const labelB = b.label || b.key || '';
            const cityA = cityFromTabLabel(labelA);
            const cityB = cityFromTabLabel(labelB);
            pairs.push({
                region_a: a.key,
                region_b: b.key,
                label_a: labelA,
                label_b: labelB,
                label: cityA + ' ↔ ' + cityB,
            });
        }
    }

    return pairs;
}

function formatGeoUrlDisplay(normalized) {
    const raw = String(normalized || '').trim();
    if (!raw) {
        return '';
    }
    const slash = raw.indexOf('/');
    const host = slash >= 0 ? raw.slice(0, slash) : raw;
    let path = slash >= 0 ? raw.slice(slash) : '/';
    if (!path || path === '/') {
        path = '/';
    } else if (path.length > 48) {
        path = path.slice(0, 45) + '…';
    }

    return host + path;
}

function geoUrlHref(normalized) {
    const display = formatGeoUrlDisplay(normalized);
    if (!display) {
        return '#';
    }

    return 'https://' + display.replace(/^\//, '');
}

function geoOverlapPct(entity) {
    if (!entity) {
        return null;
    }
    if (entity.overlap_pct != null) {
        return entity.overlap_pct;
    }
    if (entity.jaccard_pct != null) {
        return entity.jaccard_pct;
    }

    return null;
}

function renderGeoPairCell(pairDetail, strings) {
    const s = strings || {};
    const pct = geoOverlapPct(pairDetail);
    if (!pairDetail || pct == null) {
        return '<span class="text-muted small">—</span>';
    }

    const countA = pairDetail.count_a != null ? pairDetail.count_a : '—';
    const countB = pairDetail.count_b != null ? pairDetail.count_b : '—';
    const sharedCount = pairDetail.shared_count || 0;
    const pctA = pairDetail.overlap_pct_a != null ? pairDetail.overlap_pct_a : null;
    const pctB = pairDetail.overlap_pct_b != null ? pairDetail.overlap_pct_b : null;
    const urls = Array.isArray(pairDetail.shared_urls) ? pairDetail.shared_urls : [];
    const more = sharedCount > urls.length ? sharedCount - urls.length : 0;

    let html = '<div class="cabinet-ca-geo-pair-cell">' +
        '<div class="cabinet-ca-geo-pair-cell__pct">' +
        '<span class="badge text-bg-light text-dark">' + pct + '%</span>' +
        '<span class="small text-muted ms-1">' +
        escapeHtmlGeo(s.topCountHint || 'топ') + ': ' + countA + ' / ' + countB +
        '</span></div>';

    if (pctA != null && pctB != null) {
        html += '<div class="small text-muted">' +
            escapeHtmlGeo(s.overlapPerRegion || 'Доля общих в топе') + ': ' +
            pctA + '% / ' + pctB + '%</div>';
    }

    if (sharedCount === 0) {
        html += '<div class="small text-muted mt-1">' + escapeHtmlGeo(s.noSharedUrls || 'Нет общих URL') + '</div>';
    } else {
        html += '<div class="small text-muted mt-1 mb-1">' +
            escapeHtmlGeo(s.sharedPages || 'Общие страницы') + ' (' + sharedCount + '):</div>' +
            '<ul class="cabinet-ca-geo-shared-list mb-0">';
        $.each(urls, function (__i, url) {
            const display = formatGeoUrlDisplay(url);
            const href = geoUrlHref(url);
            html += '<li><a href="' + escapeHtmlGeo(href) + '" target="_blank" rel="noopener noreferrer" ' +
                'class="cabinet-ca-geo-shared-url"><code class="small">' + escapeHtmlGeo(display) + '</code></a></li>';
        });
        html += '</ul>';
        if (more > 0) {
            html += '<div class="small text-muted mt-1">+' + more + ' ' +
                escapeHtmlGeo(s.moreSharedUrls || 'ещё') + '</div>';
        }
    }

    html += '</div>';

    return html;
}

function renderGeoExcludedDomainsBlock(geo, strings) {
    const s = strings || {};
    const preset = window.competitorGeoExcludedPreset || {};
    const all = Array.isArray(geo.excluded_domains) && geo.excluded_domains.length
        ? geo.excluded_domains
        : (preset.all || []);
    if (!all.length) {
        return '';
    }

    const fromSettings = Array.isArray(geo.excluded_domains_from_settings) && geo.excluded_domains_from_settings.length
        ? geo.excluded_domains_from_settings
        : (preset.from_settings || []);
    const fromDefaults = Array.isArray(geo.excluded_domains_defaults) && geo.excluded_domains_defaults.length
        ? geo.excluded_domains_defaults
        : (preset.from_defaults || []);

    let sources = '';
    if (fromSettings.length && fromDefaults.length) {
        sources = escapeHtmlGeo(s.excludedSourcesBoth ||
            'из настроек модуля «Агрегаторы» и базового списка маркетплейсов');
    } else if (fromSettings.length) {
        sources = escapeHtmlGeo(s.excludedSourcesSettings ||
            'из настроек модуля «Список агрегаторов»');
    } else {
        sources = escapeHtmlGeo(s.excludedSourcesDefaults ||
            'базовый список маркетплейсов и агрегаторов');
    }

    let html = '<details class="cabinet-ca-geo-excluded mt-2">' +
        '<summary class="small text-muted cabinet-ca-geo-excluded__summary">' +
        escapeHtmlGeo(s.excludedTitle || 'Исключённые из расчёта домены') +
        ' <span class="badge text-bg-secondary">' + all.length + '</span>' +
        '</summary>' +
        '<p class="small text-muted mb-2 mt-2">' + sources + '</p>' +
        '<div class="cabinet-ca-geo-excluded__chips">';

    $.each(all, function (__i, host) {
        html += '<span class="badge text-bg-light text-dark border cabinet-ca-geo-excluded__chip">' +
            escapeHtmlGeo(host) + '</span>';
    });

    html += '</div></details>';

    return html;
}

function findPhrasePairDetail(phraseRow, regionA, regionB) {
    const pairs = Array.isArray(phraseRow.pairs) ? phraseRow.pairs : [];
    let found = null;
    $.each(pairs, function (_, p) {
        if (!p) {
            return;
        }
        if ((p.region_a === regionA && p.region_b === regionB) ||
            (p.region_a === regionB && p.region_b === regionA)) {
            found = p;
            return false;
        }
    });

    return found;
}

function renderGeoDependencyVerdict(geo) {
    const $box = $('#cabinet-ca-geo-verdict');
    if (!geo) {
        hideGeoDependencyVerdict();

        return;
    }

    if (geo.mode === 'by_engine' && Array.isArray(geo.engines) && geo.engines.length) {
        const s = window.competitorGeoStrings || {};
        let html = '';
        $.each(geo.engines, function (idx, engineGeo) {
            if (!engineGeo || !engineGeo.verdict) {
                return;
            }
            html += renderGeoDependencyEngineBlock(engineGeo, s, idx > 0);
        });
        if (!html) {
            hideGeoDependencyVerdict();

            return;
        }
        const footnote = escapeHtmlGeo(s.footnote || '');
        const excluded = renderGeoExcludedDomainsBlock(geo.engines[0], s);
        $box.html(
            '<div class="cabinet-ca-geo-verdict-stack">' + html +
            (footnote ? '<p class="small text-muted mb-0 mt-2">' + footnote + '</p>' : '') +
            excluded +
            '</div>'
        ).show();

        return;
    }

    if (!geo.verdict) {
        hideGeoDependencyVerdict();

        return;
    }

    const s = window.competitorGeoStrings || {};
    $box.html(
        '<div class="cabinet-ca-geo-verdict-stack">' +
        renderGeoDependencyEngineBlock(geo, s, false) +
        '</div>'
    ).show();
}

/**
 * Один блок геозависимости (одна ПС: только пары городов внутри Яндекса или Google).
 */
function renderGeoDependencyEngineBlock(geo, strings, withTopMargin) {
    const phraseRows = Array.isArray(geo.phrases) ? geo.phrases : [];
    const regionPairs = Array.isArray(geo.region_pairs) && geo.region_pairs.length
        ? geo.region_pairs
        : buildRegionPairsFromGeoRegions(geo.regions);
    const s = strings || window.competitorGeoStrings || {};
    const verdict = geo.verdict;
    const alertClass = verdict === 'geo_independent'
        ? 'success'
        : (verdict === 'geo_dependent' ? 'warning' : 'info');
    const title = s['title_' + verdict] || s.title_mixed || 'Геозависимость запросов';
    const body = s['body_' + verdict] || '';
    const engineLabel = geo.engine_label || geo.engine || '';
    const regionLabels = (geo.regions || []).map(function (r) {
        return escapeHtmlGeo(cityFromTabLabel(r.label || r.key));
    }).join(', ');

    let html = '<div class="card cabinet-ca-geo-verdict border-0 shadow-sm cabinet-ca-geo-verdict--engine' +
        (withTopMargin ? ' mt-4' : ' mb-0') + '">' +
        '<div class="card-body">';

    if (engineLabel) {
        html += '<div class="cabinet-ca-geo-engine-title h6 text-uppercase text-muted mb-3">' +
            '<i class="fas fa-search me-1" aria-hidden="true"></i>' + escapeHtmlGeo(engineLabel) +
            '</div>';
    }

    html += '<div class="alert alert-' + alertClass + ' mb-3 cabinet-ca-geo-verdict__alert">' +
        '<div class="d-flex flex-wrap align-items-start gap-2">' +
        '<div class="flex-grow-1">' +
        '<h3 class="h6 alert-heading mb-2">' + escapeHtmlGeo(title) + '</h3>' +
        '<p class="mb-2 small">' + escapeHtmlGeo(body) + '</p>' +
        '<p class="mb-0 small text-muted">' +
        escapeHtmlGeo(s.comparedRegions || 'Сравнение регионов') + ': <strong>' + regionLabels + '</strong>' +
        ' · ' + escapeHtmlGeo(s.avgOverlap || 'Среднее совпадение топ-URL') + ': ' +
        '<strong>' + (geoAvgOverlapPct(geo) != null ? geoAvgOverlapPct(geo) : '—') + '%</strong>' +
        '</p></div></div></div>';

    if (phraseRows.length) {
        html += '<div class="small fw-semibold text-muted mb-2">' + escapeHtmlGeo(s.byPhrase || 'По запросам') + '</div>';
        html += '<div class="table-responsive"><table class="table table-sm table-hover mb-0 cabinet-ca-geo-table">' +
            '<thead><tr>' +
            '<th class="cabinet-ca-geo-table__phrase">' + escapeHtmlGeo(s.columnPhrase || 'Запрос') + '</th>' +
            '<th class="text-center cabinet-ca-geo-table__avg">' + escapeHtmlGeo(s.columnOverlap || 'Совпадение') + '</th>';

        $.each(regionPairs, function (_, pair) {
            html += '<th class="cabinet-ca-geo-table__pair">' +
                '<span class="cabinet-ca-geo-pair-head">' + escapeHtmlGeo(pair.label || '') + '</span>' +
                '</th>';
        });

        html += '<th class="cabinet-ca-geo-table__verdict">' + escapeHtmlGeo(s.columnVerdict || 'Вывод') + '</th>' +
            '</tr></thead><tbody>';

        $.each(phraseRows, function (_, row) {
            const status = row.status || 'partial';
            const badgeClass = status === 'geo_independent'
                ? 'text-bg-success'
                : (status === 'geo_dependent'
                    ? 'text-bg-warning'
                    : (status === 'skipped' ? 'text-bg-light text-dark' : 'text-bg-secondary'));
            const badgeText = s['badge_' + status] || status;

            html += '<tr>' +
                '<td class="cabinet-ca-geo-table__phrase">' + escapeHtmlGeo(row.phrase) + '</td>' +
                '<td class="text-center cabinet-ca-geo-table__avg">' +
                '<span class="badge text-bg-light text-dark">' +
                (geoOverlapPct(row) != null ? geoOverlapPct(row) + '%' : '—') + '</span></td>';

            $.each(regionPairs, function (__i, pairMeta) {
                const detail = findPhrasePairDetail(row, pairMeta.region_a, pairMeta.region_b);
                html += '<td class="cabinet-ca-geo-table__pair">' + renderGeoPairCell(detail, s) + '</td>';
            });

            html += '<td class="cabinet-ca-geo-table__verdict">' +
                '<span class="badge ' + badgeClass + '">' + escapeHtmlGeo(badgeText) + '</span></td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
    }

    html += '</div></div>';

    return html;
}

function geoAvgOverlapPct(geo) {
    if (!geo) {
        return null;
    }
    if (geo.avg_overlap_pct != null) {
        return geo.avg_overlap_pct;
    }
    if (geo.avg_jaccard_pct != null) {
        return geo.avg_jaccard_pct;
    }

    return null;
}

function escapeHtmlGeo(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
