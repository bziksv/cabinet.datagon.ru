/**
 * Рекомендации: авто-группы запросов по пересечению URL + слова в meta-тегах.
 */
function initCompetitorRecommendations(payload, count) {
    window.competitorRecommendationsPayload = payload;
    window.competitorRecommendationsCount = count;

    $('#recommendations-block').show();

    $('#cabinet-ca-build-recommendations').off('click').on('click', function () {
        buildCompetitorRecommendations({scrollToResults: true});
    });

    $('.cabinet-ca-rec-tag-check').off('change').on('change', function () {
        if (window.competitorRecommendationsPayload) {
            buildCompetitorRecommendations({scrollToResults: false});
        }
    });

    buildCompetitorRecommendations({scrollToResults: false});
}

function getSelectedRecommendationTags() {
    const tags = [];
    $('.cabinet-ca-rec-tag-check:checked').each(function () {
        tags.push($(this).val());
    });

    return tags;
}

function buildCompetitorRecommendations(options) {
    const opts = options || {};
    const payload = window.competitorRecommendationsPayload;
    if (!payload || !payload.totalMetaTags) {
        return;
    }

    const $root = $('#cabinet-ca-recommendations-root');
    $root.html('<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>' +
        (window.competitorRecStrings?.loading || 'Формируем рекомендации…') + '</div>');

    $.ajax({
        type: 'POST',
        dataType: 'json',
        url: window.competitorRecommendationsUrl || '/get-recommendations',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            analysedSites: JSON.stringify(payload.analysedSites || {}),
            totalMetaTags: JSON.stringify(payload.totalMetaTags || {}),
            selectedTags: JSON.stringify(getSelectedRecommendationTags()),
            count: window.competitorRecommendationsCount || $('.form-select.count').val() || '30',
        },
        success: function (response) {
            renderRecommendationClusters(response.result || {}, opts);
        },
        error: function (xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : (window.competitorRecStrings?.error || 'Не удалось построить рекомендации');
            $root.html('<div class="alert alert-warning mb-0">' + escapeHtmlRec(msg) + '</div>');
        },
    });
}

function renderRecommendationClusters(result, options) {
    const opts = options || {};
    const $root = $('#cabinet-ca-recommendations-root');
    const clusters = (result && result.clusters) ? result.clusters : [];

    if (!clusters.length) {
        $root.html('<div class="alert alert-info mb-0">' +
            escapeHtmlRec(window.competitorRecStrings?.empty || 'Нет данных для рекомендаций') +
            '</div>');

        return;
    }

    const s = window.competitorRecStrings || {};
    let html = '';

    $.each(clusters, function (_, cluster) {
        const phrases = (cluster.phrases || []).map(escapeHtmlRec).join(', ');
        const simPct = Math.round((cluster.similarity || 0) * 100);
        const sharedCount = cluster.shared_url_count || 0;

        html += '<div class="card cabinet-ca-rec-cluster border-0 shadow-sm mb-3">';
        html += '<div class="card-header cabinet-ca-rec-cluster__head">';
        html += '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">';
        html += '<div><span class="badge text-bg-primary me-2">' + escapeHtmlRec(s.group || 'Группа') + ' ' + cluster.id + '</span>';
        html += '<strong class="cabinet-ca-rec-cluster__phrases">' + phrases + '</strong></div>';
        html += '<div class="small text-muted">';
        if (cluster.phrase_count > 1) {
            html += escapeHtmlRec(s.similarity || 'Схожесть выдачи') + ': ' + simPct + '% · ';
        }
        html += escapeHtmlRec(s.shared || 'Общих URL') + ': ' + sharedCount;
        html += '</div></div></div>';

        html += '<div class="card-body">';

        if (cluster.shared_urls && cluster.shared_urls.length) {
            html += '<div class="cabinet-ca-rec-shared mb-3"><div class="small fw-semibold text-muted mb-1">' +
                escapeHtmlRec(s.sharedUrls || 'Примеры общих посадочных') + '</div><ul class="cabinet-ca-rec-shared-list mb-0">';
            $.each(cluster.shared_urls, function (__i, url) {
                html += '<li><code class="small">' + escapeHtmlRec(url) + '</code></li>';
            });
            html += '</ul></div>';
        }

        const rec = cluster.recommendations || {};
        const tagKeys = Object.keys(rec);
        if (!tagKeys.length) {
            html += '<p class="text-muted small mb-0">' + escapeHtmlRec(s.noWords || 'Нет слов выше порога для выбранных тегов') + '</p>';
        } else {
            html += '<div class="cabinet-ca-rec-tags">';
            $.each(tagKeys, function (__j, tag) {
                const words = rec[tag] || [];
                html += '<div class="cabinet-ca-rec-tag-block"><div class="cabinet-ca-rec-tag-block__label">' + escapeHtmlRec(tag) + '</div>';
                if (!words.length) {
                    html += '<span class="text-muted small">—</span>';
                } else {
                    html += '<div class="cabinet-ca-rec-chips">';
                    $.each(words, function (__k, item) {
                        const inPhrase = item.in_phrases ? ' cabinet-ca-rec-chip--query' : '';
                        let tipText = item.label || '';
                        if (item.in_phrases) {
                            tipText = (s.chipInPhrase || 'Есть в тексте запроса группы') +
                                (tipText ? ' · ' + tipText : '');
                        }
                        if (!tipText) {
                            tipText = s.chipScore || 'Частота встреча у конкурентов в топе';
                        }
                        html += '<span class="cabinet-ca-rec-chip' + inPhrase + '" tabindex="0" role="button" ' +
                            'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' + escapeHtmlRec(tipText) + '">';
                        html += '<span class="cabinet-ca-rec-chip__word">' + escapeHtmlRec(item.word) + '</span>';
                        html += '<span class="cabinet-ca-rec-chip__score">' + item.score + '</span>';
                        html += '</span>';
                    });
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        html += '</div></div>';
    });

    $root.html(html);
    initRecommendationChipTooltips($root);

    if (opts.scrollToResults && $root.offset()) {
        $('html, body').animate({scrollTop: $root.offset().top - 80}, 400);
    }
}

function initRecommendationChipTooltips($root) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }

    $root.find('.cabinet-ca-rec-chip[data-bs-toggle="tooltip"]').each(function () {
        const existing = bootstrap.Tooltip.getInstance(this);
        if (existing) {
            existing.dispose();
        }
        new bootstrap.Tooltip(this, {
            trigger: 'hover focus',
            container: 'body',
            delay: { show: 150, hide: 50 },
        });
    });
}

function escapeHtmlRec(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function resetCompetitorRecommendations() {
    window.competitorRecommendationsPayload = null;
    $('#recommendations-block').hide();
    $('#cabinet-ca-recommendations-root').html(
        '<div class="text-muted small" id="cabinet-ca-recommendations-placeholder">' +
        (window.competitorRecStrings?.placeholder || '') +
        '</div>'
    );
}
