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
        add_action('wp_ajax_wb2b_bulk', [$this, 'bulk']);
        add_action('wp_ajax_wb2b_save_settings', [$this, 'save_settings']);

        // License + updates.
        add_action('wp_ajax_wb2b_activate_license', [$this, 'activate_license']);
        add_action('wp_ajax_wb2b_activate_domain', [$this, 'activate_domain']);
        add_action('wp_ajax_wb2b_deactivate_license', [$this, 'deactivate_license']);
        add_action('wp_ajax_wb2b_check_license', [$this, 'check_license']);
        add_action('wp_ajax_wb2b_check_update', [$this, 'check_update']);
        add_action('wp_ajax_wb2b_install_update', [$this, 'install_update']);
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

    /**
     * Bulk approve / reject customers.
     */
    public function bulk() {
        $this->verify();

        $do     = isset($_POST['do']) ? sanitize_key($_POST['do']) : '';
        $ids    = isset($_POST['user_ids']) ? array_map('absint', (array) wp_unslash($_POST['user_ids'])) : [];
        $ids    = array_values(array_filter(array_unique($ids)));
        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        if (!in_array($do, ['approve', 'reject'], true) || empty($ids)) {
            wp_send_json_error(['message' => __('Nothing to do.', 'woo-b2b')]);
        }

        $status = $do === 'approve' ? WB2B_Customer::STATUS_APPROVED : WB2B_Customer::STATUS_REJECTED;
        $done   = 0;

        foreach ($ids as $user_id) {
            if (!get_userdata($user_id)) {
                continue;
            }
            WB2B_Customer::set_status($user_id, $status, $reason);
            if ($do === 'approve') {
                WB2B()->emails->send_customer_approved($user_id);
            } else {
                WB2B()->emails->send_customer_rejected($user_id, $reason);
            }
            $done++;
        }

        wp_send_json_success([
            /* translators: %d: number of customers updated */
            'message' => sprintf(_n('%d customer updated.', '%d customers updated.', $done, 'woo-b2b'), $done),
            'ids'     => $ids,
        ]);
    }

    /**
     * Save the settings form via AJAX. Reuses the sanitizer callbacks on WB2B_Admin.
     */
    public function save_settings() {
        $this->verify();

        $admin = WB2B()->admin;
        if (!$admin) {
            wp_send_json_error(['message' => __('Settings handler unavailable.', 'woo-b2b')]);
        }

        $post = wp_unslash($_POST);

        update_option('wb2b_access_mode', $admin->sanitize_access_mode(isset($post['wb2b_access_mode']) ? $post['wb2b_access_mode'] : 'redirect'));
        update_option('wb2b_auto_approve', $admin->sanitize_bool(isset($post['wb2b_auto_approve']) ? $post['wb2b_auto_approve'] : 0));
        update_option('wb2b_require_documents', $admin->sanitize_bool(isset($post['wb2b_require_documents']) ? $post['wb2b_require_documents'] : 0));
        update_option('wb2b_auth_page_id', isset($post['wb2b_auth_page_id']) ? absint($post['wb2b_auth_page_id']) : 0);
        update_option('wb2b_doc_max_mb', isset($post['wb2b_doc_max_mb']) ? absint($post['wb2b_doc_max_mb']) : 10);
        update_option('wb2b_min_password', $admin->sanitize_min_password(isset($post['wb2b_min_password']) ? $post['wb2b_min_password'] : 8));
        update_option('wb2b_admin_email', sanitize_email(isset($post['wb2b_admin_email']) ? $post['wb2b_admin_email'] : ''));
        update_option('wb2b_allowed_pages', $admin->sanitize_id_array(isset($post['wb2b_allowed_pages']) ? $post['wb2b_allowed_pages'] : []));
        update_option('wb2b_countries', $admin->sanitize_countries(isset($post['wb2b_countries']) ? $post['wb2b_countries'] : []));
        update_option('wb2b_doc_mimes', $admin->sanitize_mimes(isset($post['wb2b_doc_mimes']) ? $post['wb2b_doc_mimes'] : []));
        update_option('wb2b_auth_ui_style', $admin->sanitize_ui_style(isset($post['wb2b_auth_ui_style']) ? $post['wb2b_auth_ui_style'] : 'theme'));

        wp_send_json_success(['message' => __('Settings saved.', 'woo-b2b')]);
    }

    /* ----- License ----- */

    /**
     * Activate a license key.
     */
    public function activate_license() {
        $this->verify();

        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        if ($license_key === '') {
            wp_send_json_error(['message' => __('Please enter a license key.', 'woo-b2b')]);
        }

        $result = WB2B()->license->activate($license_key);

        if (!empty($result['success'])) {
            wp_send_json_success([
                'message' => __('License activated successfully!', 'woo-b2b'),
                'reload'  => true,
            ]);
        }

        wp_send_json_error([
            'message' => isset($result['message']) ? $result['message'] : __('Activation failed.', 'woo-b2b'),
        ]);
    }

    /**
     * Activate the current domain for an already-stored license.
     */
    public function activate_domain() {
        $this->verify();

        $license_key = WB2B()->license->get_key();
        if (empty($license_key)) {
            wp_send_json_error(['message' => __('No license key found. Please enter a license key.', 'woo-b2b')]);
        }

        $result = WB2B()->license->activate($license_key);

        if (!empty($result['success'])) {
            wp_send_json_success([
                'message' => __('Domain activated successfully!', 'woo-b2b'),
                'reload'  => true,
            ]);
        }

        wp_send_json_error([
            'message' => isset($result['message']) ? $result['message'] : __('Activation failed.', 'woo-b2b'),
        ]);
    }

    /**
     * Deactivate the license on this domain.
     */
    public function deactivate_license() {
        $this->verify();

        WB2B()->license->deactivate();

        wp_send_json_success([
            'message' => __('License deactivated.', 'woo-b2b'),
            'reload'  => true,
        ]);
    }

    /**
     * Re-validate the stored license.
     */
    public function check_license() {
        $this->verify();

        $result = WB2B()->license->validate();

        if (!empty($result['success']) && isset($result['data'])) {
            wp_send_json_success([
                'message'   => __('License verified.', 'woo-b2b'),
                'valid'     => !empty($result['data']['valid']),
                'activated' => !empty($result['data']['activated']),
                'reload'    => true,
            ]);
        }

        wp_send_json_error([
            'message' => isset($result['message']) ? $result['message'] : __('License check failed.', 'woo-b2b'),
        ]);
    }

    /* ----- Updates ----- */

    /**
     * Check GitHub for a newer release.
     */
    public function check_update() {
        $this->verify();

        WB2B()->updater->force_check();

        $update_data     = get_transient('wb2b_update_data');
        $current_version = WB2B_VERSION;
        $has_update      = $update_data && !empty($update_data['version']) && version_compare($current_version, $update_data['version'], '<');

        if ($has_update) {
            wp_send_json_success([
                /* translators: %s: new version */
                'message'     => sprintf(__('Update available! Version %s is ready to install.', 'woo-b2b'), $update_data['version']),
                'has_update'  => true,
                'new_version' => $update_data['version'],
                'reload'      => true,
            ]);
        }

        wp_send_json_success([
            'message'    => __('You are running the latest version.', 'woo-b2b'),
            'has_update' => false,
        ]);
    }

    /**
     * Download and install the latest release.
     */
    public function install_update() {
        $this->verify();

        $update_data = get_transient('wb2b_update_data');
        if (!$update_data || empty($update_data['download_url'])) {
            wp_send_json_error(['message' => __('No update available or download URL missing.', 'woo-b2b')]);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        deactivate_plugins(WB2B_PLUGIN_BASENAME);

        $result = $upgrader->install($update_data['download_url'], ['overwrite_package' => true]);

        if (is_wp_error($result) || $result === false) {
            activate_plugin(WB2B_PLUGIN_BASENAME);
            $message = is_wp_error($result) ? $result->get_error_message() : __('Update failed. Please try again or update manually.', 'woo-b2b');
            wp_send_json_error(['message' => $message]);
        }

        activate_plugin(WB2B_PLUGIN_BASENAME);

        WB2B()->updater->clear_cache();
        delete_site_transient('update_plugins');

        wp_send_json_success([
            /* translators: %s: new version */
            'message'     => sprintf(__('Successfully updated to version %s. Please refresh the page.', 'woo-b2b'), $update_data['version']),
            'new_version' => $update_data['version'],
            'reload'      => true,
        ]);
    }
}
