<?php
/**
 * Admin approvals screen.
 *
 * @var string    $status
 * @var array     $statuses
 * @var array     $counts
 * @var WP_User[] $users
 * @var int       $total
 * @var int       $total_pages
 * @var int       $paged
 */

if (!defined('ABSPATH')) {
    exit;
}

$base_url = WB2B_Admin::get_page_url();

$status_icons = [
    WB2B_Customer::STATUS_PENDING  => 'dashicons-clock',
    WB2B_Customer::STATUS_APPROVED => 'dashicons-yes-alt',
    WB2B_Customer::STATUS_REJECTED => 'dashicons-dismiss',
];
?>
<div class="wrap wb2b-ui wb2b-approvals-page">

    <div class="wb2b-header">
        <div class="wb2b-header-left">
            <h1><?php esc_html_e('B2B Customers', 'woo-b2b'); ?></h1>
            <p class="wb2b-subtitle"><?php esc_html_e('Review registrations and approve customers before they can browse or order', 'woo-b2b'); ?></p>
        </div>
    </div>

    <nav class="wb2b-tabs">
        <?php foreach ($statuses as $key => $label) : ?>
            <?php
            $count   = isset($counts[$key]) ? (int) $counts[$key] : 0;
            $url     = add_query_arg('status', $key, $base_url);
            $is_curr = ($key === $status);
            $icon    = isset($status_icons[$key]) ? $status_icons[$key] : 'dashicons-admin-users';
            ?>
            <a href="<?php echo esc_url($url); ?>" class="wb2b-tab <?php echo $is_curr ? 'is-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                <?php echo esc_html($label); ?>
                <span class="wb2b-tab-count"><?php echo esc_html($count); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($users)) : ?>
        <div class="wb2b-empty-state">
            <span class="dashicons dashicons-groups"></span>
            <p><?php esc_html_e('No customers found for this status.', 'woo-b2b'); ?></p>
        </div>
    <?php else : ?>
        <div class="wb2b-table-wrap">
            <table class="wb2b-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Company', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Name', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Email', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Location', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Registered', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Documents', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Actions', 'woo-b2b'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user) : ?>
                    <?php
                    $profile  = WB2B_Customer::get_profile($user->ID);
                    $location = trim($profile['postcode'] . ' ' . $profile['city']);
                    if ($profile['country'] !== '') {
                        $location = trim($location . ' (' . $profile['country'] . ')');
                    }
                    $edit_link = get_edit_user_link($user->ID);
                    $reg_stamp = $profile['registered_at'] ? strtotime($profile['registered_at']) : strtotime($user->user_registered);
                    ?>
                    <tr id="wb2b-user-<?php echo (int) $user->ID; ?>">
                        <td>
                            <strong><?php echo esc_html($profile['company'] ?: '—'); ?></strong>
                            <?php if ($profile['vat_id']) : ?>
                                <br><small class="wb2b-muted"><?php esc_html_e('VAT:', 'woo-b2b'); ?> <?php echo esc_html($profile['vat_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html(trim($profile['first_name'] . ' ' . $profile['last_name'])); ?></a>
                            <?php if ($profile['department']) : ?>
                                <br><small class="wb2b-muted"><?php echo esc_html($profile['department']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><a href="mailto:<?php echo esc_attr($profile['email']); ?>"><?php echo esc_html($profile['email']); ?></a></td>
                        <td><?php echo esc_html($location ?: '—'); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), $reg_stamp)); ?></td>
                        <td>
                            <?php
                            $docs = (array) $profile['documents'];
                            if (empty($docs)) {
                                echo '<span class="wb2b-muted">—</span>';
                            } else {
                                $doc_links = [];
                                foreach ($docs as $doc_id) {
                                    $doc_id = (int) $doc_id;
                                    $doc_url = wp_get_attachment_url($doc_id);
                                    if ($doc_url) {
                                        $name        = get_the_title($doc_id) ?: basename($doc_url);
                                        $doc_links[] = '<a href="' . esc_url($doc_url) . '" target="_blank" rel="noopener">' . esc_html($name) . '</a>';
                                    }
                                }
                                echo implode('<br>', $doc_links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                        </td>
                        <td class="wb2b-actions">
                            <?php if ($status !== WB2B_Customer::STATUS_APPROVED) : ?>
                                <button type="button" class="wb2b-btn wb2b-btn-primary wb2b-approve" data-user-id="<?php echo (int) $user->ID; ?>"><?php esc_html_e('Approve', 'woo-b2b'); ?></button>
                            <?php endif; ?>
                            <?php if ($status !== WB2B_Customer::STATUS_REJECTED) : ?>
                                <button type="button" class="wb2b-btn wb2b-btn-secondary wb2b-reject" data-user-id="<?php echo (int) $user->ID; ?>"><?php esc_html_e('Reject', 'woo-b2b'); ?></button>
                            <?php endif; ?>
                            <span class="wb2b-action-status"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="wb2b-pagination">
                <?php
                echo paginate_links([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    'base'      => add_query_arg('paged', '%#%', add_query_arg('status', $status, $base_url)),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]);
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
