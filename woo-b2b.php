<?php
/**
 * Plugin Name: Woo B2B
 * Plugin URI: https://3ag.app/products/woo-b2b
 * Description: Turn a WooCommerce store into a B2B-only shop — hide the catalog from guests, gate the whole site behind login, and require admin approval before new customers can browse or order.
 * Version: 1.4.0
 * Author: 3AG
 * Author URI: https://3ag.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-b2b
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 10.7
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WB2B_VERSION', '1.4.0');
define('WB2B_PLUGIN_FILE', __FILE__);
define('WB2B_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WB2B_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WB2B_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WB2B_PRODUCT_SLUG', 'woo-b2b');

/**
 * Get the clean site domain for license validation.
 *
 * @return string
 */
function wb2b_get_domain() {
    $parsed = wp_parse_url(site_url());
    $domain = isset($parsed['host']) ? $parsed['host'] : '';

    // Remove www prefix and any port.
    $domain = preg_replace('/^www\./', '', $domain);
    $domain = preg_replace('/:\d+$/', '', $domain);

    return $domain;
}

// Autoloader: WB2B_Foo_Bar => includes/class-foo-bar.php
spl_autoload_register(function ($class) {
    $prefix = 'WB2B_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $class_name = str_replace($prefix, '', $class);
    $class_name = strtolower(str_replace('_', '-', $class_name));
    $file = WB2B_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Main Plugin Class
 */
final class Woo_B2B {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $logs;
    public $customer;
    public $emails;
    public $access;
    public $auth;
    public $admin;
    public $ajax;
    public $license;
    public $updater;
    public $payments;
    public $pricing;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, ['WB2B_Install', 'activate']);
        register_deactivation_hook(__FILE__, ['WB2B_Install', 'deactivate']);

        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'load_textdomain']);
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        // Initialize components
        $this->logs     = new WB2B_Logs();
        $this->customer = new WB2B_Customer();
        $this->emails   = new WB2B_Emails();
        $this->access   = new WB2B_Access();
        $this->auth     = new WB2B_Auth();
        $this->ajax     = new WB2B_Ajax();
        $this->license  = new WB2B_License();
        $this->updater  = new WB2B_Updater();
        $this->payments = new WB2B_Payments();
        $this->pricing  = new WB2B_Pricing();

        if (is_admin()) {
            $this->admin = new WB2B_Admin();
        }

        // HPOS compatibility
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }

    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('woo-b2b', false, dirname(WB2B_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Woo B2B requires WooCommerce to be installed and active.', 'woo-b2b'); ?></p>
        </div>
        <?php
    }

    /**
     * Get plugin URL
     */
    public function plugin_url() {
        return WB2B_PLUGIN_URL;
    }

    /**
     * Get plugin path
     */
    public function plugin_path() {
        return WB2B_PLUGIN_DIR;
    }
}

/**
 * Main instance accessor
 */
function WB2B() {
    return Woo_B2B::instance();
}

// Initialize
WB2B();
