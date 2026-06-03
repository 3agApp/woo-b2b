<?php
/**
 * License View
 *
 * @var string $license_key    The stored license key
 * @var string $license_status The local license status
 * @var array  $license_data   License data from API
 * @var int    $last_check     Unix timestamp of last license check
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_active        = $license_status === 'active';
$needs_activation = $license_status === 'not_activated';
$is_invalid       = $license_status === 'invalid';
$has_license      = !empty($license_key);

$expires_at   = isset($license_data['expires_at']) ? $license_data['expires_at'] : null;
$activations  = isset($license_data['activations']) ? $license_data['activations'] : null;
$product_name = isset($license_data['product']) ? $license_data['product'] : '';
$package      = isset($license_data['package']) ? $license_data['package'] : '';
$api_status   = isset($license_data['status']) ? $license_data['status'] : '';

$card_class = 'wb2b-license-inactive';
if ($is_active) {
    $card_class = 'wb2b-license-active';
} elseif ($needs_activation) {
    $card_class = 'wb2b-license-warning';
} elseif ($is_invalid) {
    $card_class = 'wb2b-license-expired';
}

/**
 * Mask a license key for display.
 */
$mask_key = function ($key) {
    $len = strlen($key);
    if ($len > 8) {
        return substr($key, 0, 4) . str_repeat('•', $len - 8) . substr($key, -4);
    }
    if ($len > 4) {
        return substr($key, 0, 2) . str_repeat('•', $len - 2);
    }
    return str_repeat('•', $len);
};
?>

