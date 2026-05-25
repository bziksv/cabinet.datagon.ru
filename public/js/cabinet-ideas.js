(function ($) {
    'use strict';

    var csrf = $('meta[name="csrf-token"]').attr('content');

    $(document).on('click', '.cabinet-ideas-vote__btn:not(.is-disabled):not(:disabled)', function () {
        var $btn = $(this);
        var url = $btn.data('vote-url');

        if (!url || $btn.data('loading')) {
            return;
        }

        $btn.data('loading', true).prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            dataType: 'json',
        })
            .done(function (data) {
                var $count = $btn.find('[data-votes-count]');
                $count.text(data.votes_count);
                $btn.toggleClass('is-voted', !!data.voted);
                $btn.attr('aria-pressed', data.voted ? 'true' : 'false');
            })
            .fail(function (xhr) {
                var msg =
                    (xhr.responseJSON && xhr.responseJSON.message) ||
                    'Не удалось сохранить голос. Попробуйте ещё раз.';
                if (typeof flash === 'function') {
                    flash(msg, 'Ошибка');
                } else {
                    alert(msg);
                }
            })
            .always(function () {
                $btn.data('loading', false).prop('disabled', false);
            });
    });
})(jQuery);
