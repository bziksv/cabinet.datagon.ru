/**
 * Облака на show-history — спиральный рендер как в /text-analyzer.
 * jQCloud не используем: блокирует main thread и вешает всю страницу.
 */
var relevanceCloudBusy = false
var relevanceCloudMaxWords = 100
var relevanceCloudLeaderCount = 20
var relevanceCloudSizeMinPx = 13
var relevanceCloudSizeMaxPx = 38
var relevanceCloudVisualFloor = 0.2

function relevanceCloudWeightLabel(kind) {
    if (window.relevanceCloudLabels && window.relevanceCloudLabels[kind]) {
        return window.relevanceCloudLabels[kind]
    }
    return kind === 'repetitions' ? 'повторений' : 'tf-idf'
}

function formatCloudWeight(weight) {
    var value = parseFloat(weight)
    if (!isFinite(value)) {
        return String(weight)
    }
    if (Math.abs(value - Math.round(value)) < 0.0001) {
        return String(Math.round(value))
    }
    return value.toFixed(4).replace(/\.?0+$/, '')
}

function formatWordTip(text, word, kind) {
    var weight = typeof word === 'object' ? word.weight : word

    if (kind === 'tfidf') {
        return String(text) + ' — ' + formatCloudWeight(weight)
    }

    return String(text) + ' — ' + relevanceCloudWeightLabel(kind) + ': ' + formatCloudWeight(weight)
}

function initRelevanceCloudTooltips() {
    if (window._relevanceCloudTipsBound) {
        return
    }
    window._relevanceCloudTipsBound = true

    var $tip = $('#relevance-cloud-tooltip')
    if (!$tip.length) {
        $tip = $('<div id="relevance-cloud-tooltip" class="relevance-cloud-tooltip" role="tooltip"></div>').appendTo('body')
    }

    var selector = '.relevance-spiral-cloud__word[data-tip], .relevance-tag-cloud__word[data-tip]'

    $(document)
        .on('mouseenter', selector, function (e) {
            var title = $(this).attr('data-tip')
            if (!title) {
                return
            }
            $tip.text(title).addClass('is-visible').css({left: e.pageX + 14, top: e.pageY + 14})
        })
        .on('mousemove', selector, function (e) {
            if (!$tip.hasClass('is-visible')) {
                return
            }
            $tip.css({left: e.pageX + 14, top: e.pageY + 14})
        })
        .on('mouseleave', selector, function () {
            $tip.removeClass('is-visible')
        })
}

function relevanceReleaseUiLock() {
    if (typeof window.cabinetReleaseUiLock === 'function') {
        window.cabinetReleaseUiLock()
    }
}

function renderClouds(competitors, mainPage, tfCompClouds, hide) {
    initRelevanceCloudTooltips()
    $('.clouds').show()
    $('#competitorsTfClouds').addClass('relevance-clouds-visible').show()
    sessionStorage.setItem('competitors', JSON.stringify(competitors))
    sessionStorage.setItem('mainPage', JSON.stringify(mainPage))
    sessionStorage.setItem('tfCompClouds', JSON.stringify(tfCompClouds))
    sessionStorage.setItem('hideBool', hide)
}

function arrayToObj(array) {
    if (!array) {
        return []
    }

    if (Array.isArray(array)) {
        return array.filter(function (item) {
            return item && item.text
        })
    }

    if (typeof array !== 'object') {
        return []
    }

    var result = []
    var keys = Object.keys(array)

    for (var i = 0; i < keys.length; i++) {
        var key = keys[i]
        if (key === 'count') {
            continue
        }
        var item = array[key]
        if (item && typeof item === 'object' && item.text) {
            result.push(item)
        }
    }

    return result
}

function normalizeCloudWords(words, limit, weightKind) {
    if (!words.length) {
        return []
    }

    weightKind = weightKind || 'tfidf'

    return words.slice().map(function (word) {
        var renderWeight

        if (weightKind === 'tfidf') {
            renderWeight = parseFloat(word.tfidfScore)
            if (!isFinite(renderWeight)) {
                renderWeight = parseFloat(word.weight) || 0
            }
        } else {
            renderWeight = parseFloat(word.weight) || 1
        }

        return {
            text: String(word.text),
            weight: renderWeight,
            tfidfScore: word.tfidfScore != null ? parseFloat(word.tfidfScore) : null,
        }
    }).sort(function (a, b) {
        return (parseFloat(b.weight) || 0) - (parseFloat(a.weight) || 0)
    }).slice(0, limit)
}

