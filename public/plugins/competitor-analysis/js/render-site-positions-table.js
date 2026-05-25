function renderSitePositionsTable(domainsPosition, pageLength) {
    $.each(domainsPosition, function (domain, info) {
        const phrases = info.phrases || info['phrases'] || [];
        const phrasesBlock = buildPhrasesEyeToggle(phrases);

        $('#positions-tbody').append(
            '<tr class="render">' +
            '  <td>' + escapeHtml(domain) + '</td>' +
            '  <td data-order="' + info['topPercent'] + '">' + info['topPercent'] + '% <span class="text-muted"> ' + escapeHtml(info['text']) + '</span> ' + phrasesBlock + '</td>' +
            '  <td>' + info['avg'] + '</td>' +
            '</tr>'
        )
    })
}
