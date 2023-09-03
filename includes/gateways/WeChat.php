<?php

namespace Airwallex\Gateways;

use Airwallex\Services\LogService;
use Airwallex\Struct\Refund;
use Airwallex\WeChatClient;
use Exception;
use WC_Payment_Gateway;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class WeChat extends WC_Payment_Gateway
{
    use AirwallexGatewayTrait;

    const ROUTE_SLUG = 'airwallex_wechat';
    public $method_title = 'Airwallex - WeChat Pay';
    public $method_description = '';
    public $title = 'Airwallex - WeChat Pay';
    public $description = '';
    public $icon = '';
    public $id = 'airwallex_wechat';
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
        $this->description = $this->get_option('description');
        if($this->get_client_id() && $this->get_api_key()){
            $this->method_description = __('Accept only WeChat Pay payments with your Airwallex account.', AIRWALLEX_PLUGIN_NAME);
            $this->form_fields = $this->get_form_fields();
        }else{
            $this->method_description = '<div class="error" style="padding:10px;">'.sprintf(__('To start using Airwallex payment methods, please enter your credentials first. <br><a href="%s" class="button-primary">API settings</a>', AIRWALLEX_PLUGIN_NAME), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=airwallex_general' )).'</div>';
        }
        $this->title = $this->get_option('title');
        $this->logService = new LogService();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }



    public function get_form_fields()
    {
        return apply_filters(
            'wc_airwallex_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', AIRWALLEX_PLUGIN_NAME),
                    'label' => __('Enable Airwallex WeChat Pay', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', AIRWALLEX_PLUGIN_NAME),
                    'default' => __('WeChat Pay', AIRWALLEX_PLUGIN_NAME),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Description', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', AIRWALLEX_PLUGIN_NAME),
                    'default' => '',
                    'desc_tip' => true,
                ],
            ]
        );
    }

    public function process_payment($order_id)
    {
        $return = [
            'result' => 'success'
        ];
        WC()->session->set('airwallex_order', $order_id);
        $return['redirect'] = $this->get_payment_url();
        return $return;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        $paymentIntentId = $order->get_transaction_id();
        $apiClient = WeChatClient::getInstance();
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
}
