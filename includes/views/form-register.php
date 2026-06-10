<?php
/**
 * Registration form partial.
 *
 * Shares scope with auth-page.php ($action_url, $redirect_to, $countries,
 * $salutations, $old, $val).
 */

if (!defined('ABSPATH')) {
    exit;
}

$docs_required = (bool) get_option('wb2b_require_documents', false);
$max_mb        = (int) get_option('wb2b_doc_max_mb', 10);
$doc_exts      = (array) get_option('wb2b_doc_mimes', ['pdf', 'jpg', 'jpeg', 'png']);
$accept_attr   = '.' . implode(',.', array_map('sanitize_text_field', $doc_exts));

$privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
$terms_id    = function_exists('wc_terms_and_conditions_page_id') ? wc_terms_and_conditions_page_id() : 0;
$terms_url   = $terms_id ? get_permalink($terms_id) : '';

/**
 * Render a salutation <select>.
 */
$render_salutation = function ($name, $id, $current) use ($salutations) {
    echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="wb2b-input">';
    foreach ($salutations as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
};

/**
 * Render a country <select>.
 */
$render_country = function ($name, $id, $current, $required = true) use ($countries) {
    echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="wb2b-input wb2b-country"' . ($required ? ' required' : '') . '>';
    echo '<option value="" ' . selected($current, '', false) . ' disabled>' . esc_html__('Select a country …', 'woo-b2b') . '</option>';
    foreach ($countries as $code => $label) {
        echo '<option value="' . esc_attr($code) . '" ' . selected($current, $code, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
};
?>
<div class="wb2b-card wb2b-card--register">
    <h2 class="wb2b-card__title"><?php esc_html_e('I am a new customer', 'woo-b2b'); ?></h2>
    <p class="wb2b-card__intro"><?php esc_html_e('Register a business account. Your account will be reviewed by our team before it is activated.', 'woo-b2b'); ?></p>

    <form class="wb2b-form wb2b-form--register" action="<?php echo $action_url; ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="wb2b_register">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
        <?php wp_nonce_field('wb2b_register', 'wb2b_nonce'); ?>

        <!-- Honeypot: must stay empty. -->
        <div class="wb2b-hp" aria-hidden="true">
            <label><?php esc_html_e('Leave this field empty', 'woo-b2b'); ?>
                <input type="text" name="wb2b_hp" value="" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <fieldset class="wb2b-fieldset">
            <legend><?php esc_html_e('Your details', 'woo-b2b'); ?></legend>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-3">
                    <label for="wb2b-salutation"><?php esc_html_e('Salutation', 'woo-b2b'); ?></label>
                    <?php $render_salutation('salutation', 'wb2b-salutation', $val('salutation')); ?>
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-first-name"><?php esc_html_e('First name', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-first-name" class="wb2b-input" name="first_name" value="<?php echo esc_attr($val('first_name')); ?>" required>
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-last-name"><?php esc_html_e('Last name', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-last-name" class="wb2b-input" name="last_name" value="<?php echo esc_attr($val('last_name')); ?>" required>
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-12">
                    <label for="wb2b-company"><?php esc_html_e('Company', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-company" class="wb2b-input" name="company" value="<?php echo esc_attr($val('company')); ?>" required>
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-department"><?php esc_html_e('Department', 'woo-b2b'); ?></label>
                    <input type="text" id="wb2b-department" class="wb2b-input" name="department" value="<?php echo esc_attr($val('department')); ?>">
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-vat"><?php esc_html_e('VAT ID', 'woo-b2b'); ?></label>
                    <input type="text" id="wb2b-vat" class="wb2b-input" name="vat_id" value="<?php echo esc_attr($val('vat_id')); ?>">
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-email"><?php esc_html_e('Email address', 'woo-b2b'); ?> *</label>
                    <input type="email" id="wb2b-email" class="wb2b-input" name="email" value="<?php echo esc_attr($val('email')); ?>" required>
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-email-confirm"><?php esc_html_e('Confirm email address', 'woo-b2b'); ?> *</label>
                    <input type="email" id="wb2b-email-confirm" class="wb2b-input" name="email_confirmation" value="<?php echo esc_attr($val('email_confirmation')); ?>" data-match="#wb2b-email" required>
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-password"><?php esc_html_e('Password', 'woo-b2b'); ?> *</label>
                    <input type="password" id="wb2b-password" class="wb2b-input" name="password" minlength="<?php echo esc_attr((int) get_option('wb2b_min_password', 8)); ?>" autocomplete="new-password" required>
                    <small class="wb2b-help"><?php printf(esc_html__('Must be at least %d characters long.', 'woo-b2b'), (int) get_option('wb2b_min_password', 8)); ?></small>
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-password-confirm"><?php esc_html_e('Confirm password', 'woo-b2b'); ?> *</label>
                    <input type="password" id="wb2b-password-confirm" class="wb2b-input" name="password_confirmation" autocomplete="new-password" data-match="#wb2b-password" required>
                </p>
            </div>
        </fieldset>

        <fieldset class="wb2b-fieldset">
            <legend><?php esc_html_e('Your address', 'woo-b2b'); ?></legend>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-8">
                    <label for="wb2b-street"><?php esc_html_e('Street and house number', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-street" class="wb2b-input" name="street" value="<?php echo esc_attr($val('street')); ?>" required>
                </p>
                <p class="wb2b-field wb2b-col-4">
                    <label for="wb2b-postcode"><?php esc_html_e('Postcode', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-postcode" class="wb2b-input" name="postcode" value="<?php echo esc_attr($val('postcode')); ?>" required>
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-city"><?php esc_html_e('City', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-city" class="wb2b-input" name="city" value="<?php echo esc_attr($val('city')); ?>" required>
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-address2"><?php esc_html_e('Address addition', 'woo-b2b'); ?></label>
                    <input type="text" id="wb2b-address2" class="wb2b-input" name="address_line2" value="<?php echo esc_attr($val('address_line2')); ?>">
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-country"><?php esc_html_e('Country', 'woo-b2b'); ?> *</label>
                    <?php $render_country('country', 'wb2b-country', $val('country')); ?>
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-phone"><?php esc_html_e('Phone number', 'woo-b2b'); ?></label>
                    <input type="text" id="wb2b-phone" class="wb2b-input" name="phone" value="<?php echo esc_attr($val('phone')); ?>">
                </p>
            </div>

            <p class="wb2b-field wb2b-field--inline">
                <label class="wb2b-checkbox">
                    <input type="checkbox" id="wb2b-different-shipping" name="different_shipping" value="1" data-toggle-target=".wb2b-shipping" <?php checked(!empty($val('different_shipping'))); ?>>
                    <span><?php esc_html_e('Ship to a different address', 'woo-b2b'); ?></span>
                </label>
            </p>
        </fieldset>

        <fieldset class="wb2b-fieldset wb2b-shipping" <?php echo empty($val('different_shipping')) ? 'hidden' : ''; ?>>
            <legend><?php esc_html_e('Shipping address', 'woo-b2b'); ?></legend>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-3">
                    <label for="wb2b-ship-salutation"><?php esc_html_e('Salutation', 'woo-b2b'); ?></label>
                    <?php $render_salutation('shipping_salutation', 'wb2b-ship-salutation', $val('shipping_salutation')); ?>
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-first"><?php esc_html_e('First name', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-ship-first" class="wb2b-input wb2b-ship-input" name="shipping_first_name" value="<?php echo esc_attr($val('shipping_first_name')); ?>">
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-last"><?php esc_html_e('Last name', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-ship-last" class="wb2b-input wb2b-ship-input" name="shipping_last_name" value="<?php echo esc_attr($val('shipping_last_name')); ?>">
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-company"><?php esc_html_e('Company', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-ship-company" class="wb2b-input wb2b-ship-input" name="shipping_company" value="<?php echo esc_attr($val('shipping_company')); ?>">
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-department"><?php esc_html_e('Department', 'woo-b2b'); ?></label>
                    <input type="text" id="wb2b-ship-department" class="wb2b-input" name="shipping_department" value="<?php echo esc_attr($val('shipping_department')); ?>">
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-8">
                    <label for="wb2b-ship-street"><?php esc_html_e('Street and house number', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-ship-street" class="wb2b-input wb2b-ship-input" name="shipping_street" value="<?php echo esc_attr($val('shipping_street')); ?>">
                </p>
                <p class="wb2b-field wb2b-col-4">
                    <label for="wb2b-ship-postcode"><?php esc_html_e('Postcode', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-ship-postcode" class="wb2b-input wb2b-ship-input" name="shipping_postcode" value="<?php echo esc_attr($val('shipping_postcode')); ?>">
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-city"><?php esc_html_e('City', 'woo-b2b'); ?> *</label>
                    <input type="text" id="wb2b-ship-city" class="wb2b-input wb2b-ship-input" name="shipping_city" value="<?php echo esc_attr($val('shipping_city')); ?>">
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-line2"><?php esc_html_e('Address addition', 'woo-b2b'); ?></label>
                    <input type="text" id="wb2b-ship-line2" class="wb2b-input" name="shipping_address_line2" value="<?php echo esc_attr($val('shipping_address_line2')); ?>">
                </p>
            </div>

            <div class="wb2b-row">
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-country"><?php esc_html_e('Country', 'woo-b2b'); ?> *</label>
                    <?php $render_country('shipping_country', 'wb2b-ship-country', $val('shipping_country'), false); ?>
                </p>
                <p class="wb2b-field wb2b-col-6">
                    <label for="wb2b-ship-phone"><?php esc_html_e('Phone number', 'woo-b2b'); ?></label>
                    <input type="text" id="wb2b-ship-phone" class="wb2b-input" name="shipping_phone" value="<?php echo esc_attr($val('shipping_phone')); ?>">
                </p>
            </div>
        </fieldset>

        <fieldset class="wb2b-fieldset">
            <legend><?php esc_html_e('Documents', 'woo-b2b'); ?><?php echo $docs_required ? ' *' : ''; ?></legend>
            <p class="wb2b-help">
                <?php
                printf(
                    /* translators: 1: allowed file types, 2: max size in MB */
                    esc_html__('Upload supporting documents (e.g. trade licence). Allowed: %1$s, up to %2$d MB each.', 'woo-b2b'),
                    esc_html(strtoupper(implode(', ', $doc_exts))),
                    (int) $max_mb
                );
                ?>
            </p>
            <p class="wb2b-field wb2b-col-12">
                <input type="file" name="documents[]" multiple accept="<?php echo esc_attr($accept_attr); ?>" <?php echo $docs_required ? 'required' : ''; ?>>
            </p>
        </fieldset>

        <div class="wb2b-privacy">
            <label class="wb2b-checkbox">
                <input type="checkbox" name="accept_terms" value="1" required>
                <span>
                    <?php
                    if ($privacy_url && $terms_url) {
                        printf(
                            /* translators: 1: privacy policy link, 2: terms link */
                            wp_kses_post(__('I have read the <a href="%1$s" target="_blank" rel="noopener">privacy policy</a> and agree to the <a href="%2$s" target="_blank" rel="noopener">terms and conditions</a>.', 'woo-b2b')),
                            esc_url($privacy_url),
                            esc_url($terms_url)
                        );
                    } elseif ($privacy_url) {
                        printf(
                            /* translators: %s: privacy policy link */
                            wp_kses_post(__('I have read and accept the <a href="%s" target="_blank" rel="noopener">privacy policy</a>.', 'woo-b2b')),
                            esc_url($privacy_url)
                        );
                    } else {
                        esc_html_e('I have read and accept the privacy policy and terms and conditions.', 'woo-b2b');
                    }
                    ?>
                    *
                </span>
            </label>
        </div>

        <p class="wb2b-required-note"><?php esc_html_e('Fields marked with an asterisk (*) are required.', 'woo-b2b'); ?></p>

        <p class="wb2b-submit">
            <button type="submit" class="wb2b-btn wb2b-btn--primary wb2b-btn--lg wb2b-btn--block"><?php esc_html_e('Create account', 'woo-b2b'); ?></button>
        </p>
    </form>
</div>
