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

    // Promise-based modal — replaces native confirm()/prompt().
    // Resolves: confirm → true/false; prompt → entered string/null (cancel).
    function openModal(opts) {
        var s = wb2b_admin.strings;
        var isPrompt = !!opts.prompt;

        return new Promise(function (resolve) {
            var $overlay = $('<div class="wb2b-modal-overlay"></div>');
            var $modal = $('<div class="wb2b-modal" role="dialog" aria-modal="true" aria-labelledby="wb2b-modal-title"></div>');
            if (opts.danger) {
                $modal.addClass('wb2b-modal--danger');
            }

            var icon = opts.icon || (opts.danger ? 'dashicons-warning' : 'dashicons-info-outline');
            var $header = $('<div class="wb2b-modal-header"></div>')
                .append('<span class="wb2b-modal-icon"><span class="dashicons ' + icon + '"></span></span>')
                .append($('<h2 class="wb2b-modal-title" id="wb2b-modal-title"></h2>').text(opts.title || ''));

            var $body = $('<div class="wb2b-modal-body"></div>');
            if (opts.message) {
                $body.append($('<p class="wb2b-modal-message"></p>').text(opts.message));
            }
            var $field = null;
            if (isPrompt) {
                if (opts.label) {
                    $body.append($('<label class="wb2b-label" for="wb2b-modal-field"></label>').text(opts.label));
                }
                $field = $('<textarea id="wb2b-modal-field" class="wb2b-input" rows="3"></textarea>')
                    .attr('placeholder', opts.placeholder || '');
                $body.append($field);
            }

            var $cancel = $('<button type="button" class="wb2b-btn wb2b-btn-secondary"></button>').text(opts.cancelLabel || s.cancel);
            var $confirm = $('<button type="button" class="wb2b-btn"></button>')
                .addClass(opts.danger ? 'wb2b-btn-danger' : 'wb2b-btn-primary')
                .text(opts.confirmLabel || s.confirm);
            var $footer = $('<div class="wb2b-modal-footer"></div>').append($cancel).append($confirm);

            $modal.append($header, $body, $footer);
            $overlay.append($modal).appendTo('body');

            var lastFocus = document.activeElement;
            $overlay[0].offsetWidth; // force reflow so the open transition runs
            $overlay.addClass('is-open');
            ($field || $confirm).trigger('focus');

            function close(result) {
                $(document).off('keydown.wb2bModal');
                $overlay.removeClass('is-open');
                setTimeout(function () { $overlay.remove(); }, 150);
                if (lastFocus && lastFocus.focus) {
                    lastFocus.focus();
                }
                resolve(result);
            }

            $confirm.on('click', function () {
                close(isPrompt ? ($field.val() || '') : true);
            });
            $cancel.on('click', function () {
                close(isPrompt ? null : false);
            });
            $overlay.on('mousedown', function (e) {
                if (e.target === $overlay[0]) {
                    close(isPrompt ? null : false);
                }
            });
            $(document).on('keydown.wb2bModal', function (e) {
                if (e.key === 'Escape') {
                    close(isPrompt ? null : false);
                } else if (e.key === 'Enter' && !isPrompt) {
                    e.preventDefault();
                    close(true);
                } else if (e.key === 'Tab') {
                    var $f = $overlay.find('button, textarea, input, a[href]').filter(':visible');
                    if (!$f.length) {
                        return;
                    }
                    var first = $f[0];
                    var last = $f[$f.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        });
    }

    function wb2bConfirm(opts) {
        return openModal(opts);
    }

    function wb2bPrompt(opts) {
        return openModal($.extend({}, opts, { prompt: true }));
    }

    $(function () {
        $(document).on('click', '.wb2b-approve', function () {
            var id = $(this).data('user-id');
            wb2bConfirm({
                title: wb2b_admin.strings.approve_title,
                message: wb2b_admin.strings.confirm_approve,
                confirmLabel: wb2b_admin.strings.approve,
                icon: 'dashicons-yes-alt'
            }).then(function (ok) {
                if (!ok) {
                    return;
                }
                var $row = $('#wb2b-user-' + id);
                handle('wb2b_approve', { user_id: id }, $row, $row.find('.wb2b-action-status'));
            });
        });

        $(document).on('click', '.wb2b-reject', function () {
            var id = $(this).data('user-id');
            wb2bPrompt({
                title: wb2b_admin.strings.reject_title,
                label: wb2b_admin.strings.reject_prompt,
                confirmLabel: wb2b_admin.strings.reject,
                danger: true,
                icon: 'dashicons-dismiss'
            }).then(function (reason) {
                if (reason === null) {
                    return;
                }
                var $row = $('#wb2b-user-' + id);
                handle('wb2b_reject', { user_id: id, reason: reason }, $row, $row.find('.wb2b-action-status'));
            });
        });

        // ----- Bulk selection + actions -----
        function selectedIds() {
            return $('.wb2b-row-check:checked').map(function () { return $(this).val(); }).get();
        }

        function refreshBulkBar() {
            var ids = selectedIds();
            var total = $('.wb2b-row-check').length;
            $('.wb2b-bulk-n').text(ids.length);
            $('.wb2b-bulkbar').prop('hidden', ids.length === 0);
            $('.wb2b-check-all').prop('checked', total > 0 && ids.length === total);
        }

        $(document).on('change', '.wb2b-check-all', function () {
            $('.wb2b-row-check').prop('checked', $(this).prop('checked'));
            refreshBulkBar();
        });
        $(document).on('change', '.wb2b-row-check', refreshBulkBar);

        function bulk(doAction, reason) {
            var ids = selectedIds();
            if (!ids.length) {
                return;
            }
            $('.wb2b-bulkbar').find('button').prop('disabled', true);
            $.post(wb2b_admin.ajax_url, { action: 'wb2b_bulk', nonce: wb2b_admin.nonce, do: doAction, user_ids: ids, reason: reason || '' })
                .done(function (response) {
                    if (response && response.success) {
                        notice((response.data && response.data.message) || '', true);
                        $.each(ids, function (i, id) {
                            $('#wb2b-user-' + id).fadeOut(300, function () { $(this).remove(); });
                        });
                        setTimeout(function () { window.location.reload(); }, 700);
                    } else {
                        notice((response && response.data && response.data.message) || wb2b_admin.strings.error, false);
                        $('.wb2b-bulkbar').find('button').prop('disabled', false);
                    }
                })
                .fail(function () {
                    notice(wb2b_admin.strings.error, false);
                    $('.wb2b-bulkbar').find('button').prop('disabled', false);
                });
        }

        $(document).on('click', '.wb2b-bulk-approve', function () {
            var n = selectedIds().length;
            wb2bConfirm({
                title: wb2b_admin.strings.approve_title,
                message: (wb2b_admin.strings.bulk_approve_confirm || '').replace('%d', n),
                confirmLabel: wb2b_admin.strings.approve,
                icon: 'dashicons-yes-alt'
            }).then(function (ok) { if (ok) { bulk('approve', ''); } });
        });

        $(document).on('click', '.wb2b-bulk-reject', function () {
            wb2bPrompt({
                title: wb2b_admin.strings.reject_title,
                label: wb2b_admin.strings.reject_prompt,
                confirmLabel: wb2b_admin.strings.reject,
                danger: true,
                icon: 'dashicons-dismiss'
            }).then(function (reason) { if (reason !== null) { bulk('reject', reason); } });
        });

        // ----- Settings: AJAX save -----
        $('#wb2b-settings-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var label = $btn.html();
            $btn.prop('disabled', true).html('<span class="wb2b-spinner"></span> ' + wb2b_admin.strings.working);
            $.post(wb2b_admin.ajax_url, $form.serialize() + '&action=wb2b_save_settings&nonce=' + encodeURIComponent(wb2b_admin.nonce))
                .done(function (response) {
                    if (response && response.success) {
                        var $msg = $form.find('.wb2b-saved-msg').addClass('is-visible');
                        setTimeout(function () { $msg.removeClass('is-visible'); }, 2500);
                        notice((response.data && response.data.message) || '', true);
                    } else {
                        notice((response && response.data && response.data.message) || wb2b_admin.strings.error, false);
                    }
                    $btn.prop('disabled', false).html(label);
                })
                .fail(function () {
                    notice(wb2b_admin.strings.error, false);
                    $btn.prop('disabled', false).html(label);
                });
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
            var $btn = $(this);
            wb2bConfirm({
                title: wb2b_admin.strings.deactivate_title,
                message: wb2b_admin.strings.confirm_deactivate,
                confirmLabel: wb2b_admin.strings.deactivate,
                danger: true
            }).then(function (ok) {
                if (ok) {
                    action($btn, 'wb2b_deactivate_license');
                }
            });
        });

        $('#wb2b-check-license').on('click', function () {
            action($(this), 'wb2b_check_license');
        });

        // Updates.
        $('#wb2b-check-update').on('click', function () {
            action($(this), 'wb2b_check_update');
        });

        $('#wb2b-install-update').on('click', function () {
            var $btn = $(this);
            wb2bConfirm({
                title: wb2b_admin.strings.update_title,
                message: wb2b_admin.strings.confirm_install,
                confirmLabel: wb2b_admin.strings.update,
                icon: 'dashicons-download'
            }).then(function (ok) {
                if (ok) {
                    action($btn, 'wb2b_install_update');
                }
            });
        });
    });
})(jQuery);
