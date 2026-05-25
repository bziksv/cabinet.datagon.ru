/**
 * Блок «геозависимость» при анализе в 2+ регионах.
 */
function hideGeoDependencyVerdict() {
    $('#cabinet-ca-geo-verdict').hide().empty();
}

function renderGeoDependencyVerdict(geo) {
    const $box = $('#cabinet-ca-geo-verdict');
    if (!geo || !geo.verdict) {
        hideGeoDependencyVerdict();

        return;
    }

    const phraseRows = Array.isArray(geo.phrases) ? geo.phrases : [];

    const s = window.competitorGeoStrings || {};
    const verdict = geo.verdict;
    const alertClass = verdict === 'geo_independent'
        ? 'success'
        : (verdict === 'geo_dependent' ? 'warning' : 'info');

    const title = s['title_' + verdict] || s.title_mixed || 'Геозависимость запросов';
    const body = s['body_' + verdict] || '';

    const regionLabels = (geo.regions || []).map(function (r) {
        return escapeHtmlGeo(r.label || r.key);
    }).join(', ');

    let html = '<div class="card cabinet-ca-geo-verdict border-0 shadow-sm mb-3">' +
        '<div class="card-body">' +
        '<div class="alert alert-' + alertClass + ' mb-3 cabinet-ca-geo-verdict__alert">' +
        '<div class="d-flex flex-wrap align-items-start gap-2">' +
        '<div class="flex-grow-1">' +
        '<h3 class="h6 alert-heading mb-2">' + escapeHtmlGeo(title) + '</h3>' +
        '<p class="mb-2 small">' + escapeHtmlGeo(body) + '</p>' +
        '<p class="mb-0 small text-muted">' +
        escapeHtmlGeo(s.comparedRegions || 'Сравнение регионов') + ': <strong>' + regionLabels + '</strong>' +
        ' · ' + escapeHtmlGeo(s.avgOverlap || 'Среднее совпадение топ-URL') + ': ' +
        '<strong>' + (geo.avg_jaccard_pct != null ? geo.avg_jaccard_pct : '—') + '%</strong>' +
        '</p></div></div></div>';

    if (phraseRows.length) {
        html += '<div class="small fw-semibold text-muted mb-2">' + escapeHtmlGeo(s.byPhrase || 'По запросам') + '</div>';
        html += '<div class="table-responsive"><table class="table table-sm table-hover mb-0 cabinet-ca-geo-table">' +
            '<thead><tr>' +
            '<th>' + escapeHtmlGeo(s.columnPhrase || 'Запрос') + '</th>' +
            '<th class="text-center">' + escapeHtmlGeo(s.columnOverlap || 'Совпадение') + '</th>' +
            '<th>' + escapeHtmlGeo(s.columnVerdict || 'Вывод') + '</th>' +
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
                '<td>' + escapeHtmlGeo(row.phrase) + '</td>' +
                '<td class="text-center"><span class="badge text-bg-light text-dark">' +
                (row.jaccard_pct != null ? row.jaccard_pct + '%' : '—') + '</span></td>' +
                '<td><span class="badge ' + badgeClass + '">' + escapeHtmlGeo(badgeText) + '</span></td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
    }
    html += '<p class="small text-muted mb-0 mt-2">' + escapeHtmlGeo(s.footnote || '') + '</p>';
    html += '</div></div>';

    $box.html(html).show();
}

function escapeHtmlGeo(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
