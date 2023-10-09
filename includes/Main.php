<?php

namespace Airwallex;

use Airwallex\Gateways\Card;
use Airwallex\Gateways\CardSubscriptions;
use Airwallex\Gateways\Main as MainGateway;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use Airwallex\Gateways\Blocks\AirwallexCardWCBlockSupport;
use AirwallexController;
use Airwallex\Gateways\Blocks\AirwallexMainWCBlockSupport;
use Airwallex\Gateways\Blocks\AirwallexWeChatWCBlockSupport;
use Exception;
use WC_Order;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

class Main
{
    const ROUTE_SLUG_CONFIRMATION = 'airwallex_payment_confirmation';
    const ROUTE_SLUG_WEBHOOK = 'airwallex_webhook';
    const ROUTE_SLUG_JS_LOGGER = 'airwallex_js_log';

    const OPTION_KEY_MERCHANT_COUNTRY = 'airwallex_merchant_country';

    const AWX_PAGE_ID_CACHE_KEY = 'airwallex_page_ids';

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
        add_action('wp_loaded', [$this, 'createPages']);
        add_action('wp_loaded', function() {
            add_shortcode('airwallex_payment_method_card', [new Card, 'output']);
            add_shortcode('airwallex_payment_method_wechat', [new WeChat, 'output']);
            add_shortcode('airwallex_payment_method_all', [new MainGateway, 'output']);
        });
        add_filter('display_post_states', [$this, 'addDisplayPostStates'], 10, 2);
        if (!is_admin()) {
            add_filter('wp_get_nav_menu_items', [$this, 'excludePagesFromMenu'], 10, 3);
            add_filter('wp_list_pages_excludes', [$this, 'excludePagesFromList'], 10, 1);   
        }
        add_action('woocommerce_blocks_loaded', [$this, 'woocommerceBlockSupport']);
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
                'label' => __('Airwallex Issue', 'airwallex-online-payments-gateway'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
            ]);
            register_post_status('wc-airwallex-pending', [
                'label' => __('Airwallex Pending', 'airwallex-online-payments-gateway'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
            ]);
        });

        add_filter('wc_order_statuses', function ($statusList) {
            $statusList['wc-airwallex-pending'] = __('Airwallex Pending', 'airwallex-online-payments-gateway');
            $statusList['airwallex-issue'] = __('Airwallex Issue', 'airwallex-online-payments-gateway');
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
  
    /**
     * Exclude airwallex payment pages from menu
     *
     * @param  array  $items An array of menu item post objects.
	 * @param  object $menu  The menu object.
	 * @param  array  $args  An array of arguments used to retrieve menu item objects.
     * @return array  Menu item list exclude airwallex payment pages
     */
    public function excludePagesFromMenu($items, $menu, $args) {
        $cacheService = new CacheService();
        $excludePageIds = explode(',', $cacheService->get(self::AWX_PAGE_ID_CACHE_KEY));
        foreach ($items as $key => $item) {
            if (in_array($item->object_id, $excludePageIds)) {
                unset($items[$key]);
            }
        }

        return $items;
    }
    
    /**
     * Exclude airwallex payment pages from default menu
     *
     * @param  array $exclude_array An array of page IDs to exclude.
     * @return array Page list exclude airwallex payment pages
     */
    public function excludePagesFromList($excludeArray) {
        $cacheService = new CacheService();
        $excludePageIds = explode(',', $cacheService->get(self::AWX_PAGE_ID_CACHE_KEY));
        if (is_array($excludePageIds)) {
            $excludeArray += $excludePageIds;
        }

        return $excludeArray;
    }
        
    /**
     * Create pages that the plugin relies on, storing page IDs in variables.
     */
    public function createPages() {
        // Set the locale to the store locale to ensure pages are created in the correct language.
        wc_switch_to_site_locale();

        include_once WC()->plugin_path() . '/includes/admin/wc-admin-functions.php';

        $cardShortcode = 'airwallex_payment_method_card';
        $wechatShortcode = 'airwallex_payment_method_wechat';
        $allShortcode = 'airwallex_payment_method_all';

		$pages = [
				'payment_method_card' => [
					'name' => _x('airwallex_payment_method_card', 'Page slug', 'airwallex-online-payments-gateway'),
					'title' => _x('Payment', 'Page title', 'airwallex-online-payments-gateway'),
					'content' => '<!-- wp:shortcode -->[' . $cardShortcode . ']<!-- /wp:shortcode -->',
                ],
				'payment_method_wechat'           => [
					'name' => _x('airwallex_payment_method_wechat', 'Page slug', 'airwallex-online-payments-gateway'),
					'title' => _x('Payment', 'Page title', 'airwallex-online-payments-gateway'),
					'content' => '<!-- wp:shortcode -->[' . $wechatShortcode . ']<!-- /wp:shortcode -->',
                ],
				'payment_method_all'       => [
					'name' => _x('airwallex_payment_method_all', 'Page slug', 'airwallex-online-payments-gateway'),
					'title' => _x('Payment', 'Page title', 'airwallex-online-payments-gateway'),
					'content' => '<!-- wp:shortcode -->[' . $allShortcode . ']<!-- /wp:shortcode -->',
                ],
            ];
        
        $pageIds = [];
		foreach ( $pages as $key => $page ) {
            $pageIds[] = wc_create_page(
                esc_sql( $page['name'] ),
                'airwallex_' . $key . '_page_id',
                $page['title'],
                $page['content']
            );
		}
        
        $pageIdStr = implode(',', $pageIds);
        $cacheService = new CacheService();
        if ($cacheService->get(self::AWX_PAGE_ID_CACHE_KEY) != $pageIdStr) {
            $cacheService->set(self::AWX_PAGE_ID_CACHE_KEY, $pageIdStr, 0);
        }

		// Restore the locale to the default locale.
		wc_restore_locale();
    }

    /**
	 * Add a post display state for special Airwallex pages in the page list table.
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 */
    public function addDisplayPostStates($post_states, $post) {
        if (get_option('airwallex_payment_method_card_page_id') == $post->ID) {
            $post_states['awx_page_for_card_method'] = __('Airwallex - Cards', 'airwallex-online-payments-gateway');
        } elseif (get_option('airwallex_payment_method_wechat_page_id') == $post->ID) {
            $post_states['awx_page_for_wechat_method'] = __('Airwallex - WeChat Pay', 'airwallex-online-payments-gateway');
        } elseif (get_option('airwallex_payment_method_all_page_id') == $post->ID) {
            $post_states['awx_page_for_all_method'] = __('Airwallex - All Payment Methods', 'airwallex-online-payments-gateway');
        }

        return $post_states;
    }

    public function addPluginSettingsLink($links)
    {
        $settingsLink = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=airwallex_general') . '">' . __('Airwallex API settings', 'airwallex-online-payments-gateway') . '</a>';
        array_unshift($links, $settingsLink);
        return $links;
    }

    public function addGlobalSettings($settings, $currentSection)
    {
        if ($currentSection === 'airwallex_general') {
            $settings = [
                'title' => [
                    //'title' => __('Airwallex API settings', 'airwallex-online-payments-gateway'),
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
                    'title' => __('Unique client ID', 'airwallex-online-payments-gateway'),
                    'type' => 'text',
                    'desc' => '',
                    'id' => 'airwallex_client_id',
                    'value' => get_option('airwallex_client_id'),
                ],
                'api_key' => [
                    'title' => __('API key', 'airwallex-online-payments-gateway'),
                    'type' => 'text',
                    'desc' => '',
                    'id' => 'airwallex_api_key',
                    'value' => get_option('airwallex_api_key'),
                ],
                'webhook_secret' => [
                    'title' => __('Webhook secret key', 'airwallex-online-payments-gateway'),
                    'type' => 'password',
                    'desc' => 'Webhook URL: ' . WC()->api_request_url(Main::ROUTE_SLUG_WEBHOOK),
                    'id' => 'airwallex_webhook_secret',
                    'value' => get_option('airwallex_webhook_secret'),
                ],
                'enable_sandbox' => [
                    'title' => __('Enable sandbox', 'airwallex-online-payments-gateway'),
                    'desc' => __('Yes', 'airwallex-online-payments-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'id' => 'airwallex_enable_sandbox',
                    'value' => get_option('airwallex_enable_sandbox'),
                ],
                'temporary_order_status_after_decline' => [
                    'title' => __('Temporary order status after decline during checkout', 'airwallex-online-payments-gateway'),
                    'id' => 'airwallex_temporary_order_status_after_decline',
                    'type' => 'select',
                    'desc' => __('This order status is set, when the payment has been declined and the customer redirected to the checkout page to try again.', 'airwallex-online-payments-gateway'),
                    'options' => [
                        'pending' => _x('Pending payment', 'Order status', 'woocommerce'),
                        'failed' => _x('Failed', 'Order status', 'woocommerce'),
                    ],
                    'value' => get_option('airwallex_temporary_order_status_after_decline'),
                ],
                'order_status_pending' => [
                    'title' => __('Order state for pending payments', 'airwallex-online-payments-gateway'),
                    'id' => 'airwallex_order_status_pending',
                    'type' => 'select',
                    'desc' => __('Certain local payment methods have asynchronous payment confirmations that can take up to a few days. Card payments are always instant.', 'airwallex-online-payments-gateway'),
                    'options' => array_merge(['' => __('[Do not change status]', 'airwallex-online-payments-gateway')], wc_get_order_statuses()),
                    'value' => get_option('airwallex_order_status_pending'),
                ],
                'order_status_authorized' => [
                    'title' => __('Order state for authorized payments', 'airwallex-online-payments-gateway'),
                    'id' => 'airwallex_order_status_authorized',
                    'type' => 'select',
                    'desc' => __('Status for orders that are authorized but not captured', 'airwallex-online-payments-gateway'),
                    'options' => array_merge(['' => __('[Do not change status]', 'airwallex-online-payments-gateway')], wc_get_order_statuses()),
                    'value' => get_option('airwallex_order_status_authorized'),
                ],
                'cronjob_interval' => [
                    'title' => __('Cronjob interval', 'airwallex-online-payments-gateway'),
                    'id' => 'airwallex_cronjob_interval',
                    'type' => 'select',
                    'desc' => '',
                    'options' => [
                        '3600' => __('Every hour (recommended)', 'airwallex-online-payments-gateway'),
                        '14400' => __('Every 4 hours', 'airwallex-online-payments-gateway'),
                        '28800' => __('Every 8 hours', 'airwallex-online-payments-gateway'),
                        '43200' => __('Every 12 hours', 'airwallex-online-payments-gateway'),
                    ],
                    'value' => get_option('airwallex_cronjob_interval'),
                ],
                'do_js_logging' => [
                    'title' => __('Activate JS logging', 'airwallex-online-payments-gateway'),
                    'desc' => __('Yes (only for special cases after contacting Airwallex)', 'airwallex-online-payments-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'id' => 'airwallex_do_js_logging',
                    'value' => get_option('airwallex_do_js_logging'),
                ],
                'do_remote_logging' => [
                    'title' => __('Activate remote logging', 'airwallex-online-payments-gateway'),
                    'desc' => __('Send diagnostic data to Airwallex', 'airwallex-online-payments-gateway') . '<br/><small>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . __('Help Airwallex easily resolve your issues and improve your experience by automatically sending diagnostic data. Diagnostic data may include order details.', 'airwallex-online-payments-gateway') . '</small>',
                    'type' => 'checkbox',
                    'default' => '',
                    'id' => 'airwallex_do_remote_logging',
                    'value' => get_option('airwallex_do_remote_logging'),
                ],
                'payment_page_template' => [
                    'title' => __('Payment form template', 'airwallex-online-payments-gateway'),
                    'id' => 'airwallex_payment_page_template',
                    'type' => 'select',
                    'desc' => '',
                    'options' => [
                        'default' => __('Default', 'airwallex-online-payments-gateway'),
                        'wordpress_page' => __('WordPress page shortcodes', 'airwallex-online-payments-gateway'),
                    ],
                    'value' => get_option('airwallex_payment_page_template'),
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
        wp_enqueue_script('airwallex-local-js', $jsUrlLocal, [], AIRWALLEX_VERSION, true);

        wp_enqueue_style('airwallex-css', $cssUrl, [], AIRWALLEX_VERSION);
        $errorMessage = __('An error has occurred. Please check your payment details (%s)', 'airwallex-online-payments-gateway');
        $incompleteMessage = __('Your credit card details are incomplete', 'airwallex-online-payments-gateway');
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

    public function woocommerceBlockSupport() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function(PaymentMethodRegistry $payment_method_registry) {
                  $payment_method_registry->register(new AirwallexMainWCBlockSupport());
                  $payment_method_registry->register(new AirwallexCardWCBlockSupport());
                  $payment_method_registry->register(new AirwallexWeChatWCBlockSupport());
                }
            );
        }
    }
}