function relevanceCloudVisualRatio(rank, word, words) {
    var total = words.length
    if (!total) {
        return 1
    }

    var weight = Math.max(0, parseFloat(word.weight) || 0)
    var maxW = Math.max(0, parseFloat(words[0].weight) || 0)
    if (maxW <= 0) {
        return 0.5
    }

    // Главный сигнал — доля веса от лидера (0.3226 vs 0.1876 → ~1.0 vs ~0.58).
    var shareOfLeader = Math.min(1, weight / maxW)
    var weightVisual = Math.pow(shareOfLeader, 0.72)

    var leaderCount = Math.min(relevanceCloudLeaderCount, total)
    if (rank < leaderCount) {
        // Лёгкий бонус яруса, без подтягивания размера по позиции в списке.
        var tierBoost = rank < 3 ? 1.06 : (rank < 10 ? 1.03 : 1.01)

        return relevanceCloudLiftVisualRatio(Math.min(1, weightVisual * tierBoost))
    }

    return relevanceCloudLiftVisualRatio(Math.max(0.12, Math.min(0.45, weightVisual * 0.5)))
}

function relevanceCloudLiftVisualRatio(ratio) {
    var floor = relevanceCloudVisualFloor
    var clamped = Math.max(0, Math.min(1, ratio))

    if (clamped >= 1) {
        return 1
    }

    // Поднимаем хвост облака: мелкие слова остаются меньше лидеров, но читаются.
    return floor + (1 - floor) * Math.pow(clamped, 0.85)
}

function relevanceCloudFontSizePx(visualRatio, text) {
    var len = String(text).length
    var lenPenalty = Math.min(8, Math.max(0, len - 6) * 0.35)
    var spread = relevanceCloudSizeMaxPx - relevanceCloudSizeMinPx

    return Math.round(relevanceCloudSizeMinPx + visualRatio * spread - lenPenalty)
}

function relevanceCloudFontSizeRem(visualRatio) {
    return (0.82 + visualRatio * 1.55).toFixed(2)
}

function boxesOverlap(a, b, pad) {
    pad = pad || 5
    return !(
        a.right + pad <= b.left ||
        a.left >= b.right + pad ||
        a.bottom + pad <= b.top ||
        a.top >= b.bottom + pad
    )
}

function relevanceWeightClass(ratio) {
    var bucket = Math.max(1, Math.min(10, Math.round(ratio * 9) + 1))
    return 'relevance-spiral-cloud__word--w' + bucket
}

