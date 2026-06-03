<?php
/**
 * Uninstall Woo B2B
 *
 * Removes plugin options, the audit log table, and the auth page.
 * Customer accounts and their meta are intentionally left untouched.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin options.
$options = [
    'wb2b_enabled',
    'wb2b_auth_page_id',
    'wb2b_allowed_pages',
    'wb2b_auto_approve',
    'wb2b_countries',
    'wb2b_require_documents',
    'wb2b_doc_max_mb',
    'wb2b_doc_mimes',
    'wb2b_admin_email',
    'wb2b_min_password',
    'wb2b_db_version',
];

foreach ($options as $option) {
    delete_option($option);
}

// Trash the auth page if it still exists.
$auth_page_id = (int) get_option('wb2b_auth_page_id', 0);
if ($auth_page_id) {
    wp_delete_post($auth_page_id, false);
}

// Drop the audit log table.
$table = $wpdb->prefix . 'wb2b_logs';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
