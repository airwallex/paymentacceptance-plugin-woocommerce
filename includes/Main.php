<?php

namespace Airwallex;

use Airwallex\Gateways\Card;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\LogService;
use AirwallexController;
use Exception;
use WC_Order;

class Main
{
    const ROUTE_SLUG_CONFIRMATION = 'airwallex_payment_confirmation';
    const ROUTE_SLUG_WEBHOOK = 'airwallex_webhook';
    public static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        $this->registerEvents();
        $this->registerOrderStatus();
    }

    public function registerEvents()
    {

        //add_action('admin_init', [$this, 'install']);
        add_filter('woocommerce_get_settings_checkout', [$this, 'addGlobalSettings'], 10, 3);
        add_filter('woocommerce_payment_gateways', [$this, 'addPaymentGateways']);
        add_action('woocommerce_order_status_changed', [$this, 'handleStatusChange'], 10, 4);
        add_action('woocommerce_api_' . Card::ROUTE_SLUG, [new AirwallexController, 'cardPayment']);
        add_action('woocommerce_api_' . WeChat::ROUTE_SLUG, [new AirwallexController, 'weChatPayment']);
        add_action('woocommerce_api_' . self::ROUTE_SLUG_CONFIRMATION, [new AirwallexController, 'paymentConfirmation']);
        add_action('woocommerce_api_' . self::ROUTE_SLUG_WEBHOOK, [new AirwallexController, 'webhook']);
        add_action('woocommerce_api_' . Card::ROUTE_SLUG_ASYNC_INTENT, [new AirwallexController, 'asyncIntent']);
        add_filter('plugin_action_links_' . plugin_basename(AIRWALLEX_PLUGIN_PATH . AIRWALLEX_PLUGIN_NAME . '.php'), [$this, 'addPluginSettingsLink']);
    }

    protected function registerOrderStatus()
    {

        add_filter('init', function () {
            register_post_status('airwallex-issue', [
                'label' => __('Airwallex Issue', AIRWALLEX_PLUGIN_NAME),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
            ]);
        });

        add_filter('wc_order_statuses', function ($statusList) {
            $statusList['airwallex-issue'] = __('Airwallex Issue', AIRWALLEX_PLUGIN_NAME);
            return $statusList;
        });

    }

    public function addPluginSettingsLink($links)
    {
        $settingsLink = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=airwallex_general') . '">' . __('Airwallex API settings', AIRWALLEX_PLUGIN_NAME) . '</a>';
        array_unshift($links, $settingsLink);
        return $links;
    }

    public function addGlobalSettings($settings, $currentSection)
    {
        if ($currentSection === 'airwallex_general') {
            $settings = [
                'title' => [
                    //'title' => __('Airwallex API settings', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'title',
                    'desc' => '<img src="' . AIRWALLEX_PLUGIN_URL . '/assets/images/logo.svg" width="150" alt="Airwallex" /><br>
                                <h2>API settings</h2>
                                <br>Please enter your Airwallex credentials
                                <br><br>
                                <a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">Back to payment overview</a>
                                <br>
                                <br>',
                ],
                'client_id' => [
                    'title' => __('Client ID', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'desc' => '',
                    'id' => 'airwallex_client_id',
                    'value' => get_option('airwallex_client_id'),
                ],
                'api_key' => [
                    'title' => __('API Key', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'desc' => '',
                    'id' => 'airwallex_api_key',
                    'value' => get_option('airwallex_api_key'),
                ],
                'webhook_secret' => [
                    'title' => __('Webhook Secret Key', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'password',
                    'desc' => 'Webhook URL: ' . get_home_url() . '/wc-api/' . Main::ROUTE_SLUG_WEBHOOK . '/',
                    'id' => 'airwallex_webhook_secret',
                    'value' => get_option('airwallex_webhook_secret'),
                ],
                'submit_order_details' => [
                    'title' => __('Submit order details', AIRWALLEX_PLUGIN_NAME),
                    'desc' => __('yes', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'id' => 'airwallex_submit_order_details',
                    'value' => get_option('airwallex_submit_order_details'),
                ],

                'sectionend' => [
                    'type' => 'sectionend',
                ],
            ];
        }
        return $settings;
    }

    public function addPaymentGateways($gateways)
    {
        $gateways[] = '\\Airwallex\\Gateways\\Card';
        $gateways[] = '\\Airwallex\\Gateways\\WeChat';
        return $gateways;
    }

    /**
     * @param $orderId
     * @param $statusFrom
     * @param $statusTo
     * @param WC_Order $order
     */
    public function handleStatusChange($orderId, $statusFrom, $statusTo, $order)
    {
        $this->_handleStatusChangeForCard($statusTo, $order);

    }

    /**
     * @param $statusTo
     * @param WC_Order $order
     */
    private function _handleStatusChangeForCard($statusTo, $order)
    {
        $cardGateway = new Card();

        if ($order->get_payment_method() !== $cardGateway->id) {
            return;
        }

        if ($cardGateway->is_capture_immediately()) {
            return;
        }

        if ($statusTo === $cardGateway->get_option('capture_trigger_order_status') || 'wc-' . $statusTo === $cardGateway->get_option('capture_trigger_order_status')) {
            try {
                if (!$cardGateway->is_captured($order)) {
                    $cardGateway->capture($order);
                } else {
                    (new LogService())->debug('skip capture by status change because order is already captured', $order);
                }
            } catch (Exception $e) {
                (new LogService())->error('capture by status error', $e->getMessage());
                $order->add_order_note('ERROR: ' . $e->getMessage());
            }
        }
    }
}