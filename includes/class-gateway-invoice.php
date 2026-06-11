<?php
/**
 * Pay by Invoice Gateway
 *
 * An offline WooCommerce payment gateway for approved B2B customers: the order is placed "on
 * account" (on-hold by default) and settled later against an invoice. Net payment terms are
 * configurable; the resulting due date is stored on the order and shown on the order-received
 * page and in customer emails. Modelled on WooCommerce's built-in BACS gateway.
 *
 * Settings live in WooCommerce > Settings > Payments > Pay by Invoice
 * (option `woocommerce_wb2b_invoice_settings`), not the plugin's own settings page.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WB2B_Gateway_Invoice extends WC_Payment_Gateway {

    /** Unique gateway id. */
    const ID = 'wb2b_invoice';

    /** Order meta: net terms in days at the time of checkout. */
    const META_TERMS_DAYS = '_wb2b_invoice_terms_days';

    /** Order meta: computed due date (Y-m-d). */
    const META_DUE_DATE = '_wb2b_invoice_due_date';

    /** @var string Instructions shown on the thank-you page and in emails. */
    public $instructions;

    /** @var string Order status applied on checkout (slug without the wc- prefix). */
    public $order_status;

    /** @var int Net payment terms in days. */
    public $terms_days;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id                 = self::ID;
        $this->icon               = apply_filters('wb2b_invoice_icon', '');
        $this->has_fields         = false;
        $this->method_title       = __('Pay by Invoice', 'woo-b2b');
        $this->method_description = __('Let approved B2B customers place their order on account and pay against an invoice within your net payment terms. The order is held until you mark it paid.', 'woo-b2b');
        $this->supports           = ['products'];

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user-set variables.
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->order_status = $this->get_option('order_status', 'on-hold');
        $this->terms_days   = absint($this->get_option('terms_days', 30));
        $this->enabled      = $this->get_option('enabled');

        // Actions.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);

        // Customer emails.
        add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 10, 3);
    }

    /**
     * Settings form fields.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled'      => [
                'title'   => __('Enable/Disable', 'woo-b2b'),
                'type'    => 'checkbox',
                'label'   => __('Enable Pay by Invoice', 'woo-b2b'),
                'default' => 'no',
            ],
            'title'        => [
                'title'       => __('Title', 'woo-b2b'),
                'type'        => 'safe_text',
                'description' => __('Payment method title shown to the customer at checkout.', 'woo-b2b'),
                'default'     => __('Pay by Invoice', 'woo-b2b'),
                'desc_tip'    => true,
            ],
            'description'  => [
                'title'       => __('Description', 'woo-b2b'),
                'type'        => 'textarea',
                'description' => __('Payment method description shown to the customer at checkout.', 'woo-b2b'),
                'default'     => __('Place your order on account and pay by invoice within our agreed payment terms.', 'woo-b2b'),
                'desc_tip'    => true,
            ],
            'instructions' => [
                'title'       => __('Instructions', 'woo-b2b'),
                'type'        => 'textarea',
                'description' => __('Instructions added to the order-received page and order emails.', 'woo-b2b'),
                'default'     => __('We have received your order. An invoice will follow and is payable within the terms shown below.', 'woo-b2b'),
                'desc_tip'    => true,
            ],
            'order_status' => [
                'title'       => __('Order status', 'woo-b2b'),
                'type'        => 'select',
                'description' => __('Status applied when an invoice order is placed.', 'woo-b2b'),
                'default'     => 'on-hold',
                'desc_tip'    => true,
                'options'     => [
                    'on-hold'    => __('On hold', 'woo-b2b'),
                    'processing' => __('Processing', 'woo-b2b'),
                    'pending'    => __('Pending payment', 'woo-b2b'),
                ],
            ],
            'terms_days'   => [
                'title'             => __('Payment terms (days)', 'woo-b2b'),
                'type'              => 'number',
                'description'       => __('Net days until the invoice is due. The due date is stored on the order and shown to the customer. Use 0 for no due date.', 'woo-b2b'),
                'default'           => '30',
                'desc_tip'          => true,
                'custom_attributes' => ['min' => '0', 'step' => '1'],
            ],
        ];
    }

    /**
     * Process the payment: place the order on account and store the invoice due date.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if ($order->get_total() > 0) {
            $status = apply_filters('wb2b_invoice_process_payment_order_status', $this->order_status ?: 'on-hold', $order);
            // Mark on account (awaiting invoice payment). The status transition reduces stock.
            $order->update_status($status, __('Order placed on account — awaiting invoice payment.', 'woo-b2b'));
            $this->store_due_date($order);
        } else {
            // Nothing owed — no invoice required.
            $order->payment_complete();
        }

        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    /**
     * Compute and persist the invoice terms + due date on the order (HPOS-safe CRUD).
     *
     * @param WC_Order $order Order object.
     */
    protected function store_due_date($order) {
        $days = absint(apply_filters('wb2b_invoice_terms_days', $this->terms_days, $order));

        $order->update_meta_data(self::META_TERMS_DAYS, $days);

        if ($days > 0) {
            $created = $order->get_date_created();
            $base    = $created ? $created->getTimestamp() : time();
            $order->update_meta_data(self::META_DUE_DATE, wp_date('Y-m-d', $base + ($days * DAY_IN_SECONDS)));
        }

        $order->save();
    }

    /**
     * Localized due-date string for output, or '' when none is stored.
     *
     * @param WC_Order $order Order object.
     * @return string
     */
    protected function get_due_date_display($order) {
        $due = $order->get_meta(self::META_DUE_DATE);
        if (!$due) {
            return '';
        }
        $timestamp = strtotime($due . ' 00:00:00');
        return $timestamp ? wp_date(wc_date_format(), $timestamp) : '';
    }

    /**
     * Output instructions + due date on the order-received page.
     *
     * @param int $order_id Order ID.
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }

        $due = $this->get_due_date_display($order);
        if ($due) {
            echo '<p class="wb2b-invoice-due"><strong>' . esc_html__('Payment due by:', 'woo-b2b') . '</strong> ' . esc_html($due) . '</p>';
        }
    }

    /**
     * Add instructions + due date to customer emails for invoice orders.
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Whether the email is for the admin.
     * @param bool     $plain_text    Plain-text email format.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($sent_to_admin || self::ID !== $order->get_payment_method()) {
            return;
        }

        $expected = apply_filters('wb2b_invoice_email_instructions_order_status', $this->order_status ?: 'on-hold', $order);
        if (!$order->has_status($expected)) {
            return;
        }

        $due = $this->get_due_date_display($order);

        if ($plain_text) {
            if ($this->instructions) {
                echo wp_strip_all_tags(wptexturize($this->instructions)) . PHP_EOL;
            }
            if ($due) {
                echo esc_html__('Payment due by:', 'woo-b2b') . ' ' . esc_html($due) . PHP_EOL;
            }
        } else {
            if ($this->instructions) {
                echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
            }
            if ($due) {
                echo '<p class="wb2b-invoice-due"><strong>' . esc_html__('Payment due by:', 'woo-b2b') . '</strong> ' . esc_html($due) . '</p>';
            }
        }
    }
}
