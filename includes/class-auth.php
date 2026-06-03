<?php
/**
 * Auth Class
 *
 * Renders the [woo_b2b_auth] page and processes login + registration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Auth {

    public function __construct() {
        add_shortcode('woo_b2b_auth', [$this, 'render_auth_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('admin_post_nopriv_wb2b_login', [$this, 'process_login']);
        add_action('admin_post_wb2b_login', [$this, 'process_login']);
        add_action('admin_post_nopriv_wb2b_register', [$this, 'process_register']);
        add_action('admin_post_wb2b_register', [$this, 'process_register']);
    }

    /**
     * Auth page ID.
     *
     * @return int
     */
    public static function auth_page_id() {
        return (int) get_option('wb2b_auth_page_id', 0);
    }

    /**
     * Enqueue front-end assets on the auth page.
     */
    public function enqueue_assets() {
        $auth_id = self::auth_page_id();
        if ($auth_id && is_page($auth_id)) {
            wp_enqueue_style('wb2b', WB2B_PLUGIN_URL . 'assets/css/b2b.css', [], WB2B_VERSION);
            wp_enqueue_script('wb2b', WB2B_PLUGIN_URL . 'assets/js/b2b.js', [], WB2B_VERSION, true);
        }
    }

    /**
     * Render the login + register page.
     *
     * @param array $atts
     * @return string
     */
    public function render_auth_page($atts = []) {
        // Already logged in and approved.
        if (is_user_logged_in() && WB2B_Customer::has_access(get_current_user_id())) {
            $shop = wc_get_page_permalink('shop');
            return '<div class="wb2b-auth wb2b-auth--note"><p>'
                . esc_html__('You are logged in.', 'woo-b2b') . ' '
                . '<a href="' . esc_url($shop) . '">' . esc_html__('Continue to the shop', 'woo-b2b') . '</a> &middot; '
                . '<a href="' . esc_url(wp_logout_url($shop)) . '">' . esc_html__('Log out', 'woo-b2b') . '</a>'
                . '</p></div>';
        }

        $flash       = self::get_flash();
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        $countries   = $this->get_country_options();
        $salutations = WB2B_Customer::get_salutations();
        $old         = isset($flash['input']) && is_array($flash['input']) ? $flash['input'] : [];

        ob_start();
        include WB2B_PLUGIN_DIR . 'includes/views/auth-page.php';
        return ob_get_clean();
    }

    /**
     * Process a login submission.
     */
    public function process_login() {
        $this->verify_nonce('wb2b_login');

        $username = sanitize_text_field(wp_unslash($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->set_flash('error', [__('Please enter your email and password.', 'woo-b2b')], ['username' => $username]);
            $this->redirect_back();
        }

        $user = wp_signon([
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => !empty($_POST['remember']),
        ], is_ssl());

        if (is_wp_error($user)) {
            $this->set_flash('error', [$user->get_error_message()], ['username' => $username]);
            $this->redirect_back();
        }

        $redirect = $this->get_safe_redirect();
        if (!$redirect) {
            $redirect = wc_get_page_permalink('shop');
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Process a registration submission.
     */
    public function process_register() {
        $this->verify_nonce('wb2b_register');

        // Honeypot — silently drop bots.
        if (!empty($_POST['wb2b_hp'])) {
            $this->redirect_back();
        }

        $input  = $this->collect_register_input();
        $errors = $this->validate_register($input);

        $documents = [];
        if (empty($errors)) {
            $uploaded = $this->handle_document_uploads();
            if (is_wp_error($uploaded)) {
                $errors[] = $uploaded->get_error_message();
            } else {
                $documents = $uploaded;
                if (get_option('wb2b_require_documents', false) && empty($documents)) {
                    $errors[] = __('Please upload at least one document.', 'woo-b2b');
                }
            }
        }

        if (!empty($errors)) {
            $this->set_flash('error', $errors, $this->safe_input($input));
            $this->redirect_back();
        }

        $input['documents'] = $documents;

        $result = WB2B()->customer->create_customer($input);
        if (is_wp_error($result)) {
            $this->set_flash('error', [$result->get_error_message()], $this->safe_input($input));
            $this->redirect_back();
        }

        // Notifications.
        WB2B()->emails->send_admin_new_registration($result);
        WB2B()->emails->send_customer_received($result);

        $auto = (bool) get_option('wb2b_auto_approve', false);
        $message = $auto
            ? __('Thank you! Your account has been created. You can now log in.', 'woo-b2b')
            : __('Thank you! Your registration has been received and is awaiting approval. We will email you once your account is activated.', 'woo-b2b');

        $this->set_flash('success', [$message]);
        $this->redirect_back();
    }

    /**
     * Collect and sanitize registration input.
     *
     * @return array
     */
    protected function collect_register_input() {
        $post = wp_unslash($_POST);
        $get  = function ($key) use ($post) {
            return isset($post[$key]) ? $post[$key] : '';
        };

        return [
            'salutation'             => sanitize_text_field($get('salutation')),
            'first_name'             => sanitize_text_field($get('first_name')),
            'last_name'              => sanitize_text_field($get('last_name')),
            'company'                => sanitize_text_field($get('company')),
            'department'             => sanitize_text_field($get('department')),
            'vat_id'                 => sanitize_text_field($get('vat_id')),
            'email'                  => sanitize_email($get('email')),
            'email_confirmation'     => sanitize_email($get('email_confirmation')),
            'password'               => (string) $get('password'),
            'password_confirmation'  => (string) $get('password_confirmation'),
            'street'                 => sanitize_text_field($get('street')),
            'address_line2'          => sanitize_text_field($get('address_line2')),
            'postcode'               => sanitize_text_field($get('postcode')),
            'city'                   => sanitize_text_field($get('city')),
            'country'                => strtoupper(sanitize_text_field($get('country'))),
            'phone'                  => sanitize_text_field($get('phone')),
            'different_shipping'     => !empty($get('different_shipping')),
            'shipping_salutation'    => sanitize_text_field($get('shipping_salutation')),
            'shipping_first_name'    => sanitize_text_field($get('shipping_first_name')),
            'shipping_last_name'     => sanitize_text_field($get('shipping_last_name')),
            'shipping_company'       => sanitize_text_field($get('shipping_company')),
            'shipping_department'    => sanitize_text_field($get('shipping_department')),
            'shipping_street'        => sanitize_text_field($get('shipping_street')),
            'shipping_address_line2' => sanitize_text_field($get('shipping_address_line2')),
            'shipping_postcode'      => sanitize_text_field($get('shipping_postcode')),
            'shipping_city'          => sanitize_text_field($get('shipping_city')),
            'shipping_country'       => strtoupper(sanitize_text_field($get('shipping_country'))),
            'shipping_phone'         => sanitize_text_field($get('shipping_phone')),
            'accept_terms'           => !empty($get('accept_terms')),
        ];
    }

    /**
     * Validate registration input.
     *
     * @param array $d
     * @return array Error messages.
     */
    protected function validate_register($d) {
        $errors    = [];
        $min       = (int) get_option('wb2b_min_password', 8);
        $countries = (array) get_option('wb2b_countries', ['CH', 'LI']);

        $required = [
            'first_name' => __('First name', 'woo-b2b'),
            'last_name'  => __('Last name', 'woo-b2b'),
            'company'    => __('Company', 'woo-b2b'),
            'email'      => __('Email address', 'woo-b2b'),
            'street'     => __('Street and house number', 'woo-b2b'),
            'postcode'   => __('Postcode', 'woo-b2b'),
            'city'       => __('City', 'woo-b2b'),
            'country'    => __('Country', 'woo-b2b'),
        ];
        foreach ($required as $key => $label) {
            if ($d[$key] === '') {
                /* translators: %s: field label */
                $errors[] = sprintf(__('%s is required.', 'woo-b2b'), $label);
            }
        }

        if ($d['email'] !== '' && !is_email($d['email'])) {
            $errors[] = __('Please enter a valid email address.', 'woo-b2b');
        }
        if ($d['email'] !== $d['email_confirmation']) {
            $errors[] = __('The email addresses do not match.', 'woo-b2b');
        }
        if ($d['email'] !== '' && email_exists($d['email'])) {
            $errors[] = __('An account with this email address already exists.', 'woo-b2b');
        }

        if (strlen($d['password']) < $min) {
            /* translators: %d: minimum password length */
            $errors[] = sprintf(__('The password must be at least %d characters long.', 'woo-b2b'), $min);
        }
        if ($d['password'] !== $d['password_confirmation']) {
            $errors[] = __('The passwords do not match.', 'woo-b2b');
        }

        if ($d['country'] !== '' && !in_array($d['country'], $countries, true)) {
            $errors[] = __('Please select a valid country.', 'woo-b2b');
        }

        if (!$d['accept_terms']) {
            $errors[] = __('You must accept the data protection terms.', 'woo-b2b');
        }

        if ($d['different_shipping']) {
            $ship_required = [
                'shipping_first_name' => __('Shipping first name', 'woo-b2b'),
                'shipping_last_name'  => __('Shipping last name', 'woo-b2b'),
                'shipping_company'    => __('Shipping company', 'woo-b2b'),
                'shipping_street'     => __('Shipping street and house number', 'woo-b2b'),
                'shipping_postcode'   => __('Shipping postcode', 'woo-b2b'),
                'shipping_city'       => __('Shipping city', 'woo-b2b'),
                'shipping_country'    => __('Shipping country', 'woo-b2b'),
            ];
            foreach ($ship_required as $key => $label) {
                if ($d[$key] === '') {
                    /* translators: %s: field label */
                    $errors[] = sprintf(__('%s is required.', 'woo-b2b'), $label);
                }
            }
            if ($d['shipping_country'] !== '' && !in_array($d['shipping_country'], $countries, true)) {
                $errors[] = __('Please select a valid shipping country.', 'woo-b2b');
            }
        }

        return $errors;
    }

    /**
     * Handle document uploads from the registration form.
     *
     * @return array|WP_Error Array of attachment IDs.
     */
    protected function handle_document_uploads() {
        $ids = [];

        if (empty($_FILES['documents']) || empty($_FILES['documents']['name'][0])) {
            return $ids;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $allowed_exts = array_map('strtolower', (array) get_option('wb2b_doc_mimes', ['pdf', 'jpg', 'jpeg', 'png']));
        $max_bytes    = (int) get_option('wb2b_doc_max_mb', 10) * MB_IN_BYTES;
        $mimes        = $this->ext_to_mimes($allowed_exts);

        $files = $_FILES['documents'];
        $count = is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $count; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                return new WP_Error('upload_error', __('One of the documents could not be uploaded. Please try again.', 'woo-b2b'));
            }

            if ((int) $files['size'][$i] > $max_bytes) {
                /* translators: %d: maximum file size in megabytes */
                return new WP_Error('upload_too_large', sprintf(__('Each document must be %d MB or smaller.', 'woo-b2b'), (int) get_option('wb2b_doc_max_mb', 10)));
            }

            $check = wp_check_filetype(sanitize_file_name($files['name'][$i]), $mimes);
            if (empty($check['ext']) || !in_array(strtolower($check['ext']), $allowed_exts, true)) {
                return new WP_Error('upload_type', __('Only PDF, JPG and PNG documents are allowed.', 'woo-b2b'));
            }

            $single = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];

            $uploaded = wp_handle_upload($single, ['test_form' => false, 'mimes' => $mimes]);
            if (isset($uploaded['error'])) {
                return new WP_Error('upload_failed', $uploaded['error']);
            }

            $attach_id = wp_insert_attachment([
                'post_mime_type' => $uploaded['type'],
                'post_title'     => sanitize_file_name(basename($uploaded['file'])),
                'post_content'   => '',
                'post_status'    => 'private',
            ], $uploaded['file']);

            if (is_wp_error($attach_id)) {
                return $attach_id;
            }

            $meta = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
            wp_update_attachment_metadata($attach_id, $meta);

            $ids[] = $attach_id;
        }

        return $ids;
    }

    /**
     * Map file extensions to mime types for upload validation.
     *
     * @param array $exts
     * @return array
     */
    protected function ext_to_mimes($exts) {
        $map = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];

        $out = [];
        foreach ($exts as $ext) {
            $ext = strtolower($ext);
            if (isset($map[$ext])) {
                $out[$ext] = $map[$ext];
            }
        }

        return $out;
    }

    /**
     * Country options limited to the configured allowlist.
     *
     * @return array code => label
     */
    protected function get_country_options() {
        $allowed = (array) get_option('wb2b_countries', ['CH', 'LI']);
        $all     = (function_exists('WC') && WC()->countries) ? WC()->countries->get_countries() : [];

        $out = [];
        foreach ($allowed as $code) {
            $code       = strtoupper($code);
            $out[$code] = isset($all[$code]) ? $all[$code] : $code;
        }

        return $out;
    }

    /**
     * Verify a form nonce.
     *
     * @param string $action
     */
    protected function verify_nonce($action) {
        $nonce = isset($_POST['wb2b_nonce']) ? sanitize_text_field(wp_unslash($_POST['wb2b_nonce'])) : '';
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('Security check failed. Please go back and try again.', 'woo-b2b'), 403);
        }
    }

    /**
     * Remove passwords before storing repopulation data.
     *
     * @param array $input
     * @return array
     */
    protected function safe_input($input) {
        unset($input['password'], $input['password_confirmation']);
        return $input;
    }

    /**
     * Get a validated local redirect target from the POST data.
     *
     * @return string
     */
    protected function get_safe_redirect() {
        $redirect_to = isset($_POST['redirect_to']) ? wp_unslash($_POST['redirect_to']) : '';
        if ($redirect_to === '') {
            return '';
        }
        return wp_validate_redirect($redirect_to, '');
    }

    /**
     * Redirect back to the auth page.
     */
    protected function redirect_back() {
        $url = get_permalink(self::auth_page_id());
        if (!$url) {
            $url = home_url('/');
        }

        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        if ($redirect_to !== '') {
            $url = add_query_arg('redirect_to', rawurlencode($redirect_to), $url);
        }

        wp_safe_redirect($url);
        exit;
    }

    /**
     * Store a flash notice (errors/success + repopulation input) for the next render.
     *
     * @param string $type     'error' | 'success'
     * @param array  $messages
     * @param array  $input
     */
    protected function set_flash($type, $messages, $input = []) {
        $token = wp_generate_password(20, false);
        set_transient('wb2b_flash_' . $token, [
            'type'     => $type,
            'messages' => (array) $messages,
            'input'    => $input,
        ], 5 * MINUTE_IN_SECONDS);

        if (!headers_sent()) {
            setcookie('wb2b_flash', $token, time() + 300, defined('COOKIEPATH') ? COOKIEPATH : '/', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '');
        }
        $_COOKIE['wb2b_flash'] = $token;
    }

    /**
     * Read and clear the flash notice.
     *
     * @return array|null
     */
    public static function get_flash() {
        if (empty($_COOKIE['wb2b_flash'])) {
            return null;
        }

        $token = sanitize_text_field(wp_unslash($_COOKIE['wb2b_flash']));
        $key   = 'wb2b_flash_' . $token;
        $data  = get_transient($key);
        delete_transient($key);

        if (!headers_sent()) {
            setcookie('wb2b_flash', '', time() - 3600, defined('COOKIEPATH') ? COOKIEPATH : '/', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '');
        }
        unset($_COOKIE['wb2b_flash']);

        return is_array($data) ? $data : null;
    }
}
