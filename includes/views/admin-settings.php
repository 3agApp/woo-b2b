<?php
/**
 * Admin settings screen.
 *
 * @var bool   $enabled
 * @var bool   $auto_approve
 * @var bool   $require_documents
 * @var int    $auth_page_id
 * @var array  $allowed_pages
 * @var array  $countries
 * @var array  $doc_mimes
 * @var int    $doc_max_mb
 * @var int    $min_password
 * @var string $admin_email
 * @var string $auth_ui_style
 * @var array  $all_countries
 */

if (!defined('ABSPATH')) {
    exit;
}

$all_pages       = get_pages(['sort_column' => 'post_title']);
$allowed_pages   = array_map('intval', (array) $allowed_pages);
$countries_upper = array_map('strtoupper', (array) $countries);
$doc_mimes_lower = array_map('strtolower', (array) $doc_mimes);
?>
<div class="wrap wb2b-ui wb2b-settings-page">

    <div class="wb2b-header">
        <div class="wb2b-header-main">
            <div class="wb2b-header-icon"><span class="dashicons dashicons-admin-settings"></span></div>
            <div class="wb2b-header-left">
                <h1><?php esc_html_e('Woo B2B Settings', 'woo-b2b'); ?></h1>
                <p class="wb2b-subtitle"><?php esc_html_e('Configure the catalog lock, registration, and notifications', 'woo-b2b'); ?></p>
            </div>
        </div>
    </div>

    <form id="wb2b-settings-form" method="post">

        <!-- Access control -->
        <section class="wb2b-section wb2b-card">
            <div class="wb2b-card-header">
                <h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Access control', 'woo-b2b'); ?></h2>
            </div>
            <div class="wb2b-card-body">
                <div class="wb2b-form-row">
                    <label class="wb2b-label"><?php esc_html_e('Enable B2B lock', 'woo-b2b'); ?></label>
                    <div class="wb2b-toggle-row">
                        <input type="hidden" name="wb2b_enabled" value="0">
                        <label class="wb2b-switch">
                            <input type="checkbox" name="wb2b_enabled" value="1" <?php checked($enabled); ?>>
                            <span class="wb2b-slider"></span>
                        </label>
                        <span class="wb2b-toggle-label"><?php esc_html_e('Redirect guests and unapproved users to the account page', 'woo-b2b'); ?></span>
                    </div>
                    <p class="wb2b-help-text"><?php esc_html_e('When disabled, the storefront is publicly visible again.', 'woo-b2b'); ?></p>
                </div>

                <div class="wb2b-form-row">
                    <label class="wb2b-label" for="wb2b_auth_page_id"><?php esc_html_e('Account page', 'woo-b2b'); ?></label>
                    <select name="wb2b_auth_page_id" id="wb2b_auth_page_id" class="wb2b-select">
                        <option value="0"><?php esc_html_e('— Select —', 'woo-b2b'); ?></option>
                        <?php foreach ($all_pages as $page) : ?>
                            <option value="<?php echo (int) $page->ID; ?>" <?php selected($auth_page_id, $page->ID); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="wb2b-help-text"><?php esc_html_e('The page containing the [woo_b2b_auth] shortcode. Guests are redirected here.', 'woo-b2b'); ?></p>
                </div>

                <div class="wb2b-form-row">
                    <label class="wb2b-label" for="wb2b_allowed_pages"><?php esc_html_e('Always-public pages', 'woo-b2b'); ?></label>
                    <select name="wb2b_allowed_pages[]" id="wb2b_allowed_pages" class="wb2b-select" multiple size="6">
                        <?php foreach ($all_pages as $page) : ?>
                            <option value="<?php echo (int) $page->ID; ?>" <?php selected(in_array((int) $page->ID, $allowed_pages, true)); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="wb2b-help-text"><?php esc_html_e('Pages that stay visible without logging in (e.g. privacy policy, terms). Hold Ctrl/Cmd to select multiple.', 'woo-b2b'); ?></p>
                </div>
            </div>
        </section>

        <!-- Appearance -->
        <section class="wb2b-section wb2b-card">
            <div class="wb2b-card-header">
                <h2><span class="dashicons dashicons-admin-customizer"></span> <?php esc_html_e('Appearance', 'woo-b2b'); ?></h2>
            </div>
            <div class="wb2b-card-body">
                <div class="wb2b-form-row">
                    <label class="wb2b-label" for="wb2b_auth_ui_style"><?php esc_html_e('Authentication page style', 'woo-b2b'); ?></label>
                    <select name="wb2b_auth_ui_style" id="wb2b_auth_ui_style" class="wb2b-select wb2b-input-wide">
                        <option value="theme" <?php selected($auth_ui_style, 'theme'); ?>><?php esc_html_e('Inherit theme styles', 'woo-b2b'); ?></option>
                        <option value="default" <?php selected($auth_ui_style, 'default'); ?>><?php esc_html_e('Use plugin\'s own styles', 'woo-b2b'); ?></option>
                    </select>
                    <p class="wb2b-help-text"><?php esc_html_e('"Inherit theme styles" adopts your active theme\'s colors, buttons and form fields on the [woo_b2b_auth] page (optimized for WoodMart). On other themes it falls back to the plugin\'s own design.', 'woo-b2b'); ?></p>
                </div>
            </div>
        </section>

        <!-- Registration -->
        <section class="wb2b-section wb2b-card">
            <div class="wb2b-card-header">
                <h2><span class="dashicons dashicons-id"></span> <?php esc_html_e('Registration', 'woo-b2b'); ?></h2>
            </div>
            <div class="wb2b-card-body">
                <div class="wb2b-form-row">
                    <label class="wb2b-label" for="wb2b_countries"><?php esc_html_e('Allowed countries', 'woo-b2b'); ?></label>
                    <select name="wb2b_countries[]" id="wb2b_countries" class="wb2b-select" multiple size="8">
                        <?php foreach ($all_countries as $code => $label) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected(in_array($code, $countries_upper, true)); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="wb2b-help-text"><?php esc_html_e('Countries offered in the registration address dropdown.', 'woo-b2b'); ?></p>
                </div>

                <div class="wb2b-form-row">
                    <label class="wb2b-label"><?php esc_html_e('Approval', 'woo-b2b'); ?></label>
                    <div class="wb2b-toggle-row">
                        <input type="hidden" name="wb2b_auto_approve" value="0">
                        <label class="wb2b-switch">
                            <input type="checkbox" name="wb2b_auto_approve" value="1" <?php checked($auto_approve); ?>>
                            <span class="wb2b-slider"></span>
                        </label>
                        <span class="wb2b-toggle-label"><?php esc_html_e('Auto-approve new registrations (skip manual review)', 'woo-b2b'); ?></span>
                    </div>
                </div>

                <div class="wb2b-form-row">
                    <label class="wb2b-label" for="wb2b_min_password"><?php esc_html_e('Minimum password length', 'woo-b2b'); ?></label>
                    <input type="number" name="wb2b_min_password" id="wb2b_min_password" class="wb2b-input wb2b-input-sm" min="4" max="64" value="<?php echo esc_attr($min_password); ?>">
                </div>
            </div>
        </section>

        <!-- Documents -->
        <section class="wb2b-section wb2b-card">
            <div class="wb2b-card-header">
                <h2><span class="dashicons dashicons-media-document"></span> <?php esc_html_e('Documents', 'woo-b2b'); ?></h2>
            </div>
            <div class="wb2b-card-body">
                <div class="wb2b-form-row">
                    <label class="wb2b-label"><?php esc_html_e('Require documents', 'woo-b2b'); ?></label>
                    <div class="wb2b-toggle-row">
                        <input type="hidden" name="wb2b_require_documents" value="0">
                        <label class="wb2b-switch">
                            <input type="checkbox" name="wb2b_require_documents" value="1" <?php checked($require_documents); ?>>
                            <span class="wb2b-slider"></span>
                        </label>
                        <span class="wb2b-toggle-label"><?php esc_html_e('Require at least one document on registration', 'woo-b2b'); ?></span>
                    </div>
                </div>

                <div class="wb2b-form-row">
                    <label class="wb2b-label"><?php esc_html_e('Allowed file types', 'woo-b2b'); ?></label>
                    <div class="wb2b-checkbox-group">
                        <?php foreach (['pdf', 'jpg', 'jpeg', 'png'] as $ext) : ?>
                            <label class="wb2b-checkbox-label">
                                <input type="checkbox" name="wb2b_doc_mimes[]" value="<?php echo esc_attr($ext); ?>" <?php checked(in_array($ext, $doc_mimes_lower, true)); ?>>
                                <?php echo esc_html(strtoupper($ext)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="wb2b-form-row">
                    <label class="wb2b-label" for="wb2b_doc_max_mb"><?php esc_html_e('Maximum file size (MB)', 'woo-b2b'); ?></label>
                    <input type="number" name="wb2b_doc_max_mb" id="wb2b_doc_max_mb" class="wb2b-input wb2b-input-sm" min="1" max="100" value="<?php echo esc_attr($doc_max_mb); ?>">
                </div>
            </div>
        </section>

        <!-- Notifications -->
        <section class="wb2b-section wb2b-card">
            <div class="wb2b-card-header">
                <h2><span class="dashicons dashicons-email"></span> <?php esc_html_e('Notifications', 'woo-b2b'); ?></h2>
            </div>
            <div class="wb2b-card-body">
                <div class="wb2b-form-row">
                    <label class="wb2b-label" for="wb2b_admin_email"><?php esc_html_e('Notification email', 'woo-b2b'); ?></label>
                    <input type="email" name="wb2b_admin_email" id="wb2b_admin_email" class="wb2b-input wb2b-input-wide" value="<?php echo esc_attr($admin_email); ?>">
                    <p class="wb2b-help-text"><?php esc_html_e('Where new-registration notifications are sent.', 'woo-b2b'); ?></p>
                </div>
            </div>
        </section>

        <div class="wb2b-form-actions">
            <button type="submit" class="wb2b-btn wb2b-btn-primary wb2b-btn-lg">
                <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save settings', 'woo-b2b'); ?>
            </button>
            <span class="wb2b-saved-msg"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Saved', 'woo-b2b'); ?></span>
        </div>
    </form>
</div>
