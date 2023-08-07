<?php

namespace Airwallex;

use Airwallex\Gateways\Card;
use Airwallex\Gateways\CardSubscriptions;
use Airwallex\Gateways\Main as MainGateway;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use AirwallexController;
use Exception;
use WC_Order;

class Main
{
    const ROUTE_SLUG_CONFIRMATION = 'airwallex_payment_confirmation';
    const ROUTE_SLUG_WEBHOOK = 'airwallex_webhook';
    const ROUTE_SLUG_JS_LOGGER = 'airwallex_js_log';

    const OPTION_KEY_MERCHANT_COUNTRY = 'airwallex_merchant_country';

    public static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function getInstanceKey()
    {
        return md5(AUTH_KEY);
    }

    public function init()
    {
        $this->registerEvents();
        $this->registerOrderStatus();
        $this->registerCron();
    }

    public function registerEvents()
    {

        //add_action('admin_init', [$this, 'install']);
        add_filter('woocommerce_get_settings_checkout', [$this, 'addGlobalSettings'], 10, 3);
        add_filter('woocommerce_payment_gateways', [$this, 'addPaymentGateways']);
        add_action('woocommerce_order_status_changed', [$this, 'handleStatusChange'], 10, 4);
        add_action('woocommerce_api_' . Card::ROUTE_SLUG, [new AirwallexController, 'cardPayment']);
        add_action('woocommerce_api_' . MainGateway::ROUTE_SLUG, [new AirwallexController, 'dropInPayment']);
        add_action('woocommerce_api_' . WeChat::ROUTE_SLUG, [new AirwallexController, 'weChatPayment']);
        add_action('woocommerce_api_' . self::ROUTE_SLUG_CONFIRMATION, [new AirwallexController, 'paymentConfirmation']);
        add_action('woocommerce_api_' . self::ROUTE_SLUG_WEBHOOK, [new AirwallexController, 'webhook']);
        if ($this->isJsLoggingActive()) {
            add_action('woocommerce_api_' . self::ROUTE_SLUG_JS_LOGGER, [new AirwallexController, 'jsLog']);
        }
        add_action('woocommerce_api_' . Card::ROUTE_SLUG_ASYNC_INTENT, [new AirwallexController, 'asyncIntent']);
        add_filter('plugin_action_links_' . plugin_basename(AIRWALLEX_PLUGIN_PATH . AIRWALLEX_PLUGIN_NAME . '.php'), [$this, 'addPluginSettingsLink']);
        add_action('airwallex_check_pending_transactions', [$this, 'checkPendingTransactions']);
        add_action('woocommerce_settings_saved', [$this, 'updateMerchantCountryAfterSave']);
        add_action('requests-requests.before_request', [$this, 'modifyRequestsForLogging'], 10, 5);
    }

    public function modifyRequestsForLogging($url, $headers, $data, $type, &$options)
    {
        if (!$options['blocking'] && strpos($url, 'airwallex')) {
            $options['transport'] = 'Requests_Transport_fsockopen';
        }
    }

    public function updateMerchantCountryAfterSave()
    {
        if (empty($_POST['airwallex_client_id']) || empty($_POST['airwallex_api_key'])) {
            return;
        }
        $this->updateMerchantCountry();
    }

    protected function updateMerchantCountry()
    {
        if (empty(get_option('airwallex_client_id')) || empty(get_option('airwallex_api_key'))) {
            return;
        }
        $country = null;
        try {
            $client = new AdminClient(get_option('airwallex_client_id'), get_option('airwallex_api_key'), false);
            $country = $client->getMerchantCountry();
        } catch (Exception $e) {
            //silent
        }
        if (empty($country)) {
            //try in sandbox
            try {
                $client = new AdminClient(get_option('airwallex_client_id'), get_option('airwallex_api_key'), true);
                $country = $client->getMerchantCountry();
            } catch (Exception $e) {
                //silent
            }
        }
        update_option(self::OPTION_KEY_MERCHANT_COUNTRY, $country);
    }

