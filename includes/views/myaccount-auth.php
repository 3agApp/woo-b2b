<?php
/**
 * My Account login-screen override.
 *
 * Renders the unified Woo B2B login + custom registration UI in place of
 * WooCommerce/WoodMart's native myaccount/form-login.php for logged-out visitors.
 * Loaded via the `wc_get_template` filter in WB2B_Auth::override_login_template().
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('WB2B') && WB2B()->auth) {
    // render_auth_page() escapes all output internally.
    echo WB2B()->auth->render_auth_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
