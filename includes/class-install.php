<?php
/**
 * Install Class
 *
 * Handles activation, deactivation, default options, and the auth page.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Install {

    /**
     * Default plugin options.
     *
     * @return array
     */
    public static function default_options() {
        return [
            'wb2b_enabled'           => true,
            'wb2b_auth_page_id'      => 0,
            'wb2b_allowed_pages'     => [],
            'wb2b_auto_approve'      => false,
            'wb2b_countries'         => ['CH', 'LI'],
            'wb2b_require_documents' => false,
            'wb2b_doc_max_mb'        => 10,
            'wb2b_doc_mimes'         => ['pdf', 'jpg', 'jpeg', 'png'],
            'wb2b_admin_email'       => get_option('admin_email'),
            'wb2b_min_password'      => 8,
        ];
    }

    /**
     * Activation.
     */
    public static function activate() {
        // Set default options (without overwriting existing values).
        foreach (self::default_options() as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        // Create the audit log table.
        if (class_exists('WB2B_Logs')) {
            WB2B_Logs::create_table();
        }

        // Create the auth page that hosts the login/register forms.
        self::maybe_create_auth_page();

        // Grandfather existing accounts so the gate doesn't lock them out.
        self::backfill_existing_users();

        flush_rewrite_rules();
    }

    /**
     * Mark existing users (without a status yet) as approved on activation, so
     * accounts created before the B2B gate was enabled keep their access.
     */
    public static function backfill_existing_users() {
        $user_ids = get_users([
            'fields'       => 'ID',
            'meta_query'   => [
                [
                    'key'     => WB2B_Customer::META_STATUS,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        foreach ($user_ids as $user_id) {
            update_user_meta($user_id, WB2B_Customer::META_STATUS, WB2B_Customer::STATUS_APPROVED);
        }
    }

    /**
     * Deactivation.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Create the auth page if it does not already exist.
     */
    public static function maybe_create_auth_page() {
        $page_id = (int) get_option('wb2b_auth_page_id', 0);

        // Already linked to a published/draft page.
        if ($page_id && get_post($page_id) && get_post_status($page_id) !== 'trash') {
            return $page_id;
        }

        // Look for an existing page that already contains the shortcode.
        $existing = get_posts([
            'post_type'      => 'page',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'numberposts'    => 1,
            'fields'         => 'ids',
            's'              => '[woo_b2b_auth]',
        ]);

        if (!empty($existing)) {
            update_option('wb2b_auth_page_id', (int) $existing[0]);
            return (int) $existing[0];
        }

        $page_id = wp_insert_post([
            'post_title'     => __('Account', 'woo-b2b'),
            'post_name'      => 'b2b-account',
            'post_content'   => '<!-- wp:shortcode -->[woo_b2b_auth]<!-- /wp:shortcode -->',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ]);

        if ($page_id && !is_wp_error($page_id)) {
            update_option('wb2b_auth_page_id', (int) $page_id);
            return (int) $page_id;
        }

        return 0;
    }
}
