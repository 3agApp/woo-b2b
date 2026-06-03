<?php
/**
 * Ajax Class
 *
 * Admin approve / reject actions for the approvals screen.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Ajax {

    public function __construct() {
        add_action('wp_ajax_wb2b_approve', [$this, 'approve']);
        add_action('wp_ajax_wb2b_reject', [$this, 'reject']);
    }

    /**
     * Verify nonce and capability for admin AJAX.
     */
    protected function verify() {
        if (!check_ajax_referer('wb2b_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'woo-b2b')]);
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'woo-b2b')]);
        }
    }

    /**
     * Approve a customer.
     */
    public function approve() {
        $this->verify();

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(['message' => __('Invalid user.', 'woo-b2b')]);
        }

        WB2B_Customer::set_status($user_id, WB2B_Customer::STATUS_APPROVED);
        WB2B()->emails->send_customer_approved($user_id);

        wp_send_json_success([
            'message' => __('Customer approved.', 'woo-b2b'),
            'status'  => WB2B_Customer::STATUS_APPROVED,
            'label'   => WB2B_Customer::get_status_label(WB2B_Customer::STATUS_APPROVED),
        ]);
    }

    /**
     * Reject a customer.
     */
    public function reject() {
        $this->verify();

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $reason  = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(['message' => __('Invalid user.', 'woo-b2b')]);
        }

        WB2B_Customer::set_status($user_id, WB2B_Customer::STATUS_REJECTED, $reason);
        WB2B()->emails->send_customer_rejected($user_id, $reason);

        wp_send_json_success([
            'message' => __('Customer rejected.', 'woo-b2b'),
            'status'  => WB2B_Customer::STATUS_REJECTED,
            'label'   => WB2B_Customer::get_status_label(WB2B_Customer::STATUS_REJECTED),
        ]);
    }
}
