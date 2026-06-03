<?php
/**
 * License Management Class
 *
 * Handles license validation, activation, deactivation, and status checks
 * using the 3AG License API v3.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_License {

    const API_URL      = 'https://3ag.app/api/v3';
    const PRODUCT_SLUG = 'woo-b2b';

    const OPTION_LICENSE_KEY    = 'wb2b_license_key';
    const OPTION_LICENSE_STATUS = 'wb2b_license_status';
    const OPTION_LICENSE_DATA   = 'wb2b_license_data';
    const OPTION_LAST_CHECK     = 'wb2b_license_last_check';

    public function __construct() {
        add_action('wb2b_license_check', [$this, 'daily_check']);
        add_action('admin_notices', [$this, 'maybe_show_notice']);

        if (!wp_next_scheduled('wb2b_license_check')) {
            wp_schedule_event(time(), 'daily', 'wb2b_license_check');
        }
    }

    /**
     * Current site domain (normalised).
     *
     * @return string
     */
    private function get_domain() {
        return wb2b_get_domain();
    }

    /**
     * Make an API request to the license server.
     *
     * @param string $endpoint
     * @param array  $body
     * @return array
     */
    private function api_request($endpoint, $body) {
        $response = wp_remote_post(self::API_URL . $endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $code          = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data          = json_decode($response_body, true);

        if ($code === 204) {
            return [
                'success' => true,
                'message' => __('Operation successful.', 'woo-b2b'),
            ];
        }

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'data'    => isset($data['data']) ? $data['data'] : $data,
            ];
        }

        return [
            'success' => false,
            'message' => isset($data['message']) ? $data['message'] : __('Unknown error occurred.', 'woo-b2b'),
            'errors'  => isset($data['errors']) ? $data['errors'] : [],
        ];
    }

    /**
     * Validate the stored (or given) license key.
     *
     * @param string|null $license_key
     * @return array
     */
    public function validate($license_key = null) {
        if (!$license_key) {
            $license_key = get_option(self::OPTION_LICENSE_KEY);
        }

        if (!$license_key) {
            return [
                'success' => false,
                'message' => __('No license key found.', 'woo-b2b'),
            ];
        }

        $result = $this->api_request('/licenses/validate', [
            'license_key'  => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain'       => $this->get_domain(),
        ]);

        if ($result['success'] && isset($result['data'])) {
            update_option(self::OPTION_LAST_CHECK, time());

            $is_valid     = !empty($result['data']['valid']);
            $is_activated = !empty($result['data']['activated']);

            if ($is_valid && $is_activated) {
                update_option(self::OPTION_LICENSE_STATUS, 'active');
                update_option(self::OPTION_LICENSE_DATA, $result['data']);
            } elseif ($is_valid && !$is_activated) {
                update_option(self::OPTION_LICENSE_STATUS, 'not_activated');
                update_option(self::OPTION_LICENSE_DATA, $result['data']);
            } else {
                update_option(self::OPTION_LICENSE_STATUS, 'invalid');
                update_option(self::OPTION_LICENSE_DATA, $result['data']);
            }
        } elseif (!$result['success']) {
            update_option(self::OPTION_LICENSE_STATUS, 'invalid');
            delete_option(self::OPTION_LICENSE_DATA);
        }

        return $result;
    }

    /**
     * Activate a license key on this domain.
     *
     * @param string $license_key
     * @return array
     */
    public function activate($license_key) {
        if (empty($license_key)) {
            return [
                'success' => false,
                'message' => __('License key is required.', 'woo-b2b'),
            ];
        }

        $result = $this->api_request('/licenses/activate', [
            'license_key'  => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain'       => $this->get_domain(),
        ]);

        if ($result['success'] && isset($result['data'])) {
            update_option(self::OPTION_LICENSE_KEY, $license_key);
            update_option(self::OPTION_LICENSE_STATUS, 'active');
            update_option(self::OPTION_LICENSE_DATA, $result['data']);
            update_option(self::OPTION_LAST_CHECK, time());
        }

        return $result;
    }

    /**
     * Deactivate the license on this domain and clear local data.
     *
     * @param string|null $license_key
     * @return array
     */
    public function deactivate($license_key = null) {
        if (!$license_key) {
            $license_key = get_option(self::OPTION_LICENSE_KEY);
        }

        if (!$license_key) {
            return [
                'success' => false,
                'message' => __('No license key found.', 'woo-b2b'),
            ];
        }

        $result = $this->api_request('/licenses/deactivate', [
            'license_key'  => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain'       => $this->get_domain(),
        ]);

        $this->clear_local_data();

        return $result;
    }

    /**
     * Remove all locally stored license data.
     */
    private function clear_local_data() {
        delete_option(self::OPTION_LICENSE_KEY);
        delete_option(self::OPTION_LICENSE_STATUS);
        delete_option(self::OPTION_LICENSE_DATA);
        delete_option(self::OPTION_LAST_CHECK);
    }

    /**
     * Daily verification of the stored license.
     */
    public function daily_check() {
        $license_key = get_option(self::OPTION_LICENSE_KEY);
        if (!$license_key) {
            return;
        }

        $this->validate($license_key);
    }

    /**
     * Whether the license is valid and activated on this domain.
     *
     * @return bool
     */
    public function is_valid() {
        $status      = get_option(self::OPTION_LICENSE_STATUS);
        $license_key = get_option(self::OPTION_LICENSE_KEY);

        return !empty($license_key) && $status === 'active';
    }

    /**
     * Whether the license is valid but not yet activated on this domain.
     *
     * @return bool
     */
    public function needs_activation() {
        return get_option(self::OPTION_LICENSE_STATUS) === 'not_activated';
    }

    public function get_data() {
        return get_option(self::OPTION_LICENSE_DATA, []);
    }

    public function get_key() {
        return get_option(self::OPTION_LICENSE_KEY, false);
    }

    public function get_status() {
        return get_option(self::OPTION_LICENSE_STATUS, '');
    }

    public function get_last_check() {
        return get_option(self::OPTION_LAST_CHECK, false);
    }

    public function get_expiry() {
        $data = $this->get_data();
        return isset($data['expires_at']) ? $data['expires_at'] : null;
    }

    public function is_expired() {
        $expires_at = $this->get_expiry();
        if (!$expires_at) {
            return false;
        }
        return strtotime($expires_at) < time();
    }

    public function get_remaining_days() {
        $expires_at = $this->get_expiry();
        if (!$expires_at) {
            return null;
        }
        $diff = strtotime($expires_at) - time();
        return max(0, (int) floor($diff / DAY_IN_SECONDS));
    }

    public function get_activations() {
        $data = $this->get_data();
        return isset($data['activations']) ? $data['activations'] : ['limit' => 0, 'used' => 0];
    }

    public function get_product_name() {
        $data = $this->get_data();
        return isset($data['product']) ? $data['product'] : '';
    }

    public function get_package() {
        $data = $this->get_data();
        return isset($data['package']) ? $data['package'] : '';
    }

    public function get_api_status() {
        $data = $this->get_data();
        return isset($data['status']) ? $data['status'] : '';
    }

    /**
     * Show an admin notice on the plugin's own pages when not licensed.
     */
    public function maybe_show_notice() {
        if ($this->is_valid()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos((string) $screen->id, 'woo-b2b') === false) {
            return;
        }

        // Don't nag on the License page itself.
        if (isset($_GET['page']) && $_GET['page'] === 'woo-b2b-license') {
            return;
        }

        $url = admin_url('admin.php?page=woo-b2b-license');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Woo B2B is not licensed.', 'woo-b2b'); ?></strong>
                <?php esc_html_e('Activate your license to receive automatic updates and support.', 'woo-b2b'); ?>
                <a href="<?php echo esc_url($url); ?>"><?php esc_html_e('Enter license key', 'woo-b2b'); ?></a>
            </p>
        </div>
        <?php
    }
}
