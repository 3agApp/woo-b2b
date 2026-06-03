/**
 * Woo B2B — admin approvals behaviour.
 */
(function ($) {
    'use strict';

    function handle(action, data, $row, $status, extra) {
        $row.find('button').prop('disabled', true);
        $status.text(wb2b_admin.strings.working);

        $.post(wb2b_admin.ajax_url, $.extend({
            action: action,
            nonce: wb2b_admin.nonce
        }, data)).done(function (response) {
            if (response && response.success) {
                $row.fadeOut(300, function () {
                    $(this).remove();
                });
            } else {
                $status.text((response && response.data && response.data.message) || wb2b_admin.strings.error);
                $row.find('button').prop('disabled', false);
            }
        }).fail(function () {
            $status.text(wb2b_admin.strings.error);
            $row.find('button').prop('disabled', false);
        });
    }

    $(function () {
        $(document).on('click', '.wb2b-approve', function () {
            var id = $(this).data('user-id');
            if (!window.confirm(wb2b_admin.strings.confirm_approve)) {
                return;
            }
            var $row = $('#wb2b-user-' + id);
            handle('wb2b_approve', { user_id: id }, $row, $row.find('.wb2b-action-status'));
        });

        $(document).on('click', '.wb2b-reject', function () {
            var id = $(this).data('user-id');
            var reason = window.prompt(wb2b_admin.strings.reject_prompt, '');
            if (reason === null) {
                return;
            }
            var $row = $('#wb2b-user-' + id);
            handle('wb2b_reject', { user_id: id, reason: reason }, $row, $row.find('.wb2b-action-status'));
        });
    });
})(jQuery);
