<?php
/**
 * Logs Class
 *
 * Lightweight audit trail for registrations and approval decisions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Logs {

    /**
     * Database schema version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Maximum rows to keep.
     */
    const MAX_LOGS = 500;

    /**
     * Table name.
     */
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wb2b_logs';
        $this->maybe_create_table();

        add_action('wb2b_customer_registered', [$this, 'log_registration'], 10, 3);
        add_action('wb2b_status_changed', [$this, 'log_status_change'], 10, 3);
    }

    /**
     * Create or upgrade the table if needed.
     */
    private function maybe_create_table() {
        global $wpdb;

        $installed = get_option('wb2b_db_version', '0');
        $exists    = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name));

        if ($exists !== $this->table_name || version_compare($installed, self::DB_VERSION, '<')) {
            self::create_table();
            update_option('wb2b_db_version', self::DB_VERSION);
        }
    }

    /**
     * Create the logs table.
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'wb2b_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            type varchar(50) NOT NULL DEFAULT 'event',
            message text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Add a log entry.
     *
     * @param array $data
     * @return int|false
     */
    public function add($data) {
        global $wpdb;

        $defaults = [
            'user_id'  => 0,
            'actor_id' => get_current_user_id(),
            'type'     => 'event',
            'message'  => '',
        ];
        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $this->table_name,
            [
                'user_id'    => (int) $data['user_id'],
                'actor_id'   => (int) $data['actor_id'],
                'type'       => sanitize_text_field($data['type']),
                'message'    => sanitize_textarea_field($data['message']),
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        $this->cleanup();

        return $wpdb->insert_id;
    }

    /**
     * Get log entries.
     *
     * @param array $args
     * @return array
     */
    public function get($args = []) {
        global $wpdb;

        $args = wp_parse_args($args, [
            'user_id' => null,
            'type'    => null,
            'limit'   => 50,
            'offset'  => 0,
        ]);

        $where  = ['1=1'];
        $values = [];

        if ($args['user_id'] !== null) {
            $where[]  = 'user_id = %d';
            $values[] = (int) $args['user_id'];
        }
        if ($args['type'] !== null) {
            $where[]  = 'type = %s';
            $values[] = $args['type'];
        }

        $where_clause = implode(' AND ', $where);
        $sql          = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[]     = (int) $args['limit'];
        $values[]     = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Count log entries.
     *
     * @return int
     */
    public function get_count() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * Log a new registration.
     *
     * @param int    $user_id
     * @param array  $data
     * @param string $status
     */
    public function log_registration($user_id, $data, $status) {
        $company = isset($data['company']) ? $data['company'] : '';
        $this->add([
            'user_id'  => $user_id,
            'actor_id' => 0,
            'type'     => 'registration',
            /* translators: %s: company name */
            'message'  => sprintf(__('New registration from %s', 'woo-b2b'), $company),
        ]);
    }

    /**
     * Log an approval / rejection decision.
     *
     * @param int    $user_id
     * @param string $status
     * @param string $reason
     */
    public function log_status_change($user_id, $status, $reason) {
        // Skip the initial pending state set during registration.
        if ($status === WB2B_Customer::STATUS_PENDING) {
            return;
        }

        $message = sprintf(
            /* translators: %s: status label */
            __('Status changed to %s', 'woo-b2b'),
            WB2B_Customer::get_status_label($status)
        );

        if ($status === WB2B_Customer::STATUS_REJECTED && $reason !== '') {
            $message .= ' — ' . $reason;
        }

        $this->add([
            'user_id' => $user_id,
            'type'    => $status === WB2B_Customer::STATUS_APPROVED ? 'approval' : 'rejection',
            'message' => $message,
        ]);
    }

    /**
     * Trim old rows.
     */
    private function cleanup() {
        global $wpdb;

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        if ($count > self::MAX_LOGS) {
            $delete = $count - self::MAX_LOGS;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} ORDER BY created_at ASC LIMIT %d",
                $delete
            ));
        }
    }
}