<div class="wrap wb2b-license-wrap">
    <div class="wb2b-header">
        <div class="wb2b-header-left">
            <h1><?php esc_html_e('License', 'woo-b2b'); ?></h1>
            <p class="wb2b-subtitle"><?php esc_html_e('Manage your plugin license activation', 'woo-b2b'); ?></p>
        </div>
    </div>

    <div class="wb2b-license-container">

        <!-- License Status Card -->
        <div class="wb2b-section wb2b-card wb2b-license-card <?php echo esc_attr($card_class); ?>">
            <div class="wb2b-card-body">
                <div class="wb2b-license-status-display">
                    <div class="wb2b-license-icon">
                        <?php if ($is_active) : ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php elseif ($needs_activation) : ?>
                            <span class="dashicons dashicons-warning"></span>
                        <?php elseif ($is_invalid) : ?>
                            <span class="dashicons dashicons-dismiss"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-lock"></span>
                        <?php endif; ?>
                    </div>
                    <div class="wb2b-license-info">
                        <h2>
                            <?php
                            if ($is_active) {
                                esc_html_e('License Active', 'woo-b2b');
                            } elseif ($needs_activation) {
                                esc_html_e('Activation Required', 'woo-b2b');
                            } elseif ($is_invalid) {
                                $status_labels = [
                                    'expired'   => __('License Expired', 'woo-b2b'),
                                    'suspended' => __('License Suspended', 'woo-b2b'),
                                    'cancelled' => __('License Cancelled', 'woo-b2b'),
                                    'paused'    => __('License Paused', 'woo-b2b'),
                                ];
                                echo isset($status_labels[$api_status]) ? esc_html($status_labels[$api_status]) : esc_html__('License Invalid', 'woo-b2b');
                            } else {
                                esc_html_e('License Not Active', 'woo-b2b');
                            }
                            ?>
                        </h2>
                        <?php if ($is_active && $product_name) : ?>
                            <p class="wb2b-license-product">
                                <?php echo esc_html($product_name); ?>
                                <?php if ($package) : ?>
                                    <span class="wb2b-license-package"><?php echo esc_html($package); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php elseif ($needs_activation) : ?>
                            <p><?php esc_html_e('Your license is valid but not activated on this domain. Click "Activate on This Domain" below.', 'woo-b2b'); ?></p>
                        <?php elseif ($is_invalid) : ?>
                            <p>
                                <?php
                                $status_messages = [
                                    'expired'   => __('Your license has expired. Please renew to continue receiving updates and support.', 'woo-b2b'),
                                    'suspended' => __('Your license has been suspended. Please contact support.', 'woo-b2b'),
                                    'cancelled' => __('Your license has been cancelled.', 'woo-b2b'),
                                    'paused'    => __('Your subscription is paused. Please resume it to continue.', 'woo-b2b'),
                                ];
                                echo isset($status_messages[$api_status]) ? esc_html($status_messages[$api_status]) : esc_html__('Your license is no longer valid.', 'woo-b2b');
                                ?>
                            </p>
                        <?php elseif (!$has_license) : ?>
                            <p><?php esc_html_e('Enter your license key to receive automatic updates and support.', 'woo-b2b'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($has_license && ($is_active || $needs_activation || $is_invalid)) : ?>
                    <div class="wb2b-license-details">
                        <div class="wb2b-license-detail-grid">
                            <div class="wb2b-license-detail-item">
                                <span class="wb2b-detail-label"><?php esc_html_e('Expires', 'woo-b2b'); ?></span>
                                <span class="wb2b-detail-value">
                                    <?php if ($expires_at) : ?>
                                        <?php
                                        echo esc_html(wp_date('F j, Y', strtotime($expires_at)));
                                        $remaining = WB2B()->license->get_remaining_days();
                                        if ($remaining !== null) {
                                            if ($remaining > 0) {
                                                /* translators: %d: days remaining */
                                                echo ' <span class="wb2b-days-remaining">(' . esc_html(sprintf(__('%d days left', 'woo-b2b'), $remaining)) . ')</span>';
                                            } else {
                                                echo ' <span class="wb2b-expired">(' . esc_html__('Expired', 'woo-b2b') . ')</span>';
                                            }
                                        }
                                        ?>
                                    <?php else : ?>
                                        <span class="wb2b-lifetime"><?php esc_html_e('Lifetime', 'woo-b2b'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ($activations) : ?>
                                <div class="wb2b-license-detail-item">
                                    <span class="wb2b-detail-label"><?php esc_html_e('Activations', 'woo-b2b'); ?></span>
                                    <span class="wb2b-detail-value">
                                        <?php
                                        printf(
                                            /* translators: 1: used count, 2: limit */
                                            esc_html__('%1$d of %2$d used', 'woo-b2b'),
                                            (int) $activations['used'],
                                            (int) $activations['limit']
                                        );
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($last_check) : ?>
                                <div class="wb2b-license-detail-item">
                                    <span class="wb2b-detail-label"><?php esc_html_e('Last Verified', 'woo-b2b'); ?></span>
                                    <span class="wb2b-detail-value">
                                        <?php echo esc_html(human_time_diff($last_check, time())); ?> <?php esc_html_e('ago', 'woo-b2b'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- License Form Card -->
        <div class="wb2b-section wb2b-card">
            <div class="wb2b-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php
                    if ($is_active) {
                        esc_html_e('License Management', 'woo-b2b');
                    } elseif ($needs_activation) {
                        esc_html_e('Domain Activation', 'woo-b2b');
                    } else {
                        esc_html_e('Activate License', 'woo-b2b');
                    }
                    ?>
                </h2>
            </div>
            <div class="wb2b-card-body">
                <?php if ($is_active) : ?>
                    <div class="wb2b-license-key-display">
                        <label class="wb2b-label"><?php esc_html_e('Current License Key', 'woo-b2b'); ?></label>
                        <div class="wb2b-license-key-masked"><span class="wb2b-key-value"><?php echo esc_html($mask_key($license_key)); ?></span></div>
                    </div>
                    <div class="wb2b-license-actions">
                        <button type="button" id="wb2b-check-license" class="wb2b-btn wb2b-btn-secondary">
                            <span class="dashicons dashicons-update"></span> <?php esc_html_e('Verify License', 'woo-b2b'); ?>
                        </button>
                        <button type="button" id="wb2b-deactivate-license" class="wb2b-btn wb2b-btn-danger">
                            <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Deactivate License', 'woo-b2b'); ?>
                        </button>
                    </div>

                <?php elseif ($needs_activation) : ?>
                    <div class="wb2b-license-key-display">
                        <label class="wb2b-label"><?php esc_html_e('Current License Key', 'woo-b2b'); ?></label>
                        <div class="wb2b-license-key-masked"><span class="wb2b-key-value"><?php echo esc_html($mask_key($license_key)); ?></span></div>
                    </div>
                    <p class="wb2b-activation-notice">
                        <span class="dashicons dashicons-warning"></span>
                        <?php
                        printf(
                            /* translators: %s: domain */
                            wp_kses_post(__('This domain (%s) is not yet activated. Click the button below to activate.', 'woo-b2b')),
                            '<strong>' . esc_html(wb2b_get_domain()) . '</strong>'
                        );
                        ?>
                    </p>
                    <div class="wb2b-license-actions">
                        <button type="button" id="wb2b-activate-domain" class="wb2b-btn wb2b-btn-primary wb2b-btn-lg">
                            <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Activate on This Domain', 'woo-b2b'); ?>
                        </button>
                        <button type="button" id="wb2b-deactivate-license" class="wb2b-btn wb2b-btn-secondary">
                            <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Use Different License', 'woo-b2b'); ?>
                        </button>
                    </div>

                <?php elseif ($is_invalid && $has_license) : ?>
                    <div class="wb2b-license-key-display">
                        <label class="wb2b-label"><?php esc_html_e('Current License Key', 'woo-b2b'); ?></label>
                        <div class="wb2b-license-key-masked"><span class="wb2b-key-value"><?php echo esc_html($mask_key($license_key)); ?></span></div>
                    </div>
                    <div class="wb2b-license-actions">
                        <button type="button" id="wb2b-check-license" class="wb2b-btn wb2b-btn-secondary">
                            <span class="dashicons dashicons-update"></span> <?php esc_html_e('Re-check License', 'woo-b2b'); ?>
                        </button>
                        <button type="button" id="wb2b-deactivate-license" class="wb2b-btn wb2b-btn-danger">
                            <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Remove License', 'woo-b2b'); ?>
                        </button>
                    </div>

                <?php else : ?>
                    <form id="wb2b-license-form" class="wb2b-form">
                        <div class="wb2b-form-row">
                            <label for="wb2b-license-key" class="wb2b-label">
                                <?php esc_html_e('License Key', 'woo-b2b'); ?> <span class="wb2b-required">*</span>
                            </label>
                            <div class="wb2b-input-group">
                                <input type="text" id="wb2b-license-key" name="license_key" value=""
                                       class="wb2b-input wb2b-input-lg wb2b-input-mono"
                                       placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                                <button type="submit" class="wb2b-btn wb2b-btn-primary">
                                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Activate', 'woo-b2b'); ?>
                                </button>
                            </div>
                            <p class="wb2b-help-text"><?php esc_html_e('Enter the license key you received after purchase.', 'woo-b2b'); ?></p>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="wb2b-license-help">
                    <p>
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php
                        printf(
                            /* translators: %s: purchase link */
                            wp_kses_post(__('Don\'t have a license? %s', 'woo-b2b')),
                            '<a href="https://3ag.app/products/woo-b2b" target="_blank" rel="noopener">' . esc_html__('Purchase one here', 'woo-b2b') . '</a>'
                        );
                        ?>
                    </p>
                    <p>
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php
                        printf(
                            /* translators: %s: dashboard link */
                            wp_kses_post(__('Manage your licenses and domain activations: %s', 'woo-b2b')),
                            '<a href="https://3ag.app/dashboard/licenses" target="_blank" rel="noopener">' . esc_html__('License Dashboard', 'woo-b2b') . '</a>'
                        );
                        ?>
                    </p>
                    <p>
                        <span class="dashicons dashicons-email"></span>
                        <?php
                        printf(
                            /* translators: %s: support email link */
                            wp_kses_post(__('Need help? Contact support: %s', 'woo-b2b')),
                            '<a href="mailto:info@3ag.app">info@3ag.app</a>'
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Plugin Updates Card -->
        <?php
        $update_data     = get_transient('wb2b_update_data');
        $current_version = WB2B_VERSION;
        $has_update      = $update_data && !empty($update_data['version']) && version_compare($current_version, $update_data['version'], '<');
        ?>
        <div class="wb2b-section wb2b-card">
            <div class="wb2b-card-header">
                <h2><span class="dashicons dashicons-update"></span> <?php esc_html_e('Plugin Updates', 'woo-b2b'); ?></h2>
            </div>
            <div class="wb2b-card-body">
                <div class="wb2b-update-status">
                    <div class="wb2b-version-info">
                        <div class="wb2b-version-row">
                            <span class="wb2b-version-label"><?php esc_html_e('Installed Version:', 'woo-b2b'); ?></span>
                            <span class="wb2b-version-value"><?php echo esc_html($current_version); ?></span>
                        </div>
                        <?php if ($update_data && !empty($update_data['version'])) : ?>
                            <div class="wb2b-version-row">
                                <span class="wb2b-version-label"><?php esc_html_e('Latest Version:', 'woo-b2b'); ?></span>
                                <span class="wb2b-version-value <?php echo $has_update ? 'wb2b-version-new' : ''; ?>">
                                    <?php echo esc_html($update_data['version']); ?>
                                    <?php if ($has_update) : ?>
                                        <span class="wb2b-update-badge"><?php esc_html_e('Update Available', 'woo-b2b'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wb2b-update-actions">
                        <button type="button" id="wb2b-check-update" class="wb2b-btn wb2b-btn-secondary">
                            <span class="dashicons dashicons-update"></span> <?php esc_html_e('Check for Updates', 'woo-b2b'); ?>
                        </button>
                        <?php if ($has_update) : ?>
                            <button type="button" id="wb2b-install-update" class="wb2b-btn wb2b-btn-primary" data-version="<?php echo esc_attr($update_data['version']); ?>">
                                <span class="dashicons dashicons-download"></span>
                                <?php printf(esc_html__('Update to %s', 'woo-b2b'), esc_html($update_data['version'])); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <p class="wb2b-help-text wb2b-muted" style="margin-top:15px;">
                        <span class="dashicons dashicons-external"></span>
                        <?php
                        printf(
                            /* translators: %s: GitHub releases link */
                            wp_kses_post(__('Updates are fetched from %s', 'woo-b2b')),
                            '<a href="https://github.com/3agApp/woo-b2b/releases" target="_blank" rel="noopener">GitHub Releases</a>'
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>
