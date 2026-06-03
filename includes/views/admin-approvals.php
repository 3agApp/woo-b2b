<?php
/**
 * Admin approvals screen.
 *
 * @var string  $status
 * @var array   $statuses
 * @var array   $counts
 * @var WP_User[] $users
 * @var int     $total
 * @var int     $total_pages
 * @var int     $paged
 */

if (!defined('ABSPATH')) {
    exit;
}

$base_url = WB2B_Admin::get_page_url();
?>
<div class="wrap wb2b-admin">
    <h1 class="wp-heading-inline"><?php esc_html_e('B2B Customers', 'woo-b2b'); ?></h1>
    <hr class="wp-header-end">

    <ul class="subsubsub">
        <?php
        $links = [];
        foreach ($statuses as $key => $label) {
            $count   = isset($counts[$key]) ? (int) $counts[$key] : 0;
            $url     = add_query_arg('status', $key, $base_url);
            $current = ($key === $status) ? ' class="current"' : '';
            $links[] = '<li><a href="' . esc_url($url) . '"' . $current . '>'
                . esc_html($label) . ' <span class="count">(' . esc_html($count) . ')</span></a></li>';
        }
        echo implode(' | ', $links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </ul>

    <table class="wp-list-table widefat fixed striped wb2b-approvals">
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
        <?php if (empty($users)) : ?>
            <tr>
                <td colspan="7"><?php esc_html_e('No customers found for this status.', 'woo-b2b'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($users as $user) : ?>
                <?php
                $profile  = WB2B_Customer::get_profile($user->ID);
                $location = trim($profile['postcode'] . ' ' . $profile['city']);
                if ($profile['country'] !== '') {
                    $location = trim($location . ' (' . $profile['country'] . ')');
                }
                $edit_link = get_edit_user_link($user->ID);
                ?>
                <tr id="wb2b-user-<?php echo (int) $user->ID; ?>">
                    <td>
                        <strong><?php echo esc_html($profile['company'] ?: '—'); ?></strong>
                        <?php if ($profile['vat_id']) : ?>
                            <br><small><?php esc_html_e('VAT:', 'woo-b2b'); ?> <?php echo esc_html($profile['vat_id']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html(trim($profile['first_name'] . ' ' . $profile['last_name'])); ?></a>
                        <?php if ($profile['department']) : ?>
                            <br><small><?php echo esc_html($profile['department']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><a href="mailto:<?php echo esc_attr($profile['email']); ?>"><?php echo esc_html($profile['email']); ?></a></td>
                    <td><?php echo esc_html($location ?: '—'); ?></td>
                    <td>
                        <?php
                        if ($profile['registered_at']) {
                            echo esc_html(date_i18n(get_option('date_format'), strtotime($profile['registered_at'])));
                        } else {
                            echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered)));
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $docs = (array) $profile['documents'];
                        if (empty($docs)) {
                            echo '—';
                        } else {
                            $links = [];
                            foreach ($docs as $doc_id) {
                                $doc_id = (int) $doc_id;
                                $url    = wp_get_attachment_url($doc_id);
                                if ($url) {
                                    $name    = get_the_title($doc_id) ?: basename($url);
                                    $links[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($name) . '</a>';
                                }
                            }
                            echo implode('<br>', $links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    </td>
                    <td class="wb2b-actions">
                        <?php if ($status !== WB2B_Customer::STATUS_APPROVED) : ?>
                            <button type="button" class="button button-primary wb2b-approve" data-user-id="<?php echo (int) $user->ID; ?>"><?php esc_html_e('Approve', 'woo-b2b'); ?></button>
                        <?php endif; ?>
                        <?php if ($status !== WB2B_Customer::STATUS_REJECTED) : ?>
                            <button type="button" class="button wb2b-reject" data-user-id="<?php echo (int) $user->ID; ?>"><?php esc_html_e('Reject', 'woo-b2b'); ?></button>
                        <?php endif; ?>
                        <span class="wb2b-action-status"></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
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
        </div>
    <?php endif; ?>
</div>
