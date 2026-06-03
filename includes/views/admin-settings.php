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
 * @var array  $all_countries
 */

if (!defined('ABSPATH')) {
    exit;
}

$all_pages = get_pages(['sort_column' => 'post_title']);
?>
<div class="wrap wb2b-admin">
    <h1><?php esc_html_e('Woo B2B Settings', 'woo-b2b'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('wb2b_settings'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Enable B2B lock', 'woo-b2b'); ?></th>
                <td>
                    <input type="hidden" name="wb2b_enabled" value="0">
                    <label>
                        <input type="checkbox" name="wb2b_enabled" value="1" <?php checked($enabled); ?>>
                        <?php esc_html_e('Redirect guests and unapproved users to the account page.', 'woo-b2b'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('When disabled, the storefront is publicly visible again.', 'woo-b2b'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wb2b_auth_page_id"><?php esc_html_e('Account page', 'woo-b2b'); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_pages([
                        'name'              => 'wb2b_auth_page_id',
                        'id'                => 'wb2b_auth_page_id',
                        'selected'          => $auth_page_id,
                        'show_option_none'  => __('— Select —', 'woo-b2b'),
                        'option_none_value' => 0,
                    ]);
                    ?>
                    <p class="description"><?php esc_html_e('The page containing the [woo_b2b_auth] shortcode. Guests are redirected here.', 'woo-b2b'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wb2b_allowed_pages"><?php esc_html_e('Always-public pages', 'woo-b2b'); ?></label></th>
                <td>
                    <select name="wb2b_allowed_pages[]" id="wb2b_allowed_pages" multiple size="6" style="min-width:280px;">
                        <?php foreach ($all_pages as $page) : ?>
                            <option value="<?php echo (int) $page->ID; ?>" <?php selected(in_array((int) $page->ID, array_map('intval', $allowed_pages), true)); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Pages that stay visible without logging in (e.g. privacy policy, terms). Hold Ctrl/Cmd to select multiple.', 'woo-b2b'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Approval', 'woo-b2b'); ?></th>
                <td>
                    <input type="hidden" name="wb2b_auto_approve" value="0">
                    <label>
                        <input type="checkbox" name="wb2b_auto_approve" value="1" <?php checked($auto_approve); ?>>
                        <?php esc_html_e('Auto-approve new registrations (skip manual review).', 'woo-b2b'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wb2b_admin_email"><?php esc_html_e('Notification email', 'woo-b2b'); ?></label></th>
                <td>
                    <input type="email" name="wb2b_admin_email" id="wb2b_admin_email" class="regular-text" value="<?php echo esc_attr($admin_email); ?>">
                    <p class="description"><?php esc_html_e('Where new-registration notifications are sent.', 'woo-b2b'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wb2b_min_password"><?php esc_html_e('Minimum password length', 'woo-b2b'); ?></label></th>
                <td>
                    <input type="number" name="wb2b_min_password" id="wb2b_min_password" min="4" max="64" value="<?php echo esc_attr($min_password); ?>" class="small-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wb2b_countries"><?php esc_html_e('Allowed countries', 'woo-b2b'); ?></label></th>
                <td>
                    <select name="wb2b_countries[]" id="wb2b_countries" multiple size="8" style="min-width:280px;">
                        <?php foreach ($all_countries as $code => $label) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected(in_array($code, array_map('strtoupper', $countries), true)); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Countries offered in the registration address dropdown.', 'woo-b2b'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Documents', 'woo-b2b'); ?></th>
                <td>
                    <input type="hidden" name="wb2b_require_documents" value="0">
                    <label>
                        <input type="checkbox" name="wb2b_require_documents" value="1" <?php checked($require_documents); ?>>
                        <?php esc_html_e('Require at least one document on registration.', 'woo-b2b'); ?>
                    </label>

                    <p style="margin-top:12px;"><strong><?php esc_html_e('Allowed file types:', 'woo-b2b'); ?></strong></p>
                    <?php foreach (['pdf', 'jpg', 'jpeg', 'png'] as $ext) : ?>
                        <label style="margin-right:14px;">
                            <input type="checkbox" name="wb2b_doc_mimes[]" value="<?php echo esc_attr($ext); ?>" <?php checked(in_array($ext, array_map('strtolower', $doc_mimes), true)); ?>>
                            <?php echo esc_html(strtoupper($ext)); ?>
                        </label>
                    <?php endforeach; ?>

                    <p style="margin-top:12px;">
                        <label for="wb2b_doc_max_mb"><?php esc_html_e('Maximum file size (MB):', 'woo-b2b'); ?></label>
                        <input type="number" name="wb2b_doc_max_mb" id="wb2b_doc_max_mb" min="1" max="100" value="<?php echo esc_attr($doc_max_mb); ?>" class="small-text">
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
