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
