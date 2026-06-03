/**
 * Woo B2B — front-end auth page behaviour.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        // Toggle the "different shipping address" block.
        var checkbox = document.getElementById('wb2b-different-shipping');
        var shipping = document.querySelector('.wb2b-shipping');

        function syncShipping() {
            if (!shipping) {
                return;
            }
            var on = checkbox && checkbox.checked;
            if (on) {
                shipping.removeAttribute('hidden');
            } else {
                shipping.setAttribute('hidden', '');
            }
            Array.prototype.forEach.call(shipping.querySelectorAll('.wb2b-ship-input'), function (el) {
                if (on) {
                    el.setAttribute('required', 'required');
                } else {
                    el.removeAttribute('required');
                }
            });
        }

        if (checkbox) {
            checkbox.addEventListener('change', syncShipping);
            syncShipping();
        }

        // Live "must match" validation for confirmation fields.
        Array.prototype.forEach.call(document.querySelectorAll('[data-match]'), function (el) {
            var target = document.querySelector(el.getAttribute('data-match'));

            function check() {
                if (!target) {
                    return;
                }
                if (el.value !== target.value) {
                    el.setCustomValidity(el.getAttribute('data-mismatch') || 'The values do not match.');
                } else {
                    el.setCustomValidity('');
                }
            }

            el.addEventListener('input', check);
            if (target) {
                target.addEventListener('input', check);
            }
        });
    });
})();