function paintSpiralCloud($host, words, height, weightKind, done) {
    height = height || 350
    if (words.length >= 80) {
        height = Math.max(height, 400)
    }
    weightKind = weightKind || 'tfidf'
    $host.empty().removeClass('jqcloud relevance-spiral-cloud--ready').addClass('relevance-spiral-cloud-host').css({
        height: height + 'px',
        width: '100%',
        position: 'relative',
    })

    if (!words.length) {
        $host.html('<span class="text-muted small">Нет данных</span>')
        if (typeof done === 'function') {
            done()
        }
        return
    }

    var hostW = Math.max(260, Math.floor($host.innerWidth() || $host.width() || 554))
    var hostH = Math.max(280, Math.floor(height))
    var placed = []
    var cx = hostW / 2
    var cy = hostH / 2
    var edgePad = 6
    var collisionPad = 1
    var maxAttempts = 950
    var $wrap = $('<div class="relevance-spiral-cloud"></div>').css({
        width: hostW + 'px',
        height: hostH + 'px',
    })
    $host.append($wrap)

    var index = 0
    var chunkSize = 20

    function placeWordElement($el, w, h, wordIndex) {
        var placedOk = false
        var angle = 0
        var radius = 0
        var step = 0.36
        var i
        var x
        var y
        var box

        if (wordIndex === 0) {
            x = cx
            y = cy
            box = {
                left: x - w / 2,
                top: y - h / 2,
                right: x + w / 2,
                bottom: y + h / 2,
            }
            $el.css({left: x + 'px', top: y + 'px'}).addClass('relevance-spiral-cloud__word--center')
            placed.push(box)
            return true
        }

        radius = Math.max(w, h) * 0.42

        for (i = 0; i < maxAttempts; i++) {
            x = cx + radius * Math.cos(angle)
            y = cy + radius * Math.sin(angle)
            box = {
                left: x - w / 2,
                top: y - h / 2,
                right: x + w / 2,
                bottom: y + h / 2,
            }

            if (box.left < edgePad || box.top < edgePad || box.right > hostW - edgePad || box.bottom > hostH - edgePad) {
                angle += step
                radius += 0.5
                continue
            }

            var collision = false
            var j
            for (j = 0; j < placed.length; j++) {
                if (boxesOverlap(box, placed[j], collisionPad)) {
                    collision = true
                    break
                }
            }

            if (!collision) {
                $el.css({left: x + 'px', top: y + 'px'})
                placed.push(box)
                placedOk = true
                break
            }

            angle += step
            radius += 0.5
        }

        return placedOk
    }

    function paintChunk() {
        var end = Math.min(index + chunkSize, words.length)

        for (; index < end; index++) {
            var word = words[index]
            var visualRatio = relevanceCloudVisualRatio(index, word, words)
            var sizePx = relevanceCloudFontSizePx(visualRatio, word.text)
            var tip = formatWordTip(word.text, word, weightKind)
            var $el = $('<span class="relevance-spiral-cloud__word"></span>')
                .addClass(relevanceWeightClass(visualRatio))
                .text(word.text)
                .attr('data-tip', tip)

            $wrap.append($el)

            var placedWord = false
            var attempt
            for (attempt = 0; attempt < 6; attempt++) {
                var trySize = Math.max(11, Math.round(sizePx * Math.pow(0.9, attempt)))
                $el.css('font-size', trySize + 'px')
                if (placeWordElement($el, $el.outerWidth(), $el.outerHeight(), index)) {
                    placedWord = true
                    break
                }
            }

            if (!placedWord) {
                $el.remove()
            }
        }

        if (index < words.length) {
            window.requestAnimationFrame(paintChunk)
            return
        }

        $host.addClass('relevance-spiral-cloud--ready')
        if (typeof done === 'function') {
            done()
        }
    }

    paintChunk()
}

function paintTagCloud($host, words, height, weightKind) {
    height = height || 350
    weightKind = weightKind || 'tfidf'
    $host.empty().removeClass('jqcloud relevance-spiral-cloud--ready').addClass('relevance-tag-cloud-host').css({
        height: height + 'px',
        width: '100%',
        position: 'relative',
    })

    var $wrap = $('<div class="relevance-tag-cloud"></div>')
    words.forEach(function (word, rank) {
        var visualRatio = relevanceCloudVisualRatio(rank, word, words)
        var sizeRem = relevanceCloudFontSizeRem(visualRatio)
        var tip = formatWordTip(word.text, word, weightKind)
        $wrap.append(
            $('<span class="relevance-tag-cloud__word"></span>')
                .addClass(relevanceWeightClass(visualRatio))
                .text(word.text)
                .css('font-size', sizeRem + 'rem')
                .attr('data-tip', tip)
        )
    })
    $host.append($wrap)
}

function paintOneCloud(selector, words, done, height, weightKind) {
    var $host = $(selector)

    if (!$host.length) {
        if (typeof done === 'function') {
            done()
        }
        return
    }

    initRelevanceCloudTooltips()
    var normalized = normalizeCloudWords(arrayToObj(words), relevanceCloudMaxWords, weightKind)

    try {
        paintSpiralCloud($host, normalized, height, weightKind, done)
    } catch (e) {
        console.error('renderClouds: spiral cloud', e)
        if (normalized.length) {
            paintTagCloud($host, normalized, height, weightKind)
        } else {
            $host.html('<span class="text-muted small">Нет данных</span>')
        }
        if (typeof done === 'function') {
            window.requestAnimationFrame(done)
        }
    }
}

function paintCloudsSequential(items, done) {
    var index = 0
    var total = items.length

    function next() {
        relevanceReleaseUiLock()

        if (index >= total) {
            relevanceCloudBusy = false
            $('#tf-idf-clouds, #text-clouds, #coverage-clouds-button').prop('disabled', false)
            relevanceReleaseUiLock()
            if (typeof done === 'function') {
                done()
            }
            return
        }

        var item = items[index]
        var step = index + 1
        index++

        if (item.buttonId && item.defaultLabel) {
            $('#' + item.buttonId).text(item.defaultLabel + ' (' + step + '/' + total + ')…')
        }

        paintOneCloud(item.selector, item.words, next, item.height, item.weightKind)
    }

    relevanceCloudBusy = true
    relevanceReleaseUiLock()
    $('#tf-idf-clouds, #text-clouds, #coverage-clouds-button').prop('disabled', true)
    next()
}

