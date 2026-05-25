function isTagNoise(word) {
    if (!word || word === 'undefined') {
        return true;
    }
    const w = String(word).toLowerCase().trim();
    const noise = [
        'sorry', 'your', 'request', 'has', 'been', 'denied',
        'обратите', 'внимание', 'на', 'аналоги', 'выбор',
    ];
    return noise.indexOf(w) >= 0;
}

function renderTagChips(values) {
    if (!values || typeof values !== 'object') {
        return '';
    }

    const items = Object.keys(values)
        .filter(function (word) {
            return !isTagNoise(word);
        })
        .map(function (word) {
            return [word, values[word]];
        })
        .sort(function (a, b) {
            return b[1] - a[1];
        })
        .slice(0, 28);

    if (!items.length) {
        return '<span class="text-muted small">—</span>';
    }

    return '<div class="cabinet-ca-tag-chips">' + items.map(function (pair) {
        const word = pair[0];
        const count = pair[1];
        return '<span class="cabinet-ca-tag-chip" data-count="' + count + '" title="' +
            escapeHtml(word) + ': ' + count + '">' +
            '<span class="cabinet-ca-tag-chip__word">' + escapeHtml(word) + '</span>' +
            '<span class="cabinet-ca-tag-chip__count">' + count + '</span>' +
            '</span>';
    }).join('') + '</div>';
}

function renderTagsTable(metaTags) {
    $('.tag-analysis').show()

    $.each(metaTags, function (phrase, tags) {
        let row = '<tr class="render">'
        row += '<td>' + escapeHtml(phrase) + '</td>'

        $.each(tags, function (meta, values) {
            row += '<td><div class="cabinet-ca-tag-cell">' + renderTagChips(values) + '</div></td>'
        })

        row += '</tr>'

        $('#tag-analysis-tbody').append(row)
    })
}
