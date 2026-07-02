<?php
/**
 * Auth page wrapper: login (left) + registration (right).
 *
 * @var array  $flash       Flash notice ['type','messages','input'] or null.
 * @var string $redirect_to Validated bounce-back URL.
 * @var array  $countries   code => label.
 * @var array  $salutations value => label.
 * @var array  $old         Repopulation input from a failed submission.
 * @var string $skin_class  Auth wrapper skin modifier (wb2b-skin--theme|--default).
 * @var array  $copy        Audience-dependent copy (see WB2B_Auth::get_copy()).
 */

if (!defined('ABSPATH')) {
    exit;
}

$action_url = esc_url(admin_url('admin-post.php'));

// Helper for repopulating fields.
$val = function ($key) use ($old) {
    return isset($old[$key]) ? $old[$key] : '';
};
?>
<div class="wb2b-auth <?php echo esc_attr($skin_class); ?>">

    <section class="wb2b-hero">
        <div class="wb2b-hero__text">
            <span class="wb2b-hero__eyebrow"><?php echo esc_html($copy['eyebrow']); ?></span>
            <h2 class="wb2b-hero__title"><?php echo esc_html($copy['hero_title']); ?></h2>
            <p class="wb2b-hero__subtitle"><?php echo esc_html($copy['hero_subtitle']); ?></p>
        </div>
        <ul class="wb2b-benefits">
            <li class="wb2b-benefit"><span class="wb2b-benefit__check"></span><?php echo esc_html($copy['benefit_pricing']); ?></li>
            <li class="wb2b-benefit"><span class="wb2b-benefit__check"></span><?php esc_html_e('Fast checkout &amp; saved details', 'woo-b2b'); ?></li>
            <li class="wb2b-benefit"><span class="wb2b-benefit__check"></span><?php esc_html_e('Full order history &amp; invoices', 'woo-b2b'); ?></li>
        </ul>
    </section>

    <?php if (!empty($flash) && !empty($flash['messages'])) : ?>
        <?php $notice_class = (isset($flash['type']) && $flash['type'] === 'success') ? 'wb2b-notice--success' : 'wb2b-notice--error'; ?>
        <div class="wb2b-notice <?php echo esc_attr($notice_class); ?>">
            <ul>
                <?php foreach ($flash['messages'] as $message) : ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="wb2b-grid">
        <div class="wb2b-col wb2b-col--login">
            <?php include WB2B_PLUGIN_DIR . 'includes/views/form-login.php'; ?>
        </div>
        <div class="wb2b-col wb2b-col--register">
            <?php include WB2B_PLUGIN_DIR . 'includes/views/form-register.php'; ?>
        </div>
    </div>
</div>
