<?php
/**
 * Access Class
 *
 * Locks the front end behind login + approval and blocks unapproved logins.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Access {

    public function __construct() {
        add_action('template_redirect', [$this, 'maybe_redirect'], 1);
        add_filter('authenticate', [$this, 'block_unapproved_login'], 30, 3);
        add_filter('wp_robots', [$this, 'noindex_when_locked']);
    }

    /**
     * Auth page ID.
     *
     * @return int
     */
    public static function get_auth_page_id() {
        return (int) get_option('wb2b_auth_page_id', 0);
    }

    /**
     * Current access mode: how the storefront is gated.
     *
     *  - 'redirect' — lockdown; guests are sent to the My Account (auth) page.
     *  - 'prices'   — public catalog, but prices are hidden and purchasing disabled for guests.
     *
     * The store is always gated in one of these two modes; to disable the plugin entirely,
     * deactivate it. Falls back to the legacy `wb2b_enabled` boolean so existing installs keep
     * their gated behaviour (always → 'redirect') until the option is saved from Settings.
     *
     * @return string
     */
    public static function get_mode() {
        $mode = get_option('wb2b_access_mode', 'redirect');

        return in_array($mode, ['redirect', 'prices'], true) ? $mode : 'redirect';
    }

    /**
     * Whether the current request should bypass the gate.
     *
     * @return bool
     */
    protected function is_bypassed() {
        if (is_admin() || wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (is_feed() || is_robots() || (function_exists('is_favicon') && is_favicon())) {
            return true;
        }

        // Admins / shop managers are never gated.
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        return false;
    }

    /**
     * Whether the currently queried page is always allowed (auth page + allowlist).
     *
     * @return bool
     */
    protected function is_allowed_page() {
        $object_id = get_queried_object_id();

        if (!$object_id) {
            return false;
        }

        // The My Account page is the auth surface — always public (avoids a redirect loop).
        $account_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('myaccount') : 0;
        if ($account_id > 0 && $object_id === $account_id) {
            return true;
        }

        // The standalone [woo_b2b_auth] page (backward-compat alias) stays public too.
        $auth_page_id = self::get_auth_page_id();
        if ($auth_page_id && $object_id === $auth_page_id) {
            return true;
        }

        $allowed = (array) get_option('wb2b_allowed_pages', []);
        $allowed = array_map('intval', $allowed);

        return in_array((int) $object_id, $allowed, true);
    }

    /**
     * URL of the auth surface (the WooCommerce My Account page), with the standalone
     * [woo_b2b_auth] page as a fallback.
     *
     * @return string Empty string if neither can be resolved.
     */
    public static function get_auth_url() {
        $account = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
        if ($account) {
            return $account;
        }

        $auth_page_id = self::get_auth_page_id();
        return $auth_page_id ? (string) get_permalink($auth_page_id) : '';
    }

    /**
     * Redirect guests / unapproved users to the auth (My Account) page.
     */
    public function maybe_redirect() {
        if ($this->is_bypassed()) {
            return;
        }

        // Only lockdown mode redirects; 'prices' keeps the catalog public.
        if (self::get_mode() !== 'redirect') {
            return;
        }

        // Approved, logged-in users may continue.
        if (is_user_logged_in() && WB2B_Customer::has_access(get_current_user_id())) {
            return;
        }

        // The auth surface and any allowlisted page stay public.
        if ($this->is_allowed_page()) {
            return;
        }

        $target = self::get_auth_url();
        if (!$target) {
            // No auth surface resolvable — fail open rather than trap the visitor.
            return;
        }

        // Preserve where the visitor wanted to go for post-login bounce-back.
        $current = home_url(add_query_arg([]));
        $target  = add_query_arg('redirect_to', rawurlencode($current), $target);

        wp_safe_redirect($target);
        exit;
    }

    /**
     * Block login for users who are not approved.
     *
     * @param WP_User|WP_Error|null $user
     * @param string                $username
     * @param string                $password
     * @return WP_User|WP_Error|null
     */
    public function block_unapproved_login($user, $username, $password) {
        // Let earlier errors (bad credentials, empty fields) flow through.
        if (!$user instanceof WP_User) {
            return $user;
        }

        // Privileged users are always allowed.
        if (user_can($user, 'manage_woocommerce')) {
            return $user;
        }

        $status = WB2B_Customer::get_status($user->ID);

        if ($status === WB2B_Customer::STATUS_PENDING) {
            return new WP_Error(
                'wb2b_pending',
                __('Your account is awaiting approval. We will email you once it has been reviewed.', 'woo-b2b')
            );
        }

        if ($status === WB2B_Customer::STATUS_REJECTED) {
            return new WP_Error(
                'wb2b_rejected',
                __('Your registration was not approved. Please contact us for more information.', 'woo-b2b')
            );
        }

        return $user;
    }

    /**
     * Add noindex when the visitor is being gated, so locked URLs are not indexed.
     *
     * @param array $robots
     * @return array
     */
    public function noindex_when_locked($robots) {
        if ($this->is_bypassed()) {
            return $robots;
        }

        // Only the full-lock mode hides pages behind a redirect; the 'prices' catalog stays indexable.
        if (self::get_mode() !== 'redirect') {
            return $robots;
        }

        if (is_user_logged_in() && WB2B_Customer::has_access(get_current_user_id())) {
            return $robots;
        }

        $robots['noindex']  = true;
        $robots['nofollow'] = true;

        return $robots;
    }
}
