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
 * @var string    $search
 * @var string    $country
 * @var string    $order
 * @var array     $country_opts  code => label
 */

if (!defined('ABSPATH')) {
    exit;
}

$base_url  = WB2B_Admin::get_page_url();
$total_all = array_sum($counts);

$status_icons = [
    WB2B_Customer::STATUS_PENDING  => 'dashicons-clock',
    WB2B_Customer::STATUS_APPROVED => 'dashicons-yes-alt',
    WB2B_Customer::STATUS_REJECTED => 'dashicons-dismiss',
];

// Active filters, preserved across tab + pagination links.
$filter_args = [];
if ($search !== '')   { $filter_args['s'] = $search; }
if ($country !== '')  { $filter_args['country'] = $country; }
if ($order === 'ASC') { $filter_args['order'] = 'ASC'; }

$initials = function ($first, $last, $email) {
    $ini = trim(($first !== '' ? mb_substr($first, 0, 1) : '') . ($last !== '' ? mb_substr($last, 0, 1) : ''));
    return $ini !== '' ? $ini : mb_substr((string) $email, 0, 2);
};
?>
<div class="wrap wb2b-ui wb2b-approvals-page">

    <div class="wb2b-header">
        <div class="wb2b-header-main">
            <div class="wb2b-header-icon"><span class="dashicons dashicons-groups"></span></div>
            <div class="wb2b-header-left">
                <h1><?php esc_html_e('B2B Customers', 'woo-b2b'); ?></h1>
                <p class="wb2b-subtitle"><?php esc_html_e('Review registrations and approve customers before they can browse or order', 'woo-b2b'); ?></p>
            </div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="wb2b-stats">
        <?php
        $stat_defs = [
            WB2B_Customer::STATUS_PENDING  => ['label' => __('Pending', 'woo-b2b'),  'icon' => 'dashicons-clock'],
            WB2B_Customer::STATUS_APPROVED => ['label' => __('Approved', 'woo-b2b'), 'icon' => 'dashicons-yes-alt'],
            WB2B_Customer::STATUS_REJECTED => ['label' => __('Rejected', 'woo-b2b'), 'icon' => 'dashicons-dismiss'],
        ];
        foreach ($stat_defs as $key => $def) :
            $count = isset($counts[$key]) ? (int) $counts[$key] : 0;
            ?>
            <a href="<?php echo esc_url(add_query_arg('status', $key, $base_url)); ?>" class="wb2b-stat-card <?php echo $key === $status ? 'is-active' : ''; ?>">
                <span class="wb2b-stat-icon wb2b-stat-icon--<?php echo esc_attr($key); ?>"><span class="dashicons <?php echo esc_attr($def['icon']); ?>"></span></span>
                <span class="wb2b-stat-meta">
                    <span class="wb2b-stat-value"><?php echo esc_html(number_format_i18n($count)); ?></span>
                    <span class="wb2b-stat-label"><?php echo esc_html($def['label']); ?></span>
                </span>
            </a>
        <?php endforeach; ?>
        <div class="wb2b-stat-card">
            <span class="wb2b-stat-icon wb2b-stat-icon--total"><span class="dashicons dashicons-groups"></span></span>
            <span class="wb2b-stat-meta">
                <span class="wb2b-stat-value"><?php echo esc_html(number_format_i18n($total_all)); ?></span>
                <span class="wb2b-stat-label"><?php esc_html_e('Total customers', 'woo-b2b'); ?></span>
            </span>
        </div>
    </div>

    <!-- Status tabs -->
    <nav class="wb2b-tabs">
        <?php foreach ($statuses as $key => $label) :
            $count   = isset($counts[$key]) ? (int) $counts[$key] : 0;
            $url     = add_query_arg(array_merge(['status' => $key], $filter_args), $base_url);
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

    <!-- Toolbar: search + filters -->
    <form class="wb2b-toolbar" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(WB2B_Admin::MENU_SLUG); ?>">
        <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
        <div class="wb2b-search">
            <span class="dashicons dashicons-search"></span>
            <input type="search" name="s" class="wb2b-input" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by name or email…', 'woo-b2b'); ?>">
        </div>
        <?php if (!empty($country_opts)) : ?>
            <select name="country" class="wb2b-select">
                <option value=""><?php esc_html_e('All countries', 'woo-b2b'); ?></option>
                <?php foreach ($country_opts as $code => $label) : ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($country, $code); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <select name="order" class="wb2b-select">
            <option value="DESC" <?php selected($order, 'DESC'); ?>><?php esc_html_e('Newest first', 'woo-b2b'); ?></option>
            <option value="ASC" <?php selected($order, 'ASC'); ?>><?php esc_html_e('Oldest first', 'woo-b2b'); ?></option>
        </select>
        <button type="submit" class="wb2b-btn wb2b-btn-secondary"><span class="dashicons dashicons-filter"></span> <?php esc_html_e('Filter', 'woo-b2b'); ?></button>
        <?php if (!empty($filter_args)) : ?>
            <a href="<?php echo esc_url(add_query_arg('status', $status, $base_url)); ?>" class="wb2b-btn wb2b-btn-secondary"><?php esc_html_e('Reset', 'woo-b2b'); ?></a>
        <?php endif; ?>
    </form>

    <!-- Bulk action bar -->
    <div class="wb2b-bulkbar" hidden>
        <span class="wb2b-bulkbar-count"><span class="wb2b-bulk-n">0</span> <?php esc_html_e('selected', 'woo-b2b'); ?></span>
        <div class="wb2b-bulkbar-actions">
            <?php if ($status !== WB2B_Customer::STATUS_APPROVED) : ?>
                <button type="button" class="wb2b-btn wb2b-btn-primary wb2b-bulk-approve"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Approve selected', 'woo-b2b'); ?></button>
            <?php endif; ?>
            <?php if ($status !== WB2B_Customer::STATUS_REJECTED) : ?>
                <button type="button" class="wb2b-btn wb2b-btn-danger wb2b-bulk-reject"><span class="dashicons dashicons-no"></span> <?php esc_html_e('Reject selected', 'woo-b2b'); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($users)) : ?>
        <div class="wb2b-empty-state">
            <span class="dashicons dashicons-groups"></span>
            <p>
                <?php if (!empty($filter_args)) : ?>
                    <?php esc_html_e('No customers match your search.', 'woo-b2b'); ?>
                <?php else : ?>
                    <?php esc_html_e('No customers found for this status.', 'woo-b2b'); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php else : ?>
        <div class="wb2b-table-wrap">
            <table class="wb2b-table">
                <thead>
                    <tr>
                        <th class="wb2b-check"><input type="checkbox" class="wb2b-check-all" aria-label="<?php esc_attr_e('Select all', 'woo-b2b'); ?>"></th>
                        <th><?php esc_html_e('Company', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Customer', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Location', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Registered', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Documents', 'woo-b2b'); ?></th>
                        <th><?php esc_html_e('Actions', 'woo-b2b'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user) :
                    $profile  = WB2B_Customer::get_profile($user->ID);
                    $location = trim($profile['postcode'] . ' ' . $profile['city']);
                    if ($profile['country'] !== '') {
                        $location = trim($location . ' (' . $profile['country'] . ')');
                    }
                    $edit_link = get_edit_user_link($user->ID);
                    $reg_stamp = $profile['registered_at'] ? strtotime($profile['registered_at']) : strtotime($user->user_registered);
                    $full_name = trim($profile['first_name'] . ' ' . $profile['last_name']);
                    ?>
                    <tr id="wb2b-user-<?php echo (int) $user->ID; ?>">
                        <td class="wb2b-check"><input type="checkbox" class="wb2b-row-check" value="<?php echo (int) $user->ID; ?>" aria-label="<?php esc_attr_e('Select customer', 'woo-b2b'); ?>"></td>
                        <td>
                            <strong><?php echo esc_html($profile['company'] ?: '—'); ?></strong>
                            <?php if ($profile['vat_id']) : ?>
                                <br><small class="wb2b-muted"><?php esc_html_e('VAT:', 'woo-b2b'); ?> <?php echo esc_html($profile['vat_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="wb2b-customer-cell">
                                <span class="wb2b-avatar"><?php echo esc_html($initials($profile['first_name'], $profile['last_name'], $profile['email'])); ?></span>
                                <span>
                                    <a href="<?php echo esc_url($edit_link); ?>"><strong><?php echo esc_html($full_name ?: $profile['email']); ?></strong></a>
                                    <br><a href="mailto:<?php echo esc_attr($profile['email']); ?>" class="wb2b-muted"><?php echo esc_html($profile['email']); ?></a>
                                    <?php if ($profile['department']) : ?>
                                        <br><small class="wb2b-muted"><?php echo esc_html($profile['department']); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($location ?: '—'); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), $reg_stamp)); ?></td>
                        <td>
                            <?php
                            $docs = (array) $profile['documents'];
                            if (empty($docs)) {
                                echo '<span class="wb2b-muted">—</span>';
                            } else {
                                foreach ($docs as $doc_id) {
                                    $doc_id  = (int) $doc_id;
                                    $doc_url = wp_get_attachment_url($doc_id);
                                    if ($doc_url) {
                                        $name = get_the_title($doc_id) ?: basename($doc_url);
                                        printf(
                                            '<a href="%1$s" target="_blank" rel="noopener" class="wb2b-doc-chip"><span class="dashicons dashicons-media-default"></span>%2$s</a>',
                                            esc_url($doc_url),
                                            esc_html($name)
                                        );
                                    }
                                }
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
                    'base'      => add_query_arg('paged', '%#%', add_query_arg(array_merge(['status' => $status], $filter_args), $base_url)),
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
