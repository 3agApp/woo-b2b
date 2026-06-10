<?php
/**
 * Admin Class
 *
 * Admin menu, settings, the approvals screen, and Users-list integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Admin {

    /**
     * Menu slug.
     */
    const MENU_SLUG = 'woo-b2b';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);

        // Users-list integration.
        add_filter('manage_users_columns', [$this, 'add_user_column']);
        add_filter('manage_users_custom_column', [$this, 'render_user_column'], 10, 3);
        add_filter('user_row_actions', [$this, 'user_row_actions'], 10, 2);
        add_action('admin_post_wb2b_user_action', [$this, 'handle_user_row_action']);
    }

    /**
     * Register the admin menu.
     */
    public function add_menu() {
        $counts  = WB2B_Customer::get_status_counts();
        $pending = isset($counts[WB2B_Customer::STATUS_PENDING]) ? (int) $counts[WB2B_Customer::STATUS_PENDING] : 0;

        $menu_title = __('B2B Customers', 'woo-b2b');
        if ($pending > 0) {
            $menu_title .= ' <span class="awaiting-mod">' . esc_html($pending) . '</span>';
        }

        add_menu_page(
            __('B2B Customers', 'woo-b2b'),
            $menu_title,
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render_approvals_page'],
            'dashicons-groups',
            56
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Customers', 'woo-b2b'),
            __('Customers', 'woo-b2b'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render_approvals_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'woo-b2b'),
            __('Settings', 'woo-b2b'),
            'manage_woocommerce',
            self::MENU_SLUG . '-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('License', 'woo-b2b'),
            __('License', 'woo-b2b'),
            'manage_woocommerce',
            self::MENU_SLUG . '-license',
            [$this, 'render_license_page']
        );
    }

    /**
     * Enqueue admin assets on our pages and the Users list.
     */
    public function enqueue_assets($hook) {
        $on_our_page = (strpos($hook, self::MENU_SLUG) !== false) || ($hook === 'users.php');
        if (!$on_our_page) {
            return;
        }

        $css = WB2B_PLUGIN_DIR . 'assets/css/admin.css';
        $js  = WB2B_PLUGIN_DIR . 'assets/js/admin.js';

        wp_enqueue_style('wb2b-admin', WB2B_PLUGIN_URL . 'assets/css/admin.css', [], file_exists($css) ? filemtime($css) : WB2B_VERSION);
        wp_enqueue_script('wb2b-admin', WB2B_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], file_exists($js) ? filemtime($js) : WB2B_VERSION, true);

        wp_localize_script('wb2b-admin', 'wb2b_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wb2b_admin_nonce'),
            'strings'  => [
                'confirm_approve'    => __('Approve this customer?', 'woo-b2b'),
                'reject_prompt'      => __('Reason for rejection (optional, included in the email):', 'woo-b2b'),
                'working'            => __('Working…', 'woo-b2b'),
                'error'              => __('Something went wrong. Please try again.', 'woo-b2b'),
                'confirm_deactivate' => __('Deactivate the license on this domain?', 'woo-b2b'),
                'confirm_install'    => __('Download and install the update now? The plugin will briefly deactivate.', 'woo-b2b'),
                // Modal chrome + action button labels.
                'cancel'             => __('Cancel', 'woo-b2b'),
                'confirm'            => __('Confirm', 'woo-b2b'),
                'approve_title'      => __('Approve customer', 'woo-b2b'),
                'approve'            => __('Approve', 'woo-b2b'),
                'reject_title'       => __('Reject customer', 'woo-b2b'),
                'reject'             => __('Reject', 'woo-b2b'),
                'deactivate_title'   => __('Deactivate license', 'woo-b2b'),
                'deactivate'         => __('Deactivate', 'woo-b2b'),
                'update_title'       => __('Install update', 'woo-b2b'),
                'update'             => __('Update now', 'woo-b2b'),
                'bulk_approve_confirm' => __('Approve %d selected customer(s)?', 'woo-b2b'),
            ],
        ]);
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting('wb2b_settings', 'wb2b_enabled', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_bool'], 'default' => true]);
        register_setting('wb2b_settings', 'wb2b_auto_approve', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_bool'], 'default' => false]);
        register_setting('wb2b_settings', 'wb2b_require_documents', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_bool'], 'default' => false]);
        register_setting('wb2b_settings', 'wb2b_auth_page_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        register_setting('wb2b_settings', 'wb2b_doc_max_mb', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 10]);
        register_setting('wb2b_settings', 'wb2b_min_password', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitize_min_password'], 'default' => 8]);
        register_setting('wb2b_settings', 'wb2b_admin_email', ['type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_option('admin_email')]);
        register_setting('wb2b_settings', 'wb2b_allowed_pages', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_id_array'], 'default' => []]);
        register_setting('wb2b_settings', 'wb2b_countries', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_countries'], 'default' => ['CH', 'LI']]);
        register_setting('wb2b_settings', 'wb2b_doc_mimes', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_mimes'], 'default' => ['pdf', 'jpg', 'jpeg', 'png']]);
        register_setting('wb2b_settings', 'wb2b_auth_ui_style', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_ui_style'], 'default' => 'theme']);
    }

    /* ----- Sanitizers ----- */

    public function sanitize_bool($value) {
        return (bool) $value;
    }

    public function sanitize_min_password($value) {
        return max(4, absint($value));
    }

    public function sanitize_id_array($value) {
        $value = is_array($value) ? $value : [];
        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    public function sanitize_countries($value) {
        $value = is_array($value) ? $value : [];
        $valid = (function_exists('WC') && WC()->countries) ? array_keys(WC()->countries->get_countries()) : [];

        $out = [];
        foreach ($value as $code) {
            $code = strtoupper(sanitize_text_field($code));
            if (preg_match('/^[A-Z]{2}$/', $code) && (empty($valid) || in_array($code, $valid, true))) {
                $out[] = $code;
            }
        }

        $out = array_values(array_unique($out));
        return !empty($out) ? $out : ['CH', 'LI'];
    }

    public function sanitize_mimes($value) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $value   = is_array($value) ? array_map('strtolower', $value) : [];
        $out     = array_values(array_intersect($allowed, $value));
        return !empty($out) ? $out : $allowed;
    }

    public function sanitize_ui_style($value) {
        $allowed = ['theme', 'default'];
        $value   = sanitize_text_field($value);
        return in_array($value, $allowed, true) ? $value : 'theme';
    }

    /* ----- Pages ----- */

    /**
     * Render the approvals page.
     */
    public function render_approvals_page() {
        $status   = isset($_GET['status']) ? sanitize_key($_GET['status']) : WB2B_Customer::STATUS_PENDING;
        $statuses = WB2B_Customer::statuses();
        if (!isset($statuses[$status])) {
            $status = WB2B_Customer::STATUS_PENDING;
        }

        // Filters.
        $search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $country = isset($_GET['country']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['country']))) : '';
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            $country = '';
        }
        $order = (isset($_GET['order']) && strtoupper(sanitize_key($_GET['order'])) === 'ASC') ? 'ASC' : 'DESC';

        $paged    = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;

        $meta_query = [['key' => WB2B_Customer::META_STATUS, 'value' => $status]];
        if ($country !== '') {
            $meta_query[]           = ['key' => 'billing_country', 'value' => $country];
            $meta_query['relation'] = 'AND';
        }

        $args = [
            'meta_query'  => $meta_query,
            'number'      => $per_page,
            'offset'      => ($paged - 1) * $per_page,
            'orderby'     => 'registered',
            'order'       => $order,
            'count_total' => true,
        ];
        if ($search !== '') {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'user_nicename', 'display_name'];
        }

        $query = new WP_User_Query($args);

        $users       = $query->get_results();
        $total       = $query->get_total();
        $total_pages = (int) ceil($total / $per_page);
        $counts      = WB2B_Customer::get_status_counts();

        // Country options for the filter (limited to the configured allowed countries).
        $wc_countries  = (function_exists('WC') && WC()->countries) ? WC()->countries->get_countries() : [];
        $country_opts  = [];
        foreach (array_map('strtoupper', (array) get_option('wb2b_countries', ['CH', 'LI'])) as $code) {
            $country_opts[$code] = isset($wc_countries[$code]) ? $wc_countries[$code] : $code;
        }

        include WB2B_PLUGIN_DIR . 'includes/views/admin-approvals.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $enabled           = (bool) get_option('wb2b_enabled', true);
        $auto_approve      = (bool) get_option('wb2b_auto_approve', false);
        $require_documents = (bool) get_option('wb2b_require_documents', false);
        $auth_page_id      = (int) get_option('wb2b_auth_page_id', 0);
        $allowed_pages     = (array) get_option('wb2b_allowed_pages', []);
        $countries         = (array) get_option('wb2b_countries', ['CH', 'LI']);
        $doc_mimes         = (array) get_option('wb2b_doc_mimes', ['pdf', 'jpg', 'jpeg', 'png']);
        $doc_max_mb        = (int) get_option('wb2b_doc_max_mb', 10);
        $min_password      = (int) get_option('wb2b_min_password', 8);
        $admin_email       = get_option('wb2b_admin_email', get_option('admin_email'));
        $auth_ui_style     = get_option('wb2b_auth_ui_style', 'theme');

        $all_countries = (function_exists('WC') && WC()->countries) ? WC()->countries->get_countries() : [];

        include WB2B_PLUGIN_DIR . 'includes/views/admin-settings.php';
    }

    /**
     * Render the License page.
     */
    public function render_license_page() {
        $license_key    = get_option('wb2b_license_key', '');
        $license_status = get_option('wb2b_license_status', '');
        $license_data   = WB2B()->license->get_data();
        $last_check     = get_option('wb2b_license_last_check');

        include WB2B_PLUGIN_DIR . 'includes/views/license.php';
    }

    /* ----- Users list ----- */

    public function add_user_column($columns) {
        $columns['wb2b_status'] = __('B2B status', 'woo-b2b');
        return $columns;
    }

    public function render_user_column($output, $column, $user_id) {
        if ($column !== 'wb2b_status') {
            return $output;
        }

        $status = WB2B_Customer::get_status($user_id);
        if ($status === '') {
            return '&mdash;';
        }

        return '<span class="wb2b-badge wb2b-badge--' . esc_attr($status) . '">' . esc_html(WB2B_Customer::get_status_label($status)) . '</span>';
    }

    public function user_row_actions($actions, $user) {
        if (!current_user_can('manage_woocommerce')) {
            return $actions;
        }

        $status = WB2B_Customer::get_status($user->ID);

        if ($status === WB2B_Customer::STATUS_PENDING || $status === WB2B_Customer::STATUS_REJECTED) {
            $actions['wb2b_approve'] = '<a href="' . esc_url($this->action_link($user->ID, 'approve')) . '">' . esc_html__('Approve (B2B)', 'woo-b2b') . '</a>';
        }
        if ($status === WB2B_Customer::STATUS_PENDING || $status === WB2B_Customer::STATUS_APPROVED) {
            $actions['wb2b_reject'] = '<a href="' . esc_url($this->action_link($user->ID, 'reject')) . '">' . esc_html__('Reject (B2B)', 'woo-b2b') . '</a>';
        }

        return $actions;
    }

    /**
     * Build a nonced row-action link.
     */
    protected function action_link($user_id, $do) {
        return wp_nonce_url(
            admin_url('admin-post.php?action=wb2b_user_action&do=' . $do . '&user=' . (int) $user_id),
            'wb2b_user_action_' . (int) $user_id
        );
    }

    /**
     * Handle the Users-list approve/reject row action.
     */
    public function handle_user_row_action() {
        $user_id = isset($_GET['user']) ? absint($_GET['user']) : 0;
        $do      = isset($_GET['do']) ? sanitize_key($_GET['do']) : '';

        if (!current_user_can('manage_woocommerce') || !$user_id) {
            wp_die(esc_html__('You are not allowed to do this.', 'woo-b2b'), 403);
        }

        check_admin_referer('wb2b_user_action_' . $user_id);

        if ($do === 'approve') {
            WB2B_Customer::set_status($user_id, WB2B_Customer::STATUS_APPROVED);
            WB2B()->emails->send_customer_approved($user_id);
        } elseif ($do === 'reject') {
            WB2B_Customer::set_status($user_id, WB2B_Customer::STATUS_REJECTED);
            WB2B()->emails->send_customer_rejected($user_id);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url('users.php'));
        exit;
    }

    /**
     * Admin page URL helper.
     */
    public static function get_page_url($page = '') {
        $slug = self::MENU_SLUG;
        if ($page) {
            $slug .= '-' . $page;
        }
        return admin_url('admin.php?page=' . $slug);
    }
}
