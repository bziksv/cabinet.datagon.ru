function refreshAll() {
    $('#start-analyse').prop('disabled', true);
    $('.top-sites').hide();
    $('.nested').hide();
    $('.positions').hide();
    $('.tag-analysis').hide();
    $('#sites-block').hide();
    $('.urls.mt-5').hide();
    $('#render-bar').hide();
    if (typeof resetCompetitorRecommendations === 'function') {
        resetCompetitorRecommendations();
    } else {
        $('#recommendations-block').hide();
    }
    if (typeof hideGeoDependencyVerdict === 'function') {
        hideGeoDependencyVerdict();
    }
    $('.render').remove();
    $('.extra-th').hide();

    if ($.fn.dataTable) {
        if ($('#positions').length && $.fn.dataTable.isDataTable('#positions')) {
            $('#positions').DataTable().destroy();
        }
        if ($('#urls-table').length && $.fn.dataTable.isDataTable('#urls-table')) {
            $('#urls-table').DataTable().destroy();
        }
    }

    if (typeof setProgressBarStyles === 'function') {
        setProgressBarStyles(1);
    }
    $('#progress-bar').show(300);
}
