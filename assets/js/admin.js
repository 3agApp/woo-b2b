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

    // Toast notice.
    function notice(message, ok) {
        var $t = $('<div class="wb2b-toast"></div>')
            .addClass(ok ? 'wb2b-toast-success' : 'wb2b-toast-error')
            .text(message)
            .appendTo('body');
        setTimeout(function () {
            $t.fadeOut(300, function () { $(this).remove(); });
        }, ok ? 2500 : 5000);
    }

    // License/update AJAX with button busy state; reloads on success when asked.
    function action($btn, ajaxAction, data) {
        var label = $btn.html();
        $btn.prop('disabled', true).html('<span class="wb2b-spinner"></span> ' + wb2b_admin.strings.working);

        $.post(wb2b_admin.ajax_url, $.extend({ action: ajaxAction, nonce: wb2b_admin.nonce }, data || {}))
            .done(function (response) {
                if (response && response.success) {
                    notice((response.data && response.data.message) || '', true);
                    if (response.data && response.data.reload) {
                        setTimeout(function () { window.location.reload(); }, 800);
                        return;
                    }
                } else {
                    notice((response && response.data && response.data.message) || wb2b_admin.strings.error, false);
                }
                $btn.prop('disabled', false).html(label);
            })
            .fail(function () {
                notice(wb2b_admin.strings.error, false);
                $btn.prop('disabled', false).html(label);
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

        // License management.
        $('#wb2b-license-form').on('submit', function (e) {
            e.preventDefault();
            var key = $('#wb2b-license-key').val().trim();
            if (!key) {
                notice(wb2b_admin.strings.error, false);
                return;
            }
            action($(this).find('button[type="submit"]'), 'wb2b_activate_license', { license_key: key });
        });

        $('#wb2b-activate-domain').on('click', function () {
            action($(this), 'wb2b_activate_domain');
        });

        $('#wb2b-deactivate-license').on('click', function () {
            if (!window.confirm(wb2b_admin.strings.confirm_deactivate)) {
                return;
            }
            action($(this), 'wb2b_deactivate_license');
        });

        $('#wb2b-check-license').on('click', function () {
            action($(this), 'wb2b_check_license');
        });

        // Updates.
        $('#wb2b-check-update').on('click', function () {
            action($(this), 'wb2b_check_update');
        });

        $('#wb2b-install-update').on('click', function () {
            if (!window.confirm(wb2b_admin.strings.confirm_install)) {
                return;
            }
            action($(this), 'wb2b_install_update');
        });
    });
})(jQuery);
