$(document).ready(function () {
    var $calcPanel = $('#calc');
    var $prognozPanel = $('#prognoz');

    function num(val) {
        var n = parseFloat(val);
        return isFinite(n) ? n : 0;
    }

    function safeRatio(numerator, denominator) {
        if (!denominator) {
            return null;
        }
        return numerator / denominator;
    }

    function formatPercent(ratio, decimals) {
        if (ratio === null || !isFinite(ratio)) {
            return '';
        }
        return (ratio * 100).toFixed(decimals === undefined ? 2 : decimals);
    }

    function formatNumber(value, decimals) {
        if (value === null || !isFinite(value)) {
            return '';
        }
        return value.toFixed(decimals === undefined ? 0 : decimals);
    }

    function clearRoiMetrics() {
        $('#calc .cabinet-roi-metric__value').text('');
        $('#calc input[type="hidden"][id$="-val"]').val('');
        $('#calc .box-name').removeClass('bg-green bg-yellow bg-red');
    }

    function clearPrognozMetrics() {
        $('#prognoz .cabinet-roi-metric__value').text('');
        $('#prognoz input[type="hidden"][id^="rez-"]').val('');
        $('#prognoz .box-name').removeClass('bg-green bg-yellow bg-red');
    }

    function applyRoiHighlight() {
        var roiVal = num($('#rez-roi-roi-val').val());
        var $roiName = $('#bg-change-roi');
        $roiName.removeClass('bg-green bg-yellow bg-red');
        if (roiVal > 100) {
            $roiName.addClass('bg-green');
        } else if (roiVal > 0) {
            $roiName.addClass('bg-yellow');
        } else if ($('#rez-roi-roi-val').val() !== '') {
            $roiName.addClass('bg-red');
        }
    }

    function applyRevenueHighlight() {
        var revenue = num($('#rez-perrevenue').val());
        var budget = num($('input[name="budget"]').val());
        var $revName = $('#bg-change-prrev');
        $revName.removeClass('bg-green bg-yellow bg-red');
        if ($('#rez-perrevenue').val() === '') {
            return;
        }
        if (revenue < budget) {
            $revName.addClass('bg-red');
        } else {
            $revName.addClass('bg-green');
        }
    }

    $prognozPanel.hide();

    $('#go-calc').on('click', function () {
        var cost = num($('input[name="zatrat"]').val());
        var income = num($('input[name="doxod"]').val());
        var views = num($('input[name="prosmotr"]').val());
        var clicks = num($('input[name="kliki"]').val());
        var actions = num($('input[name="zayavka"]').val());
        var sales = num($('input[name="pokupka"]').val());

        var roiPct = safeRatio(income - cost, cost);
        var ctr = safeRatio(clicks, views);
        var ctc = safeRatio(actions, clicks);
        var ctb = safeRatio(sales, actions);
        var cpm = safeRatio(cost, views);
        var cpc = safeRatio(cost, clicks);
        var cpa = safeRatio(cost, actions);
        var cps = safeRatio(cost, sales);
        var apv = safeRatio(income, sales);
        var apc = safeRatio(income, clicks);

        $('#rez-roi-roi-val').val(formatPercent(roiPct, 0));
        $('#rez-roi-ctr-val').val(formatPercent(ctr, 2));
        $('#rez-roi-ctc-val').val(formatPercent(ctc, 2));
        $('#rez-roi-ctb-val').val(formatPercent(ctb, 2));
        $('#rez-roi-cpm-val').val(cpm === null ? '' : formatNumber(cpm * 1000, 1));
        $('#rez-roi-cpc-val').val(formatNumber(cpc, 1));
        $('#rez-roi-cpa-val').val(formatNumber(cpa, 1));
        $('#rez-roi-cps-val').val(formatNumber(cps, 1));
        $('#rez-roi-apv-val').val(formatNumber(apv, 1));
        $('#rez-roi-apc-val').val(formatNumber(apc, 1));

        $('#rez-roi-roi').text($('#rez-roi-roi-val').val());
        $('#rez-roi-ctr').text($('#rez-roi-ctr-val').val());
        $('#rez-roi-ctc').text($('#rez-roi-ctc-val').val());
        $('#rez-roi-ctb').text($('#rez-roi-ctb-val').val());
        $('#rez-roi-cpm').text($('#rez-roi-cpm-val').val());
        $('#rez-roi-cpc').text($('#rez-roi-cpc-val').val());
        $('#rez-roi-cpa').text($('#rez-roi-cpa-val').val());
        $('#rez-roi-cps').text($('#rez-roi-cps-val').val());
        $('#rez-roi-apv').text($('#rez-roi-apv-val').val());
        $('#rez-roi-apc').text($('#rez-roi-apc-val').val());

        applyRoiHighlight();
    });

    $('#go-reset').on('click', function () {
        $calcPanel.find('input[type="number"]').val('');
        clearRoiMetrics();
    });

    $('#go-prognoz').on('click', function () {
        var budget = num($('input[name="budget"]').val());
        var clickCost = num($('input[name="clickcost"]').val());
        var convAction = num($('input[name="convaction"]').val());
        var convSales = num($('input[name="convsales"]').val());
        var avgCheck = num($('input[name="sredcheck"]').val());

        var clicks = safeRatio(budget, clickCost);
        var actions = clicks === null ? null : clicks * (convAction / 100);
        var sales = actions === null ? null : actions * (convSales / 100);
        var revenue = sales === null ? null : sales * avgCheck;
        var roiPct = safeRatio(revenue === null ? null : revenue - budget, budget);

        $('#rez-perclicks').val(formatNumber(clicks, 0));
        $('#rez-peractions').val(formatNumber(actions, 0));
        $('#rez-persales').val(formatNumber(sales, 0));
        $('#rez-perrevenue').val(formatNumber(revenue, 0));
        $('#rez-perroi').val(formatPercent(roiPct, 0));

        $('#perclicks').text($('#rez-perclicks').val());
        $('#peractions').text($('#rez-peractions').val());
        $('#persales').text($('#rez-persales').val());
        $('#perrevenue').text($('#rez-perrevenue').val());
        $('#perroi').text($('#rez-perroi').val());

        applyRevenueHighlight();
    });

    $('#go-prreset').on('click', function () {
        $prognozPanel.find('input[type="number"]').val('');
        clearPrognozMetrics();
    });

    $('#pokupka').on('keyup', function (e) {
        if (e.keyCode === 13) {
            $('#go-calc').click();
        }
    });

    $('#sredcheck').on('keyup', function (e) {
        if (e.keyCode === 13) {
            $('#go-prognoz').click();
        }
    });

    $('#myonoffswitch').on('change', function () {
        if ($(this).prop('checked')) {
            $calcPanel.hide();
            $prognozPanel.show();
        } else {
            $prognozPanel.hide();
            $calcPanel.show();
        }
    });

    $('.cabinet-roi-page .btn-info').on('click', function () {
        $('.cabinet-roi-page .btn-info').removeClass('active');
        $(this).addClass('active');
        $('.box-result').hide();
        $('#' + $(this).attr('data-id')).show();
    });
});
