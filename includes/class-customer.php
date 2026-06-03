<?php
/**
 * Customer Class
 *
 * B2B customer data model: approval status, meta keys, and account creation
 * with WooCommerce billing/shipping mapping.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Customer {

    /**
     * Approval statuses.
     */
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Meta keys.
     */
    const META_STATUS    = 'wb2b_status';
    const META_CHANGED   = 'wb2b_status_changed';
    const META_REASON    = 'wb2b_rejection_reason';
    const META_REGISTERED = 'wb2b_registered_at';

    public function __construct() {
        // Auto-approve accounts created from wp-admin so the gate doesn't lock out
        // admin-created customers. Front-end registrations set their own status.
        add_action('user_register', [$this, 'maybe_set_default_status'], 20);
    }

    /**
     * Available statuses with labels.
     *
     * @return array
     */
    public static function statuses() {
        return [
            self::STATUS_PENDING  => __('Pending', 'woo-b2b'),
            self::STATUS_APPROVED => __('Approved', 'woo-b2b'),
            self::STATUS_REJECTED => __('Rejected', 'woo-b2b'),
        ];
    }

    /**
     * Human label for a status.
     *
     * @param string $status
     * @return string
     */
    public static function get_status_label($status) {
        $statuses = self::statuses();
        return isset($statuses[$status]) ? $statuses[$status] : ucfirst((string) $status);
    }

    /**
     * Salutation options (value => label).
     *
     * @return array
     */
    public static function get_salutations() {
        return [
            'none' => __('Not specified', 'woo-b2b'),
            'mrs'  => __('Mrs', 'woo-b2b'),
            'mr'   => __('Mr', 'woo-b2b'),
        ];
    }

    /**
     * Get a user's approval status.
     *
     * @param int $user_id
     * @return string One of the STATUS_* constants, or '' if not set.
     */
    public static function get_status($user_id) {
        return (string) get_user_meta((int) $user_id, self::META_STATUS, true);
    }

    /**
     * Set a user's approval status.
     *
     * @param int    $user_id
     * @param string $status
     * @param string $reason  Optional rejection reason.
     * @return bool
     */
    public static function set_status($user_id, $status, $reason = '') {
        $user_id  = (int) $user_id;
        $statuses = self::statuses();

        if (!isset($statuses[$status])) {
            return false;
        }

        update_user_meta($user_id, self::META_STATUS, $status);
        update_user_meta($user_id, self::META_CHANGED, current_time('mysql', true));

        if ($status === self::STATUS_REJECTED) {
            update_user_meta($user_id, self::META_REASON, sanitize_textarea_field($reason));
        } else {
            delete_user_meta($user_id, self::META_REASON);
        }

        do_action('wb2b_status_changed', $user_id, $status, $reason);

        return true;
    }

    /**
     * Whether a user is allowed through the gate.
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_access($user_id) {
        if (!$user_id) {
            return false;
        }

        // Admins / shop managers always have access.
        if (user_can($user_id, 'manage_woocommerce')) {
            return true;
        }

        return self::get_status($user_id) === self::STATUS_APPROVED;
    }

    /**
     * Default status for accounts created outside of our registration form.
     *
     * @param int $user_id
     */
    public function maybe_set_default_status($user_id) {
        if (self::get_status($user_id) !== '') {
            return;
        }

        // Accounts created by an admin in wp-admin are trusted and auto-approved.
        if (is_admin() && current_user_can('create_users')) {
            self::set_status($user_id, self::STATUS_APPROVED);
        }
    }

    /**
     * Create a new B2B customer account (pending approval).
     *
     * @param array $data Validated registration data.
     * @return int|WP_Error User ID on success.
     */
    public function create_customer($data) {
        $email = sanitize_email($data['email']);

        if (email_exists($email)) {
            return new WP_Error('email_exists', __('An account with this email address already exists.', 'woo-b2b'));
        }

        $username = $this->generate_username($email);

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $data['password'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'display_name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'role'         => 'customer',
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // WooCommerce billing fields.
        $billing = [
            'billing_first_name' => $data['first_name'],
            'billing_last_name'  => $data['last_name'],
            'billing_company'    => $data['company'],
            'billing_address_1'  => $data['street'],
            'billing_address_2'  => $data['address_line2'],
            'billing_postcode'   => $data['postcode'],
            'billing_city'       => $data['city'],
            'billing_country'    => $data['country'],
            'billing_phone'      => $data['phone'],
            'billing_email'      => $email,
        ];
        foreach ($billing as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }

        // Plugin-specific billing meta.
        update_user_meta($user_id, 'wb2b_salutation', $data['salutation']);
        update_user_meta($user_id, 'wb2b_billing_department', $data['department']);
        update_user_meta($user_id, 'wb2b_vat_id', $data['vat_id']);

        // Shipping fields (only when a different shipping address was provided).
        if (!empty($data['different_shipping'])) {
            $shipping = [
                'shipping_first_name' => $data['shipping_first_name'],
                'shipping_last_name'  => $data['shipping_last_name'],
                'shipping_company'    => $data['shipping_company'],
                'shipping_address_1'  => $data['shipping_street'],
                'shipping_address_2'  => $data['shipping_address_line2'],
                'shipping_postcode'   => $data['shipping_postcode'],
                'shipping_city'       => $data['shipping_city'],
                'shipping_country'    => $data['shipping_country'],
                'shipping_phone'      => $data['shipping_phone'],
            ];
            foreach ($shipping as $key => $value) {
                update_user_meta($user_id, $key, $value);
            }
            update_user_meta($user_id, 'wb2b_shipping_salutation', $data['shipping_salutation']);
            update_user_meta($user_id, 'wb2b_shipping_department', $data['shipping_department']);
        }

        // Uploaded documents.
        if (!empty($data['documents'])) {
            $doc_ids = array_map('intval', (array) $data['documents']);
            update_user_meta($user_id, 'wb2b_documents', $doc_ids);

            // Attach the documents to this user so they are easy to trace.
            foreach ($doc_ids as $doc_id) {
                wp_update_post([
                    'ID'          => $doc_id,
                    'post_author' => $user_id,
                ]);
            }
        }

        // Approval status.
        $auto_approve = (bool) get_option('wb2b_auto_approve', false);
        $status       = $auto_approve ? self::STATUS_APPROVED : self::STATUS_PENDING;

        update_user_meta($user_id, self::META_REGISTERED, current_time('mysql', true));
        self::set_status($user_id, $status);

        do_action('wb2b_customer_registered', $user_id, $data, $status);

        return $user_id;
    }

    /**
     * Build a profile array for admin display.
     *
     * @param int $user_id
     * @return array
     */
    public static function get_profile($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $get = function ($key) use ($user_id) {
            return get_user_meta($user_id, $key, true);
        };

        return [
            'user_id'       => $user_id,
            'email'         => $user->user_email,
            'salutation'    => $get('wb2b_salutation'),
            'first_name'    => $get('billing_first_name') ?: $user->first_name,
            'last_name'     => $get('billing_last_name') ?: $user->last_name,
            'company'       => $get('billing_company'),
            'department'    => $get('wb2b_billing_department'),
            'vat_id'        => $get('wb2b_vat_id'),
            'street'        => $get('billing_address_1'),
            'address_line2' => $get('billing_address_2'),
            'postcode'      => $get('billing_postcode'),
            'city'          => $get('billing_city'),
            'country'       => $get('billing_country'),
            'phone'         => $get('billing_phone'),
            'documents'     => (array) $get('wb2b_documents'),
            'status'        => self::get_status($user_id),
            'registered_at' => $get(self::META_REGISTERED),
            'reason'        => $get(self::META_REASON),
        ];
    }

    /**
     * Count customers by status.
     *
     * @return array Map of status => count.
     */
    public static function get_status_counts() {
        $counts = [];
        foreach (array_keys(self::statuses()) as $status) {
            $query = new WP_User_Query([
                'meta_key'    => self::META_STATUS,
                'meta_value'  => $status,
                'number'      => 0,
                'count_total' => true,
                'fields'      => 'ID',
            ]);
            $counts[$status] = (int) $query->get_total();
        }
        return $counts;
    }

    /**
     * Generate a unique username from an email address.
     *
     * @param string $email
     * @return string
     */
    private function generate_username($email) {
        $base = sanitize_user(current(explode('@', $email)), true);
        $base = $base !== '' ? $base : 'customer';

        $username = $base;
        $i        = 1;
        while (username_exists($username)) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }
}
