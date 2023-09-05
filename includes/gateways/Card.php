<?php

namespace Airwallex\Gateways;

use Airwallex\CardClient;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;
use WC_HTTPS;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Card extends WC_Payment_Gateway
{
    use AirwallexGatewayTrait;

    const ROUTE_SLUG = 'airwallex_card';
    const ROUTE_SLUG_WECHAT = 'airwallex_wechat';
    const ROUTE_SLUG_ASYNC_INTENT = 'airwallex_async_intent';
    const GATEWAY_ID = 'airwallex_card';
    public $method_title = 'Airwallex - Cards';
    public $method_description;
    public $title = 'Airwallex - Cards';
    public $description = '';
    public $icon = AIRWALLEX_PLUGIN_URL . '/assets/images/airwallex_cc_icon.svg';
    public $id = self::GATEWAY_ID;
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];
    public $logService;

    public function __construct()
    {

        $this->plugin_id = AIRWALLEX_PLUGIN_NAME;
        $this->init_settings();
        $this->description = $this->get_option('description') ?: ($this->get_option('checkout_form_type') === 'inline' ? '<!-- -->' : '');
        if ($this->get_client_id() && $this->get_api_key()) {
            $this->method_description = __('Accept only credit and debit card payments with your Airwallex account.', AIRWALLEX_PLUGIN_NAME);
            $this->form_fields = $this->get_form_fields();
        } else {
            $this->method_description = '<div class="error" style="padding:10px;">' . sprintf(__('To start using Airwallex payment methods, please enter your credentials first. <br><a href="%s" class="button-primary">API settings</a>', AIRWALLEX_PLUGIN_NAME), admin_url('admin.php?page=wc-settings&tab=checkout&section=airwallex_general')) . '</div>';
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        $this->title = $this->get_option('title');
        $this->logService = new LogService();
    }

    public function getCardLogos()
    {
        $cacheService = new CacheService($this->get_api_key());
        $logos = $cacheService->get('cardLogos');
        if (empty($logos)) {
            $apiClient = CardClient::getInstance();
            if ($paymentMethodTypes = $apiClient->getPaymentMethodTypes()) {
                $logos = [];
                foreach ($paymentMethodTypes as $paymentMethodType) {
                    if ($paymentMethodType['name'] === 'card' && empty($logos)) {
                        foreach ($paymentMethodType['card_schemes'] as $cardType) {
                            if (isset($cardType['resources']['logos']['svg'])) {
                                $logos['card_'.$cardType['name']] = $cardType['resources']['logos']['svg'];
                            }
                        }
                    }
                }
                $logos = $this->sort_icons($logos);
                $cacheService->set('cardLogos', $logos, 86400);
            }
        }
        return array_reverse($logos);
    }

    public function get_icon()
    {
        $return = '';
        if ($logos = $this->getCardLogos()) {
            foreach ($logos as $logo) {
                $return .= '<img src="' . WC_HTTPS::force_https_url($logo) . '" class="airwallex-card-icon" alt="' . esc_attr($this->get_title()) . '" />';
            }
            apply_filters('woocommerce_gateway_icon', $return, $this->id);
            return $return;
        } else {
            return parent::get_icon();
        }
    }

    public function payment_fields()
    {
        if ($this->get_option('checkout_form_type') === 'inline') {
            echo '<p>' . $this->description . '</p>';
            echo '<div id="airwallex-card"></div>';
        } else {
            parent::payment_fields();
        }
    }

    public function get_async_intent_url()
    {
        $url = \WooCommerce::instance()->api_request_url(self::ROUTE_SLUG_ASYNC_INTENT);
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'request_id=' . uniqid();
        return $url;
    }

    public function get_form_fields()
    {
        $isEmbeddedFieldsAllowed = (defined('WC_VERSION') && version_compare( WC_VERSION, '4.8.0', '>='));
        return apply_filters(
            'wc_airwallex_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', AIRWALLEX_PLUGIN_NAME),
                    'label' => __('Enable Airwallex Card Payments', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', AIRWALLEX_PLUGIN_NAME),
                    'default' => __('Credit Card', AIRWALLEX_PLUGIN_NAME),
                ],
                'description' => [
                    'title' => __('Description', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', AIRWALLEX_PLUGIN_NAME),
                    'default' => '',
                ],
                'checkout_form_type' => [
                    'title' => __('Checkout Form', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'select',
                    'description' => (!$isEmbeddedFieldsAllowed?' '.__('Please upgrade WooCommerce to 4.8.0+ to use embedded credit card input fields', AIRWALLEX_PLUGIN_NAME):''),
                    'default' => $isEmbeddedFieldsAllowed?'inline':'redirect',
                    'options' =>
                        ($isEmbeddedFieldsAllowed?['inline' => __('Embedded', AIRWALLEX_PLUGIN_NAME)]:[])
                        +['redirect' => __('On separate page', AIRWALLEX_PLUGIN_NAME)],
                ],
                'payment_descriptor' => [
                    'title' => __('Statement descriptor', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'custom_attributes' => [
                        'maxlength' => 28,
                    ],
                    'description' => __('Descriptor that will be displayed to the customer. For example, in customer\'s credit card statement. Use %order% as a placeholder for the order\'s ID.', AIRWALLEX_PLUGIN_NAME),
                    'default' => __('Your order %order%', AIRWALLEX_PLUGIN_NAME),
                ],
                'capture_immediately' => [
                    'title' => __('Capture immediately', AIRWALLEX_PLUGIN_NAME),
                    'label' => __('yes', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => __('Choose this option if you do not want to rely on status changes for capturing the payment', AIRWALLEX_PLUGIN_NAME),
                    'default' => 'yes',
                ],
                'capture_trigger_order_status' => [
                    'title' => __('Capture status', AIRWALLEX_PLUGIN_NAME),
                    'label' => '',
                    'type' => 'select',
                    'description' => __('When this status is assigned to an order, the funds will be captured', AIRWALLEX_PLUGIN_NAME),
                    'options' => array_merge(['' => ''], wc_get_order_statuses()),
                    'default' => ''
                ],
            ]
        );
    }

    public function process_payment($order_id)
    {
        $return = [
            'result' => 'success',
        ];
        WC()->session->set('airwallex_order', $order_id);
        if ($this->get_option('checkout_form_type') === 'redirect') {
            $return['redirect'] = $this->get_payment_url();
        } else {
            $return['messages'] = '<!--Airwallex payment processing-->';
        }
        return $return;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        $paymentIntentId = $order->get_transaction_id();
        $apiClient = CardClient::getInstance();
        try {
            $refund = $apiClient->createRefund($paymentIntentId, $amount, $reason);
            $metaKey = $refund->getMetaKey();
            if (!$order->meta_exists($metaKey)) {
                $order->add_order_note(sprintf(
                    __('Airwallex refund initiated: %s', AIRWALLEX_PLUGIN_NAME),
                    $refund->getId()
                ));
                add_post_meta($order->get_id(), $metaKey, ['status' => Refund::STATUS_CREATED]);
            } else {
                throw new Exception("refund {$refund->getId()} already exist.", '1');
            }
            $this->logService->debug(__METHOD__ . " - Order: {$order_id}, refund initiated, {$refund->getId()}");
        } catch (\Exception $e) {
            $this->logService->debug(__METHOD__ . " - Order: {$order_id}, refund failed, {$e->getMessage()}");
            return new WP_Error($e->getCode(), 'Refund failed, ' . $e->getMessage());
        }

        return true;
    }

    /**
     * @param WC_Order $order
     * @param float $amount
     * @throws Exception
     */
    public function capture(WC_Order $order, $amount = null)
    {
        $apiClient = CardClient::getInstance();
        $paymentIntentId = $order->get_transaction_id();
        if (empty($paymentIntentId)) {
            throw new Exception('No Airwallex payment intent found for this order: ' . $order->get_id());
        }
        if ($amount === null) {
            $amount = $order->get_total();
        }
        $paymentIntentAfterCapture = $apiClient->capture($paymentIntentId, $amount);
        if ($paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED) {
            (new LogService())->debug('capture successful', $paymentIntentAfterCapture->toArray());
            $order->add_order_note('Airwallex payment capture success');
        } else {
            (new LogService())->error('capture failed', $paymentIntentAfterCapture->toArray());
            $order->add_order_note('Airwallex payment failed capture');
        }
    }

    public function is_captured($order)
    {
        $apiClient = CardClient::getInstance();
        $paymentIntentId = $order->get_transaction_id();
        $paymentIntent = $apiClient->getPaymentIntent($paymentIntentId);
        if ($paymentIntent->getStatus() === PaymentIntent::STATUS_SUCCEEDED) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function is_capture_immediately()
    {
        return in_array($this->get_option('capture_immediately'), [true, 'yes'], true);
    }
}
