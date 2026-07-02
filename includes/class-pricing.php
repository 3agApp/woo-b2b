<?php
/**
 * Pricing Class
 *
 * Implements the 'prices' access mode (see WB2B_Access::get_mode()): the catalog stays public, but
 * product prices are hidden and purchasing is disabled for guests until they log in. Hooks are only
 * registered while that mode is active; everything is scoped to logged-out visitors so approved
 * customers, shop managers and admins keep seeing prices as normal.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Pricing {

    public function __construct() {
        if (WB2B_Access::get_mode() !== 'prices') {
            return;
        }

        add_filter('woocommerce_get_price_html', [$this, 'filter_price_html'], 100, 2);
        add_filter('woocommerce_variable_empty_price_html', [$this, 'filter_price_html'], 100, 2);
        add_filter('woocommerce_is_purchasable', [$this, 'filter_is_purchasable'], 100, 2);
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'filter_loop_add_to_cart'], 100, 3);
        add_action('woocommerce_single_product_summary', [$this, 'render_single_login_button'], 31);
    }

    /**
     * Whether prices should be hidden for the current request.
     *
     * Skips the backend (mirrors WB2B_Payments) and only acts for logged-out visitors.
     *
     * @return bool
     */
    protected function should_hide() {
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }

        return !is_user_logged_in();
    }

    /**
     * Where the "Log in to see prices" links point.
     *
     * Prefers the configured auth page, then the My Account page, then wp-login.
     *
     * @return string
     */
    protected function login_url() {
        $auth_page_id = WB2B_Access::get_auth_page_id();
        if ($auth_page_id) {
            $permalink = get_permalink($auth_page_id);
            if ($permalink) {
                return $permalink;
            }
        }

        if (function_exists('wc_get_page_permalink')) {
            $account = wc_get_page_permalink('myaccount');
            if ($account) {
                return $account;
            }
        }

        return wp_login_url();
    }

    /**
     * Replace the price with a "Log in to see prices" link.
     *
     * @param string     $price_html
     * @param WC_Product $product
     * @return string
     */
    public function filter_price_html($price_html, $product) {
        if (!$this->should_hide()) {
            return $price_html;
        }

        return sprintf(
            '<span class="wb2b-price-login"><a href="%s">%s</a></span>',
            esc_url($this->login_url()),
            esc_html__('Log in to see prices', 'woo-b2b')
        );
    }

    /**
     * Make products non-purchasable for guests so they cannot be added to the cart.
     *
     * @param bool       $purchasable
     * @param WC_Product $product
     * @return bool
     */
    public function filter_is_purchasable($purchasable, $product) {
        if (!$this->should_hide()) {
            return $purchasable;
        }

        return false;
    }

    /**
     * Replace the shop-loop Add to Cart button with a login link.
     *
     * @param string     $html
     * @param WC_Product $product
     * @param array      $args
     * @return string
     */
    public function filter_loop_add_to_cart($html, $product, $args = []) {
        if (!$this->should_hide()) {
            return $html;
        }

        return sprintf(
            '<a href="%s" class="button wb2b-login-button">%s</a>',
            esc_url($this->login_url()),
            esc_html__('Log in to see prices', 'woo-b2b')
        );
    }

    /**
     * Render a login button on the single product page, where a non-purchasable product would
     * otherwise show no Add to Cart form at all.
     */
    public function render_single_login_button() {
        if (!$this->should_hide()) {
            return;
        }

        printf(
            '<p class="wb2b-single-login"><a href="%s" class="button wb2b-login-button">%s</a></p>',
            esc_url($this->login_url()),
            esc_html__('Log in to see prices', 'woo-b2b')
        );
    }
}
