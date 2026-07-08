function getColor(result, ideal) {
    let percent = ideal / 100

    let difference = 100 - (result / percent)

    if (difference >= 0 && difference < 15 || difference < 0) {
        return 'rgba(78,183,103,0.5)';
    }

    if (difference >= 15 && difference <= 20) {
        return 'rgba(245,226,170,0.5)';
    }

    return 'rgba(220,53,69,0.5)';
}


function renderScannedSitesList(words, sites, avgCoveragePercent, count, hide, boostPercent, avg = null, id = null, search = 'id') {
    $('.sites').show(300)
    let iterator = 1;
    let rows = ''
    $.each(sites, function (key, value) {
        let site = value['site']

        let btnGroup = "<div class='btn-group'>" + " <button type='button' data-bs-toggle='dropdown' aria-expanded='false' class='text-dark btn btn-tool dropdown-toggle'>" +
            " <i class='fas fa-external-link-alt'></i>" +
            " </button> " +
            " <div role='menu' class='dropdown-menu dropdown-menu-left'>" +
            " <a target='_blank' class='dropdown-item' href='" + value['site'] + "'>" +
            " <i class='fas fa-external-link-alt first-action'></i></a>" +
            " <a target='_blank' class='dropdown-item' href='/redirect-to-text-analyzer/" + site.replaceAll('/', 'abc') + "'>" +
            " <i class='fas fa-external-link-alt second-action'></i>" +
            " </a>" +
            " <span class='dropdown-item add-in-ignored-domains' style='cursor: pointer' data-target='" + value['site'] + "'>" +
            " <i class='fas fa-external-link-alt third-action'></i>" +
            " </span>" +
            " <span class='dropdown-item remove-from-ignored-domains' style='cursor: pointer' data-target='" + value['site'] + "'>" +
            " <i class='fas fa-external-link-alt fourth-action'></i>" +
            " </span>" + " </div>" + "</div>";

        let noTop = ''
        let ignorBlock = ''
        let ignorClass = ''
        let className = ''
        let warning

        if (value['ignored']) {
            ignorBlock = "<div class='text-muted'>(" + words.ignoredDomain + ")</div>"
            ignorClass = " ignored-site"
        }

        if (value['danger']) {
            warning = "<td class='scanned-sites-result bg-warning'>" +
                " <span data-scroll='#ignoredDomains' class='scroll-to-ignored-list pointer'>" + words.notGetData + "</span>"
                + ignorBlock +
                "</td>";
        } else {
            warning = "<td class='scanned-sites-result'>" +
                " <span data-scroll='#ignoredDomains' class='scroll-to-ignored-list pointer scanned-sites-result__text'> "
                + words.successAnalyse +
                " </span>"
                + ignorBlock

            if (id !== null) {
                warning += "<a class='btn btn-sm btn-success scanned-sites-result__btn' href='/relevance/show-detail-html/" + encodeURIComponent(value['site'].replace(/\//g, 'splittedSlashe')) + "/" + id + "/" + search + "' target='_blank'>Просмотр html</a>"
            }

            warning += "</td>"
        }

        let color = false
        if (value['mainPage']) {
            if (!value['inRelevance']) {
                noTop = "<span class='text-muted'>(" + words.notTop + ")</span>"
            }
            if (avg !== null) {
                color = true
            }
            className = 'bg-my-site'
        } else if (value['equallyHost']) {
            className = 'bg-warning-elem'
        }

        var position

        if (!value['position']) {
            position = "<td class='scanned-sites-pos' data-order='100'>" + words.notTop + "</td>"

        } else {
            position = "<td class='scanned-sites-pos' data-order='" + value['position'] + "'> " + value['position'] + " </td>"
        }

        rows += "<tr class='render" + ignorClass + "'>"
        rows += position
        rows += "<td data-target='" + iterator + "' class='scanned-sites-domain " + className + "'>"
        rows += "<span class='analyzed-site scanned-sites-domain__url' id='site-" + value['position'] + "' title='" + value['site'].replace(/'/g, '&#39;') + "'>" + value['site'] + "</span>"
        rows += noTop + btnGroup
        rows += "</td>"
        if (color) {
            rows += "<td class='scanned-sites-metric' style='background-color:" + getColor(value.mainPoints, avg.points) + "' data-target='" + value['mainPoints'] + "'>" + value['mainPoints'] + " </td>"
            rows += "<td class='scanned-sites-metric' style='background-color:" + getColor(value.coverage, avg.coverage) + "' data-target='" + value['coverage'] + "'>" + value['coverage'] + "% </td>"
            rows += "<td class='scanned-sites-metric' style='background-color:" + getColor(value.coverageTf, avg.coverageTf) + "' data-target='" + value['coverageTf'] + "'>" + value['coverageTf'] + "% </td>"
            rows += "<td class='scanned-sites-metric' style='background-color:" + getColor(value.width, avg.width) + "' data-target='" + value.width + "'>" + value.width + "</td>"
            rows += "<td class='scanned-sites-metric' style='background-color:" + getColor(value.density.densityMainPercent, avg.densityPercent) + "' data-target='" + value['density']['densityMainPercent'] + "'>" + value['density']['densityMainPercent'] + "</td>"
        } else {
            rows += "<td class='scanned-sites-metric' data-target='" + value['mainPoints'] + "'>" + value['mainPoints'] + " </td>"
            rows += "<td class='scanned-sites-metric' data-target='" + value['coverage'] + "'>" + value['coverage'] + "% </td>"
            rows += "<td class='scanned-sites-metric' data-target='" + value['coverageTf'] + "'>" + value['coverageTf'] + "% </td>"
            rows += "<td class='scanned-sites-metric' data-target='" + value.width + "'>" + value.width + "</td>"
            rows += "<td class='scanned-sites-metric' data-target='" + value['density']['densityMainPercent'] + "'>" + value['density']['densityMainPercent'] + "</td>"
        }

        rows += "<td class='scanned-sites-metric scanned-sites-chars' data-target='" + value['countSymbols'] + "'>" + value['countSymbols'] + "</td>"
        rows += warning
        rows += "</tr>"

        iterator++
    });

    $('#scanned-sites-tbody').html(rows)

    if ($.fn.DataTable.isDataTable('#scanned-sites')) {
        $("#scanned-sites").dataTable().fnDestroy();
    }

    $('#scanned-sites').DataTable({
        deferRender: true,
        order: [[0, 'asc']],
        pageLength: count,
        searching: true,
        orderCellsTop: false,
        aoColumnDefs: [{
            bSortable: false, aTargets: [8]
        }],
        dom: 'lBfrtip',
        buttons: ['copy', 'csv', 'excel'],
        language: {
            paginate: {
                first: '«', last: '»', next: '»', previous: '«'
            },
        },
        oLanguage: {
            sSearch: words.search + ':',
            sLengthMenu: words.show + ' _MENU_ ' + words.records,
            sEmptyTable: words.noRecords,
            sInfo: words.showing + ' ' + words.from + '  _START_ ' + words.to + ' _END_ ' + words.of + ' _TOTAL_ ' + words.entries,
        },
        drawCallback: function () {
            syncScannedSitesHeaderSticky()
        },
        initComplete: function () {
            ensureScannedSitesTableScrollWrap()
            renderScannedSitesRecommendationsRow(avg, words)
            syncScannedSitesHeaderSticky()
            window.requestAnimationFrame(function () {
                syncScannedSitesHeaderSticky()
            })
            setTimeout(syncScannedSitesHeaderSticky, 100)
        }
    });

    ensureScannedSitesTableScrollWrap()

        // $(document).on('click', '.show-detail-html', function () {
        //     let id = $(this).attr('data-id')
        //     let url = $(this).attr('data-target').replace(/\//g, 'splittedSlashe')
        //
        //     window.location.href = '/relevance/show-detail-html/' + url + '/' + id
        // })

    setTimeout(() => {
        $('#scanned-sites_length').before("    <div class='d-flex'>" + "        <div class='__helper-link ui_tooltip_w'>" + "            <div class='custom-control custom-switch custom-switch-off-danger custom-switch-on-success'>" + "                <input type='checkbox'" + "                       class='custom-control-input'" + "                       id='showOrHideIgnoredSites'" + "                       name='noIndex'>" + "                <label class='custom-control-label' for='showOrHideIgnoredSites'></label>" + "            </div>" + "        </div>" + "        <p>" + words.hideDomains + "</p>" + "    </div>")

        $('#showOrHideIgnoredSites').click(function () {
            if ($('.ignored-site').is(':visible')) {
                $('.ignored-site').hide()
            } else {
                $('.ignored-site').show()
            }
        });

        if (hide === 'yes') {
            $('#showOrHideIgnoredSites').trigger('click');
        }

        $('#scanned-sites_wrapper > .dt-buttons').after("<button class='btn btn-secondary ml-1 click_tracking' data-click='Copy links sites'" + " id='copySites' style='cursor: pointer'>" + words.copyLinks + "</button>")

        $('#copySites').click(function () {
            let sites = ''
            $.each($('.analyzed-site'), function () {
                sites += $(this).html() + "\n"
            })
            const el = document.createElement('textarea');
            el.value = sites;
            el.setAttribute('readonly', '');
            el.style.position = 'absolute';
            el.style.left = '-9999px';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        })

        if (avg !== null) {
            renderScannedSitesRecommendationsRow(avg, words)
        } else {
            $('#scanned-sites-recommendations-row').remove()
            syncScannedSitesHeaderSticky()
        }

    }, 2000)

    $('.add-in-ignored-domains').click(function () {
        let url = new URL($(this).attr('data-target'))
        let textarea = $('.form-control.ignoredDomains')
        let string = textarea.val()
        if (!string.includes(url.hostname)) {
            let domain = (url.hostname).replace('www.', '')

            if (textarea.val().slice(-1) === "\n") {
                textarea.val(textarea.val() + domain + "\n")
            } else {
                textarea.val(textarea.val() + "\n" + domain + "\n")
            }

            let toastr = $('.toast-top-right.success-message.lock-word');
            toastr.show(300)
            setTimeout(() => {
                toastr.hide(300)
            }, 3000)
        }
    });

    $('.remove-from-ignored-domains').unbind().click(function () {
        let url = new URL($(this).attr('data-target'))
        let textarea = $('.form-control.ignoredDomains')
        let string = textarea.val()
        let domain = (url.hostname).replace('www.', '')

        if (string.includes(domain)) {
            textarea.val(textarea.val().replace(domain, ""))

            let toastr = $('.toast-top-right.success-message.lock-word-removed');
            toastr.show(300)
            setTimeout(() => {
                toastr.hide(300)
            }, 3000)
        }
    });

    $('.scroll-to-ignored-list').on('click', function () {
        var el = $(this);
        var dest = el.attr('data-scroll');
        $('html').animate({
            scrollTop: $(dest).offset().top
        }, 500);
        return false;
    });

    $('#avgCoveragePercent').html(avgCoveragePercent.toFixed(3))

    $("#avgCoveragePercentInput").change(function () {
        let number = $('#avgCoveragePercent').html()
        if ($("#avgCoveragePercentInput").val() !== '') {
            number = Number(number)
            number = number + ((number / 100) * $('#avgCoveragePercentInput').val())
            let freshNumber = number.toFixed(3)
            $('#changedAvgPercent').html('(' + freshNumber + ')')

            var freshPercent
            $('#scanned-sites-tbody tr').each(function () {
                $(this).find('td').each(function (cell) {
                    if (cell == 3) {
                        let thisValue = Number($(this).html().replace('%', ''))
                        freshPercent = Math.min(thisValue / (freshNumber / 100), 100)
                        freshPercent = freshPercent.toFixed(3)
                    }
                    if (cell == 5) {
                        $(this).attr('data-order', freshPercent)
                        $(this).html(freshPercent)
                    }
                });
            });
        }
    });

    if (boostPercent) {
        $('#avgCoveragePercentInput').val(boostPercent)
        let number = $('#avgCoveragePercent').html()
        number = Number(number)
        number = number + ((number / 100) * boostPercent)
        let freshNumber = number.toFixed(3)
        $('#changedAvgPercent').html('(' + freshNumber + ')')

        var freshPercent
        $('#scanned-sites-tbody tr').each(function () {
            $(this).find('td').each(function (cell) {
                if (cell == 3) {
                    let thisValue = Number($(this).html().replace('%', ''))
                    freshPercent = Math.min(thisValue / (freshNumber / 100), 100)
                    freshPercent = freshPercent.toFixed(3)
                }
                if (cell == 5) {
                    $(this).attr('data-order', freshPercent)
                    $(this).html(freshPercent)
                }
            });
        });
    }
}

function renderScannedSitesRecommendationsRow(avg, words) {
    if (avg === null) {
        $('#scanned-sites-recommendations-row').remove()
        return
    }

    var $thead = $('#scanned-sites thead.scanned-sites-thead')
    if (!$thead.length) {
        return
    }

    var $recRow = $('#scanned-sites-recommendations-row')
    if (!$recRow.length) {
        $recRow = $('<tr class="scanned-sites-thead__recommendations" id="scanned-sites-recommendations-row"></tr>')
        $thead.append($recRow)
    }

    $recRow.html(
        '<th class="scanned-sites-th-pos">-</th>' +
        '<th class="scanned-sites-th-domain">' + words.recommendations + '</th>' +
        '<th class="scanned-sites-th-metric">' + Number(avg.points).toFixed(2) + '</th>' +
        '<th class="scanned-sites-th-metric">' + Number(avg.coverage).toFixed(2) + '</th>' +
        '<th class="scanned-sites-th-metric">' + Number(avg.coverageTf).toFixed(2) + '</th>' +
        '<th class="scanned-sites-th-metric">' + Number(avg.width).toFixed(2) + '</th>' +
        '<th class="scanned-sites-th-metric">' + Number(avg.densityPercent).toFixed(2) + '</th>' +
        '<th class="scanned-sites-th-narrow">' + Number(avg.countSymbols).toFixed(0) + '</th>' +
        '<th class="scanned-sites-th-result">-</th>'
    )
    syncScannedSitesHeaderSticky()
}

function ensureScannedSitesTableScrollWrap() {
    var $table = $('#scanned-sites')
    if (!$table.length || $table.parent().hasClass('relevance-tlp-table-scroll')) {
        return
    }

    $table.wrap('<div class="relevance-tlp-table-scroll"></div>')
}

function syncScannedSitesHeaderSticky() {
    var $titlesRow = $('#scanned-sites thead .scanned-sites-thead__titles')
    var $recommendationsRow = $('#scanned-sites thead .scanned-sites-thead__recommendations')
    var $scroll = $('#scanned-sites_wrapper .relevance-tlp-table-scroll')

    if (!$titlesRow.length || !$scroll.length) {
        return
    }

    var titlesHeight = $titlesRow.outerHeight() || 0
    var recommendationsHeight = $recommendationsRow.length
        ? ($recommendationsRow.outerHeight() || 0)
        : 0

    $scroll.css('--scanned-sites-titles-row-height', titlesHeight + 'px')
    $scroll.css('--scanned-sites-recommendations-row-top', titlesHeight + 'px')
    $scroll.css('--scanned-sites-body-row-top', (titlesHeight + recommendationsHeight) + 'px')
}