    public function getMerchantCountry()
    {
        $country = get_option(self::OPTION_KEY_MERCHANT_COUNTRY);

        if (empty($country)) {
            $this->updateMerchantCountry();
            $country = get_option(self::OPTION_KEY_MERCHANT_COUNTRY);
        }
        if (empty($country)) {
            $country = get_option('woocommerce_default_country');
        }
        return $country;
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
            register_post_status('wc-airwallex-pending', [
                'label' => __('Airwallex Pending', AIRWALLEX_PLUGIN_NAME),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
            ]);
        });

        add_filter('wc_order_statuses', function ($statusList) {
            $statusList['wc-airwallex-pending'] = __('Airwallex Pending', AIRWALLEX_PLUGIN_NAME);
            $statusList['airwallex-issue'] = __('Airwallex Issue', AIRWALLEX_PLUGIN_NAME);
            return $statusList;
        });

    }

    protected function registerCron()
    {
        $interval = (int)get_option('airwallex_cronjob_interval');
        $interval = ($interval < 3600) ? 3600 : $interval;
        add_action('init', function () use ($interval) {
            if (function_exists('as_schedule_cron_action')) {
                if (!as_next_scheduled_action('airwallex_check_pending_transactions')) {
                    as_schedule_recurring_action(
                        strtotime('midnight tonight'),
                        $interval,
                        'airwallex_check_pending_transactions'
                    );
                }
            }
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
                    'title' => __('Unique Client ID', AIRWALLEX_PLUGIN_NAME),
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
                    'desc' => 'Webhook URL: ' . WC()->api_request_url(Main::ROUTE_SLUG_WEBHOOK),
                    'id' => 'airwallex_webhook_secret',
                    'value' => get_option('airwallex_webhook_secret'),
                ],
                'enable_sandbox' => [
                    'title' => __('Enable sandbox', AIRWALLEX_PLUGIN_NAME),
                    'desc' => __('yes', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'id' => 'airwallex_enable_sandbox',
                    'value' => get_option('airwallex_enable_sandbox'),
                ],
                'temporary_order_status_after_decline' => [
                    'title' => __('Temporary order status after decline during checkout', AIRWALLEX_PLUGIN_NAME),
                    'id' => 'airwallex_temporary_order_status_after_decline',
                    'type' => 'select',
                    'desc' => __('This order status is set, when the payment has been declined and the customer redirected to the checkout page to try again.', AIRWALLEX_PLUGIN_NAME),
                    'options' => [
                        'pending' => _x('Pending payment', 'Order status', 'woocommerce'),
                        'failed' => _x('Failed', 'Order status', 'woocommerce'),
                    ],
                    'value' => get_option('airwallex_temporary_order_status_after_decline'),
                ],
                'order_status_pending' => [
                    'title' => __('Order state for pending payments', AIRWALLEX_PLUGIN_NAME),
                    'id' => 'airwallex_order_status_pending',
                    'type' => 'select',
                    'desc' => __('Certain local payment methods have asynchronous payment confirmations that can take up to a few days. Card payments are always instant.', AIRWALLEX_PLUGIN_NAME),
                    'options' => array_merge(['' => __('[Do not change status]', AIRWALLEX_PLUGIN_NAME)], wc_get_order_statuses()),
                    'value' => get_option('airwallex_order_status_pending'),
                ],
                'order_status_authorized' => [
                    'title' => __('Order state for authorized payments', AIRWALLEX_PLUGIN_NAME),
                    'id' => 'airwallex_order_status_authorized',
                    'type' => 'select',
                    'desc' => __('Status for orders that are authorized but not captured', AIRWALLEX_PLUGIN_NAME),
                    'options' => array_merge(['' => __('[Do not change status]', AIRWALLEX_PLUGIN_NAME)], wc_get_order_statuses()),
                    'value' => get_option('airwallex_order_status_authorized'),
                ],
                'cronjob_interval' => [
                    'title' => __('Cronjob Interval', AIRWALLEX_PLUGIN_NAME),
                    'id' => 'airwallex_cronjob_interval',
                    'type' => 'select',
                    'desc' => '',
                    'options' => [
                        '3600' => __('every hour (recommended)', AIRWALLEX_PLUGIN_NAME),
                        '14400' => __('every 4 hours', AIRWALLEX_PLUGIN_NAME),
                        '28800' => __('every 8 hours', AIRWALLEX_PLUGIN_NAME),
                        '43200' => __('every 12 hours', AIRWALLEX_PLUGIN_NAME),
                    ],
                    'value' => get_option('airwallex_cronjob_interval'),
                ],
                'do_js_logging' => [
                    'title' => __('Activate JS logging', AIRWALLEX_PLUGIN_NAME),
                    'desc' => __('yes (only for special cases after contacting Airwallex)', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'id' => 'airwallex_do_js_logging',
                    'value' => get_option('airwallex_do_js_logging'),
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
        $gateways[] = MainGateway::class;
        if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
            $gateways[] = CardSubscriptions::class;
        } else {
            $gateways[] = Card::class;
        }
        $gateways[] = WeChat::class;
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

    public function checkPendingTransactions()
    {
        (new OrderService())->checkPendingTransactions();
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

    public function isJsLoggingActive()
    {
        return in_array(get_option('airwallex_do_js_logging'), ['yes', 1, true, '1'], true);
    }

    public function addJsLegacy()
    {
        $isCheckout = is_checkout();
        $cardGateway = new Card();
        $jsUrl = 'https://checkout.airwallex.com/assets/elements.bundle.min.js';
        $jsUrlLocal = AIRWALLEX_PLUGIN_URL . '/assets/js/airwallex-local.js';
        $cssUrl = AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout.css';

        $confirmationUrl = $cardGateway->get_payment_confirmation_url();
        $confirmationUrl .= (strpos($confirmationUrl, '?') === false) ? '?' : '&';

        $inlineScript = '
            const AirwallexParameters = {
                asyncIntentUrl: \'' . $cardGateway->get_async_intent_url() . '\',
                confirmationUrl: \'' . $confirmationUrl . '\'
            };';
        if (isset($_GET['pay_for_order']) && 'true' === $_GET['pay_for_order']) {
            global $wp;
            if ($order_id = (int)$wp->query_vars['order-pay']) {
                $order = wc_get_order($order_id);
                if (is_a($order, 'WC_Order')) {
                    $inlineScript .= 'AirwallexParameters.billingFirstName = ' . json_encode($order->get_billing_first_name()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingLastName = ' . json_encode($order->get_billing_last_name()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingAddress1 = ' . json_encode($order->get_billing_address_1()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingAddress2 = ' . json_encode($order->get_billing_address_2()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingState = ' . json_encode($order->get_billing_state()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingCity = ' . json_encode($order->get_billing_city()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingPostcode = ' . json_encode($order->get_billing_postcode()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingCountry = ' . json_encode($order->get_billing_country()) . ';';
                    $inlineScript .= 'AirwallexParameters.billingEmail = ' . json_encode($order->get_billing_email()) . ';';
                }
            }
        }

        if ($this->isJsLoggingActive()) {
            $loggingInlineScript = "\nconst airwallexJsLogUrl = '" . WC()->api_request_url(Main::ROUTE_SLUG_JS_LOGGER) . "';";
            wp_enqueue_script('airwallex-js-logging-js', AIRWALLEX_PLUGIN_URL . '/assets/js/jsnlog.js', [], false, false);
            wp_add_inline_script('airwallex-js-logging-js', $loggingInlineScript);

        }

        if (!$isCheckout) {
            //separate pages for cc and wechat payment
            define('AIRWALLEX_INLINE_JS', $inlineScript);
            return;
        }

        wp_enqueue_script('airwallex-lib-js', $jsUrl, [], false, true);
        wp_enqueue_script('airwallex-local-js', $jsUrlLocal, [], false, true);

        wp_enqueue_style('airwallex-css', $cssUrl);
        $errorMessage = __('An error has occurred. Please check your payment details (%s)', AIRWALLEX_PLUGIN_NAME);
        $incompleteMessage = __('Your credit card details are incomplete', AIRWALLEX_PLUGIN_NAME);
        $environment = $cardGateway->is_sandbox() ? 'demo' : 'prod';
        $autoCapture = $cardGateway->is_capture_immediately() ? 'true' : 'false';
        $airwallexOrderId = absint(get_query_var('order-pay'));
        $locale = \Airwallex\Services\Util::getLocale();
        $inlineScript .= <<<AIRWALLEX

    const airwallexCheckoutProcessingAction = function (msg) {
        if (msg && msg.indexOf('<!--Airwallex payment processing-->') !== -1) {
            confirmSlimCardPayment();
        }
    }
    
    jQuery(document.body).on('checkout_error', function (e, msg) {
        airwallexCheckoutProcessingAction(msg);
    });
    
    //for plugin CheckoutWC
    window.addEventListener('cfw-checkout-failed-before-error-message', function (event) {
        if (typeof event.detail.response.messages === 'undefined') {
            return;
        }
        airwallexCheckoutProcessingAction(event.detail.response.messages);
    });
    
    //this is for payment changes after order placement
    jQuery('#order_review').on('submit', function (e) {
        let airwallexCardPaymentOption = jQuery('#payment_method_airwallex_card');
        if (airwallexCardPaymentOption.length && airwallexCardPaymentOption.is(':checked')) {
            if (jQuery('#airwallex-card').length) {
                e.preventDefault();
                confirmSlimCardPayment($airwallexOrderId);
            }
        }
    });
    
    Airwallex.init({
        env: '$environment',
        locale: '$locale',
        origin: window.location.origin, // Setup your event target to receive the browser events message
    });
    
    const airwallexSlimCard = Airwallex.createElement('card');
    
    airwallexSlimCard.mount('airwallex-card');
    setInterval(function(){
        if(document.getElementById('airwallex-card') && !document.querySelector('#airwallex-card iframe')){
            try{
                airwallexSlimCard.mount('airwallex-card')
            }catch{
            
            }
        }
    }, 1000);
    
    function confirmSlimCardPayment(orderId) {
        //timeout necessary because of event order in plugin CheckoutWC
        setTimeout(function(){
            jQuery('form.checkout').block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
            });
        }, 50);
        
        let asyncIntentUrl = AirwallexParameters.asyncIntentUrl;
        if(orderId){
            asyncIntentUrl += (asyncIntentUrl.indexOf('?') !== -1 ? '&' : '?') + 'airwallexOrderId=' + orderId;
        }
        
        AirwallexClient.ajaxGet(asyncIntentUrl, function (data) {
            if (!data || data.error) {
                AirwallexClient.displayCheckoutError(String('$errorMessage').replace('%s', ''));
            }
            const finalConfirmationUrl = AirwallexParameters.confirmationUrl + 'order_id=' + data.orderId + '&intent_id=' + data.paymentIntent;
            if(data.createConsent){
                Airwallex.createPaymentConsent({
                    intent_id: data.paymentIntent,
                    customer_id: data.customerId,
                    client_secret: data.clientSecret,
                    currency: data.currency,
                    element: airwallexSlimCard,
                    next_triggered_by: 'merchant'
                }).then((response) => {
                    location.href = finalConfirmationUrl;
                }).catch(err => {
                    console.log(err);
                    jQuery('form.checkout').unblock();
                    AirwallexClient.displayCheckoutError(String('$errorMessage').replace('%s', err.message || ''));
                });
            }else{
                Airwallex.confirmPaymentIntent({
                    element: airwallexSlimCard,
                    id: data.paymentIntent,
                    client_secret: data.clientSecret,
                    payment_method: {
                        card: {
                            name: AirwallexClient.getCardHolderName()
                        },
                        billing: AirwallexClient.getBillingInformation()
                    },
                    payment_method_options: {
                        card: {
                            auto_capture: $autoCapture,
                        },
                    }
                }).then((response) => {
                    location.href = finalConfirmationUrl;
                }).catch(err => {
                    console.log(err);
                    jQuery('form.checkout').unblock();
                    AirwallexClient.displayCheckoutError(String('$errorMessage').replace('%s', err.message || ''));
                })
            }
            
        });
    }
    
    window.addEventListener('onError', (event) => {
        if (!event.detail) {
            return;
        }
        const {error} = event.detail;
        AirwallexClient.displayCheckoutError(String('$errorMessage').replace('%s', error.message || ''));
    });
AIRWALLEX;
        wp_add_inline_script('airwallex-local-js', $inlineScript);
    }
}
