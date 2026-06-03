<?php
/**
 * Login form partial.
 *
 * Shares scope with auth-page.php ($action_url, $redirect_to, $val).
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wb2b-card wb2b-card--login">
    <h2 class="wb2b-card__title"><?php esc_html_e('I am already a customer', 'woo-b2b'); ?></h2>
    <p class="wb2b-card__intro"><?php esc_html_e('Log in with your email address and password.', 'woo-b2b'); ?></p>

    <form class="wb2b-form wb2b-form--login" action="<?php echo $action_url; ?>" method="post">
        <input type="hidden" name="action" value="wb2b_login">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
        <?php wp_nonce_field('wb2b_login', 'wb2b_nonce'); ?>

        <p class="wb2b-field">
            <label for="wb2b-login-email"><?php esc_html_e('Email address', 'woo-b2b'); ?></label>
            <input type="email" id="wb2b-login-email" name="username" value="<?php echo esc_attr($val('username')); ?>" placeholder="<?php esc_attr_e('Enter your email address …', 'woo-b2b'); ?>" required>
        </p>

        <p class="wb2b-field">
            <label for="wb2b-login-password"><?php esc_html_e('Password', 'woo-b2b'); ?></label>
            <input type="password" id="wb2b-login-password" name="password" placeholder="<?php esc_attr_e('Enter your password …', 'woo-b2b'); ?>" required>
        </p>

        <p class="wb2b-field wb2b-field--inline">
            <label class="wb2b-checkbox">
                <input type="checkbox" name="remember" value="1">
                <span><?php esc_html_e('Remember me', 'woo-b2b'); ?></span>
            </label>
        </p>

        <p class="wb2b-login-recover">
            <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('I forgot my password.', 'woo-b2b'); ?></a>
        </p>

        <p class="wb2b-submit">
            <button type="submit" class="wb2b-btn wb2b-btn--primary"><?php esc_html_e('Log in', 'woo-b2b'); ?></button>
        </p>
    </form>

    <div class="wb2b-advantages">
        <h3 class="wb2b-advantages__title"><?php esc_html_e('Benefits of registering:', 'woo-b2b'); ?></h3>
        <ul>
            <li><?php esc_html_e('Fast checkout', 'woo-b2b'); ?></li>
            <li><?php esc_html_e('Save your data and preferences', 'woo-b2b'); ?></li>
            <li><?php esc_html_e('Order overview and shipping information', 'woo-b2b'); ?></li>
            <li><?php esc_html_e('Access to wholesale pricing once approved', 'woo-b2b'); ?></li>
        </ul>
    </div>
</div>