$("#tf-idf-clouds").click(function () {
    if (relevanceCloudBusy) {
        return
    }

    var $btn = $(this)
    var defaultLabel = $btn.data('default-label') || $btn.text().replace(/\s*\(\d+\/\d+\)…$/, '')

    if (!$btn.data('default-label')) {
        $btn.data('default-label', defaultLabel)
    }

    if (!$('.tf-idf-clouds').is(':visible')) {
        $('.tf-idf-clouds').show()
        if (!generatedTfIdf) {
            var competitors = JSON.parse(sessionStorage.getItem('competitors'))
            var mainPage = JSON.parse(sessionStorage.getItem('mainPage'))

            paintCloudsSequential([
                {selector: '#competitorsTfCloud', words: competitors.totalTf, buttonId: 'tf-idf-clouds', defaultLabel: defaultLabel, weightKind: 'tfidf'},
                {selector: '#mainPageTfCloud', words: mainPage.totalTf, buttonId: 'tf-idf-clouds', defaultLabel: defaultLabel, weightKind: 'tfidf'},
                {selector: '#competitorsTextTfCloud', words: competitors.textTf, buttonId: 'tf-idf-clouds', defaultLabel: defaultLabel, weightKind: 'tfidf'},
                {selector: '#mainPageTextTfCloud', words: mainPage.textTf, buttonId: 'tf-idf-clouds', defaultLabel: defaultLabel, weightKind: 'tfidf'},
                {selector: '#competitorsLinksTfCloud', words: competitors.linkTf, buttonId: 'tf-idf-clouds', defaultLabel: defaultLabel, weightKind: 'tfidf'},
                {selector: '#mainPageLinksTfCloud', words: mainPage.linkTf, buttonId: 'tf-idf-clouds', defaultLabel: defaultLabel, weightKind: 'tfidf'},
            ], function () {
                generatedTfIdf = true
                $btn.text(defaultLabel)
            })
        }
    } else {
        $('.tf-idf-clouds').hide()
    }
})

$("#text-clouds").click(function () {
    if (relevanceCloudBusy) {
        return
    }

    var $btn = $(this)
    var defaultLabel = $btn.data('default-label') || $btn.text().replace(/\s*\(\d+\/\d+\)…$/, '')

    if (!$btn.data('default-label')) {
        $btn.data('default-label', defaultLabel)
    }

    if (!$('.text-clouds').is(':visible')) {
        $('.text-clouds').show()
        if (!generatedText) {
            var competitors = JSON.parse(sessionStorage.getItem('competitors'))
            var mainPage = JSON.parse(sessionStorage.getItem('mainPage'))

            paintCloudsSequential([
                {selector: '#competitorsLinksCloud', words: competitors.links, buttonId: 'text-clouds', defaultLabel: defaultLabel, weightKind: 'repetitions'},
                {selector: '#mainPageLinksCloud', words: mainPage.links, buttonId: 'text-clouds', defaultLabel: defaultLabel, weightKind: 'repetitions'},
                {selector: '#competitorsTextCloud', words: competitors.text, buttonId: 'text-clouds', defaultLabel: defaultLabel, weightKind: 'repetitions'},
                {selector: '#mainPageTextCloud', words: mainPage.text, buttonId: 'text-clouds', defaultLabel: defaultLabel, weightKind: 'repetitions'},
                {selector: '#competitorsTextAndLinksCloud', words: competitors.textAndLinks, buttonId: 'text-clouds', defaultLabel: defaultLabel, weightKind: 'repetitions'},
                {selector: '#mainPageTextWithLinksCloud', words: mainPage.textWithLinks, buttonId: 'text-clouds', defaultLabel: defaultLabel, weightKind: 'repetitions'},
            ], function () {
                generatedText = true
                $btn.text(defaultLabel)
            })
        }
    } else {
        $('.text-clouds').hide()
    }
})

