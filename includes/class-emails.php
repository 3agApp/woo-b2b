<?php
/**
 * Emails Class
 *
 * Notification emails for the registration / approval workflow.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Emails {

    /**
     * Notify the admin of a new pending registration.
     *
     * @param int $user_id
     * @return bool
     */
    public function send_admin_new_registration($user_id) {
        $to      = get_option('wb2b_admin_email', get_option('admin_email'));
        $profile = WB2B_Customer::get_profile($user_id);

        /* translators: %s: site name */
        $subject = sprintf(__('[%s] New B2B registration pending approval', 'woo-b2b'), get_bloginfo('name'));

        $body  = '<p>' . esc_html__('A new B2B customer has registered and is awaiting approval.', 'woo-b2b') . '</p>';
        $body .= $this->profile_table($profile);
        $body .= $this->button(admin_url('admin.php?page=woo-b2b'), __('Review registration', 'woo-b2b'));

        $subject = apply_filters('wb2b_email_admin_subject', $subject, $user_id);
        $body    = apply_filters('wb2b_email_admin_body', $body, $user_id, $profile);

        return $this->send($to, $subject, $body);
    }

    /**
     * Confirm to the customer that we received their registration.
     *
     * @param int $user_id
     * @return bool
     */
    public function send_customer_received($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        /* translators: %s: site name */
        $subject = sprintf(__('[%s] We received your registration', 'woo-b2b'), get_bloginfo('name'));

        $body = '<p>' . sprintf(
            /* translators: %s: customer name */
            esc_html__('Hello %s,', 'woo-b2b'),
            esc_html($user->display_name)
        ) . '</p>';

        if (get_option('wb2b_auto_approve', false)) {
            $body .= '<p>' . esc_html__('Thank you for registering. Your account is active and you can log in right away.', 'woo-b2b') . '</p>';
            $body .= $this->button($this->login_url(), __('Log in', 'woo-b2b'));
        } else {
            $body .= '<p>' . esc_html__('Thank you for registering. Your account is currently being reviewed by our team. We will email you as soon as it has been approved.', 'woo-b2b') . '</p>';
        }

        $subject = apply_filters('wb2b_email_received_subject', $subject, $user_id);
        $body    = apply_filters('wb2b_email_received_body', $body, $user_id);

        return $this->send($user->user_email, $subject, $body);
    }

    /**
     * Tell the customer their account was approved.
     *
     * @param int $user_id
     * @return bool
     */
    public function send_customer_approved($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        /* translators: %s: site name */
        $subject = sprintf(__('[%s] Your account has been approved', 'woo-b2b'), get_bloginfo('name'));

        $body  = '<p>' . sprintf(esc_html__('Hello %s,', 'woo-b2b'), esc_html($user->display_name)) . '</p>';
        $body .= '<p>' . esc_html__('Good news — your account has been approved. You can now log in to browse our catalogue and place orders.', 'woo-b2b') . '</p>';
        $body .= $this->button($this->login_url(), __('Log in', 'woo-b2b'));

        $subject = apply_filters('wb2b_email_approved_subject', $subject, $user_id);
        $body    = apply_filters('wb2b_email_approved_body', $body, $user_id);

        return $this->send($user->user_email, $subject, $body);
    }

    /**
     * Tell the customer their registration was not approved.
     *
     * @param int    $user_id
     * @param string $reason
     * @return bool
     */
    public function send_customer_rejected($user_id, $reason = '') {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        /* translators: %s: site name */
        $subject = sprintf(__('[%s] About your registration', 'woo-b2b'), get_bloginfo('name'));

        $body  = '<p>' . sprintf(esc_html__('Hello %s,', 'woo-b2b'), esc_html($user->display_name)) . '</p>';
        $body .= '<p>' . esc_html__('Thank you for your interest. Unfortunately we were unable to approve your account at this time.', 'woo-b2b') . '</p>';

        if ($reason !== '') {
            $body .= '<p><strong>' . esc_html__('Reason:', 'woo-b2b') . '</strong> ' . esc_html($reason) . '</p>';
        }

        $subject = apply_filters('wb2b_email_rejected_subject', $subject, $user_id);
        $body    = apply_filters('wb2b_email_rejected_body', $body, $user_id, $reason);

        return $this->send($user->user_email, $subject, $body);
    }

    /**
     * Send an HTML email.
     *
     * @param string $to
     * @param string $subject
     * @param string $body_html
     * @return bool
     */
    public function send($to, $subject, $body_html) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, $this->wrap($subject, $body_html), $headers);
    }

    /**
     * Wrap content in a simple HTML shell.
     *
     * @param string $title
     * @param string $content
     * @return string
     */
    protected function wrap($title, $content) {
        $site = get_bloginfo('name');

        $html  = '<!doctype html><html><body style="margin:0;padding:0;background:#f4f5f7;">';
        $html .= '<div style="max-width:600px;margin:0 auto;padding:24px;font-family:Arial,Helvetica,sans-serif;color:#333;line-height:1.5;">';
        $html .= '<h2 style="color:#2b6cb0;margin:0 0 16px;">' . esc_html($site) . '</h2>';
        $html .= '<div style="background:#fff;border:1px solid #e2e6ea;border-radius:8px;padding:24px;">' . $content . '</div>';
        $html .= '<p style="color:#9aa0a6;font-size:12px;margin-top:18px;">' . esc_html($site) . '</p>';
        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * A simple HTML button/link.
     *
     * @param string $url
     * @param string $label
     * @return string
     */
    protected function button($url, $label) {
        return '<p style="margin:20px 0 0;"><a href="' . esc_url($url) . '" style="display:inline-block;background:#2b6cb0;color:#fff;text-decoration:none;padding:11px 22px;border-radius:6px;font-weight:bold;">' . esc_html($label) . '</a></p>';
    }

    /**
     * The auth/login page URL.
     *
     * @return string
     */
    protected function login_url() {
        $page_id = (int) get_option('wb2b_auth_page_id', 0);
        $url     = $page_id ? get_permalink($page_id) : '';
        return $url ? $url : wp_login_url();
    }

    /**
     * Build an HTML table summarising a customer profile.
     *
     * @param array $profile
     * @return string
     */
    protected function profile_table($profile) {
        if (empty($profile)) {
            return '';
        }

        $copy = WB2B_Auth::get_copy();

        $rows = [
            $copy['company_label']      => $profile['company'],
            __('Name', 'woo-b2b')       => trim($profile['first_name'] . ' ' . $profile['last_name']),
            __('Email', 'woo-b2b')      => $profile['email'],
            __('VAT ID', 'woo-b2b')     => $profile['vat_id'],
            __('Address', 'woo-b2b')    => trim($profile['street'] . ', ' . $profile['postcode'] . ' ' . $profile['city']),
            __('Country', 'woo-b2b')    => $profile['country'],
            __('Phone', 'woo-b2b')      => $profile['phone'],
        ];

        $html = '<table style="border-collapse:collapse;width:100%;margin:12px 0;">';
        foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $html .= '<tr>';
            $html .= '<td style="padding:6px 10px;border:1px solid #e2e6ea;background:#f9fafb;font-weight:bold;width:35%;">' . esc_html($label) . '</td>';
            $html .= '<td style="padding:6px 10px;border:1px solid #e2e6ea;">' . esc_html($value) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
