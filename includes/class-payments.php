<?php
/**
 * Payments Class
 *
 * Registers the Pay by Invoice gateway with WooCommerce and restricts it to approved B2B
 * customers. Kept separate from the gateway class itself because WooCommerce instantiates the
 * gateway only after the `woocommerce_payment_gateways` filter runs — so registration must live
 * outside the gateway (it is referenced by class-name string, never instantiated here).
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Payments {

    public function __construct() {
        add_filter('woocommerce_payment_gateways', [$this, 'register_gateway']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_available_gateways']);
    }

    /**
     * Add the invoice gateway to WooCommerce's gateway list.
     *
     * @param array $gateways Registered gateway class names.
     * @return array
     */
    public function register_gateway($gateways) {
        $gateways[] = 'WB2B_Gateway_Invoice';
        return $gateways;
    }

    /**
     * Hide the invoice gateway from anyone who is not an approved B2B customer.
     *
     * The storefront is already login-gated, so in practice everyone reaching checkout is
     * approved — this is a defensive backstop. Admin order screens are left untouched.
     *
     * @param array $gateways Available gateways keyed by id.
     * @return array
     */
    public function filter_available_gateways($gateways) {
        if (is_admin() && !wp_doing_ajax()) {
            return $gateways;
        }

        if (!isset($gateways[WB2B_Gateway_Invoice::ID])) {
            return $gateways;
        }

        if (!WB2B_Customer::has_access(get_current_user_id())) {
            unset($gateways[WB2B_Gateway_Invoice::ID]);
        }

        return $gateways;
    }
}