$('#coverage-clouds-button').click(function () {
    if (relevanceCloudBusy) {
        return
    }

    var $coverageClouds = $('#coverage-clouds')
    var isOpen = $coverageClouds.hasClass('coverage-clouds--open')

    if (!isOpen) {
        $coverageClouds.removeAttr('style').addClass('coverage-clouds--open')

        var tfCompCloudsRaw = sessionStorage.getItem('tfCompClouds')
        var tfCompClouds = null
        try {
            tfCompClouds = tfCompCloudsRaw ? JSON.parse(tfCompCloudsRaw) : null
        } catch (e) {
            console.error('coverage-clouds: invalid tfCompClouds in sessionStorage', e)
        }

        if (!tfCompClouds || typeof tfCompClouds !== 'object' || Object.keys(tfCompClouds).length === 0) {
            $coverageClouds.append("<p class='text-muted px-2'>Нет данных для облаков конкурентов.</p>")
            return
        }

        if (!generatedCompetitorCoverage) {
            var iterator = 1
            var coverageItems = []

            $.each(tfCompClouds, function (key, value) {
                var btnGroup =
                    "<div class='btn-group coverage-cloud-item__actions'>" +
                    "        <button type='button' data-bs-toggle='dropdown' aria-expanded='false' class='text-dark btn btn-tool dropdown-toggle'>" +
                    "            <i class='fas fa-external-link-alt'></i>" +
                    "        </button> " +
                    "       <div role='menu' class='dropdown-menu dropdown-menu-left'>" +
                    "            <a target='_blank' class='dropdown-item' href='" + key + "'>" +
                    "                <i class='fas fa-external-link-alt'></i> Перейти на посадочную страницу</a>" +
                    "            <span class='dropdown-item add-in-ignored-domains' style='cursor: pointer'" +
                    "                  data-target='" + key + "'>" +
                    "                <i class='fas fa-external-link-alt'></i>" +
                    "                Добавить в игнорируемые домены" +
                    "            </span>" +
                    "        </div>" +
                    "</div>"

                $('#coverage-clouds').append(
                    "<div class='coverage-cloud-item render'>" +
                    "<div class='coverage-cloud-item__header'>" +
                    "<a class='competitor-cloud' href='" + key + "' target='_blank' rel='noopener'>" + key + "</a>" +
                    btnGroup +
                    "</div>" +
                    "<div id='cloud" + iterator + "' class='generated-cloud coverage-cloud-host'></div>" +
                    "</div>"
                )

                coverageItems.push({
                    selector: '#cloud' + iterator,
                    words: value,
                    height: 400,
                    weightKind: 'tfidf',
                })
                iterator++
            })

            paintCloudsSequential(coverageItems, function () {
                generatedCompetitorCoverage = true

                $('.add-in-ignored-domains').click(function () {
                    var url = new URL($(this).attr('data-target'))
                    var textarea = $('.form-control.ignoredDomains')
                    var string = textarea.val()
                    if (!string.includes(url.hostname)) {
                        var domain = (url.hostname).replace('www.', '')
                        if (textarea.val().slice(-1) === "\n") {
                            textarea.val(textarea.val() + domain + "\n")
                        } else {
                            textarea.val(textarea.val() + "\n" + domain + "\n")
                        }

                        var toastr = $('.toast-top-right.success-message.lock-word')
                        toastr.show(300)
                        setTimeout(function () {
                            toastr.hide(300)
                        }, 3000)
                    }
                })

                var links = []
                $.each($('.ignored-site'), function (key, value) {
                    var text = $(value).children('td').eq(1).children('div').eq(0).children('div').eq(0).children('a').eq(0).attr('href')
                    links.push(text)
                })
                var compClouds = $('.competitor-cloud')

                $('#showOrHideIgnoredClouds').click(function () {
                $.each(compClouds, function (key, value) {
                    for (var i = 0; i < links.length; i++) {
                        if (links[i] == $(value).attr('href') || links[i] == $(value).text()) {
                                var object = $(value).parent().parent()
                                if (object.is(':visible')) {
                                    object.hide()
                                } else {
                                    object.show()
                                }
                            }
                        }
                    })
                })

                var hide = sessionStorage.getItem('hideBool')
                if (hide === 'yes') {
                    $('#showOrHideIgnoredClouds').trigger('click')
                }
            })
        }
    } else {
        $coverageClouds.removeClass('coverage-clouds--open').removeAttr('style')
    }
})

function showOrHideIgnoredDomains() {
    var hide = Boolean(sessionStorage.getItem('hideBool'))
    if (hide) {
        $('#showOrHideIgnoredClouds').trigger('click')
    }
}

$(function () {
    initRelevanceCloudTooltips()
})
