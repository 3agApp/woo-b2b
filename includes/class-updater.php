<?php
/**
 * Plugin Updater Class
 *
 * Automatic updates from GitHub Releases (paired with the release workflow in
 * .github/workflows/release.yml which publishes a release on every version bump).
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Updater {

    const GITHUB_OWNER     = '3agApp';
    const GITHUB_REPO      = 'woo-b2b';
    const PRODUCT_SLUG     = 'woo-b2b';
    const CACHE_KEY        = 'wb2b_update_data';
    const CACHE_EXPIRATION = 43200; // 12 hours

    public function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
        add_filter('auto_update_plugin', [$this, 'enable_auto_update'], 10, 2);

        add_action('wb2b_update_check', [$this, 'scheduled_check']);

        if (!wp_next_scheduled('wb2b_update_check')) {
            wp_schedule_event(time(), 'twicedaily', 'wb2b_update_check');
        }
    }

    /**
     * Enable WordPress auto-updates for this plugin.
     */
    public function enable_auto_update($update, $item) {
        if (isset($item->plugin) && $item->plugin === WB2B_PLUGIN_BASENAME) {
            return true;
        }
        return $update;
    }

    private function get_github_api_url() {
        return sprintf('https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_OWNER, self::GITHUB_REPO);
    }

    private function get_github_repo_url() {
        return sprintf('https://github.com/%s/%s', self::GITHUB_OWNER, self::GITHUB_REPO);
    }

    /**
     * Inject our update into the plugins update transient.
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $update_data = $this->get_update_data();
        if (!$update_data || empty($update_data['version'])) {
            return $transient;
        }

        if (version_compare(WB2B_VERSION, $update_data['version'], '<')) {
            $transient->response[WB2B_PLUGIN_BASENAME] = (object) [
                'slug'         => self::PRODUCT_SLUG,
                'plugin'       => WB2B_PLUGIN_BASENAME,
                'new_version'  => $update_data['version'],
                'url'          => $this->get_github_repo_url(),
                'package'      => $update_data['download_url'],
                'tested'       => '7.0',
                'requires_php' => '7.4',
                'requires'     => '5.8',
            ];
        } else {
            $transient->no_update[WB2B_PLUGIN_BASENAME] = (object) [
                'slug'        => self::PRODUCT_SLUG,
                'plugin'      => WB2B_PLUGIN_BASENAME,
                'new_version' => WB2B_VERSION,
                'url'         => $this->get_github_repo_url(),
            ];
        }

        return $transient;
    }

    /**
     * Fetch (and cache) the latest release data from GitHub.
     *
     * @param bool $force
     * @return array|null
     */
    public function get_update_data($force = false) {
        if (!$force) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                return $cached;
            }
        }

        $response = wp_remote_get($this->get_github_api_url(), [
            'timeout' => 30,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('WB2B GitHub Update Check Error: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data)) {
            if ($code === 404) {
                error_log('WB2B GitHub Update Check: No releases found');
            } else {
                error_log('WB2B GitHub Update Check HTTP ' . $code . ': ' . ($data['message'] ?? 'Unknown error'));
            }
            return null;
        }

        $version      = isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : null;
        $download_url = null;

        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['name']) && strpos($asset['name'], '-latest.zip') !== false) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
                if (isset($asset['name']) && preg_match('/\.zip$/', $asset['name'])) {
                    $download_url = $asset['browser_download_url'];
                }
            }
        }

        if (empty($download_url) && !empty($data['zipball_url'])) {
            $download_url = $data['zipball_url'];
        }

        $update_data = [
            'version'      => $version,
            'download_url' => $download_url,
            'changelog'    => $data['body'] ?? '',
            'release_date' => $data['published_at'] ?? null,
            'checked'      => time(),
        ];

        set_transient(self::CACHE_KEY, $update_data, self::CACHE_EXPIRATION);

        return $update_data;
    }

    /**
     * Provide plugin info for the WordPress "View details" popup.
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        if (!isset($args->slug) || $args->slug !== self::PRODUCT_SLUG) {
            return $result;
        }

        $update_data = $this->get_update_data();

        return (object) [
            'name'           => 'Woo B2B',
            'slug'           => self::PRODUCT_SLUG,
            'version'        => $update_data['version'] ?? WB2B_VERSION,
            'author'         => '<a href="https://github.com/' . self::GITHUB_OWNER . '">3agApp</a>',
            'author_profile' => 'https://github.com/' . self::GITHUB_OWNER,
            'homepage'       => $this->get_github_repo_url(),
            'requires'       => '5.8',
            'tested'         => '7.0',
            'requires_php'   => '7.4',
            'last_updated'   => $update_data['release_date'] ?? gmdate('Y-m-d H:i:s'),
            'sections'       => [
                'description' => '<p>' . esc_html__('Turn a WooCommerce store into a B2B-only shop with a catalog lock, login/registration page, and admin approval workflow.', 'woo-b2b') . '</p>',
                'changelog'   => $this->get_changelog($update_data),
            ],
            'download_link'  => $update_data['download_url'] ?? '',
        ];
    }

    /**
     * Format the changelog from release notes or readme.txt.
     */
    private function get_changelog($update_data = null) {
        if (!empty($update_data['changelog'])) {
            $changelog = $update_data['changelog'];
            $changelog = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $changelog);
            $changelog = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $changelog);
            $changelog = preg_replace('/^[*-] (.+)$/m', '<li>$1</li>', $changelog);
            $changelog = preg_replace('/(<li>.+<\/li>\n?)+/s', '<ul>$0</ul>', $changelog);
            return nl2br($changelog);
        }

        return '<p>' . sprintf(
            /* translators: %s: releases URL */
            wp_kses_post(__('See the <a href="%s">GitHub releases</a> for the full changelog.', 'woo-b2b')),
            esc_url($this->get_github_repo_url() . '/releases')
        ) . '</p>';
    }

    /**
     * After install, ensure the plugin folder keeps its expected name and stays active.
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $is_our_plugin = false;
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === WB2B_PLUGIN_BASENAME) {
            $is_our_plugin = true;
        } elseif (isset($result['destination_name']) && dirname(WB2B_PLUGIN_BASENAME) === $result['destination_name']) {
            $is_our_plugin = true;
        }

        if (!$is_our_plugin) {
            return $response;
        }

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (empty($wp_filesystem) || !is_object($wp_filesystem)) {
            return $response;
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname(WB2B_PLUGIN_BASENAME);

        if ($wp_filesystem->exists($result['destination']) && $result['destination'] !== $plugin_dir) {
            $wp_filesystem->move($result['destination'], $plugin_dir);
            $result['destination'] = $plugin_dir;
        }

        activate_plugin(WB2B_PLUGIN_BASENAME);

        if (!wp_next_scheduled('wb2b_license_check')) {
            wp_schedule_event(time(), 'daily', 'wb2b_license_check');
        }
        if (!wp_next_scheduled('wb2b_update_check')) {
            wp_schedule_event(time(), 'twicedaily', 'wb2b_update_check');
        }

        return $response;
    }

    /**
     * Scheduled refresh of the cached release data.
     */
    public function scheduled_check() {
        $this->get_update_data(true);
    }

    public function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }

    public function force_check() {
        $this->clear_cache();
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}
