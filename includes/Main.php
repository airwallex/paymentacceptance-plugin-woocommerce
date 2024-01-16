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
use Airwallex\Gateways\Blocks\AirwallexMainWCBlockSupport;
use Airwallex\Gateways\Blocks\AirwallexWeChatWCBlockSupport;
use Airwallex\Controllers\AirwallexController;
use Airwallex\Client\AdminClient;
use Airwallex\Client\CardClient;
use Airwallex\Controllers\GatewaySettingsController;
use Airwallex\Controllers\OrderController;
use Airwallex\Controllers\PaymentConsentController;
use Airwallex\Controllers\PaymentIntentController;
use Airwallex\Controllers\PaymentSessionController;
use Airwallex\Gateways\Blocks\AirwallexExpressCheckoutWCBlockSupport;
use Airwallex\Gateways\ExpressCheckout;
use Airwallex\Gateways\Settings\AdminSettings;
use Airwallex\Gateways\Settings\APISettings;
use Airwallex\Services\Util;
use Exception;
use WC_Order;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

class Main {

	const ROUTE_SLUG_CONFIRMATION = 'airwallex_payment_confirmation';
	const ROUTE_SLUG_WEBHOOK      = 'airwallex_webhook';
	const ROUTE_SLUG_JS_LOGGER    = 'airwallex_js_log';

	const OPTION_KEY_MERCHANT_COUNTRY = 'airwallex_merchant_country';

	const AWX_PAGE_ID_CACHE_KEY = 'airwallex_page_ids';

	public static $instance;

	public $apiSettings;

	private $expressCheckout;

	public static function getInstance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function getInstanceKey() {
		return md5( AUTH_KEY );
	}

	public function init() {
		$cardClient            = new CardClient();
		$cacheService          = new CacheService(Util::getClientSecret());
		$orderService          = new OrderService();
		$this->expressCheckout = new ExpressCheckout(
			new Card(),
			new GatewaySettingsController($cardClient),
			new OrderController(),
			new PaymentIntentController($cardClient, $cacheService, $orderService),
			new PaymentConsentController($cardClient, $cacheService, $orderService),
			new PaymentSessionController($cardClient),
			$orderService,
			new CacheService(Util::getClientSecret()),
			$cardClient
		);

		$this->registerEvents();
		$this->registerOrderStatus();
		$this->registerCron();
		$this->registerSettings();
		$this->registerExpressCheckoutButtons($this->expressCheckout);
	}

	public function registerEvents() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'addPaymentGateways' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handleStatusChange' ), 10, 4 );
		add_action( 'woocommerce_api_' . Card::ROUTE_SLUG, array( new AirwallexController(), 'cardPayment' ) );
		add_action( 'woocommerce_api_' . MainGateway::ROUTE_SLUG, array( new AirwallexController(), 'dropInPayment' ) );
		add_action( 'woocommerce_api_' . WeChat::ROUTE_SLUG, array( new AirwallexController(), 'weChatPayment' ) );
		add_action( 'woocommerce_api_' . self::ROUTE_SLUG_CONFIRMATION, array( new AirwallexController(), 'paymentConfirmation' ) );
		add_action( 'woocommerce_api_' . self::ROUTE_SLUG_WEBHOOK, array( new AirwallexController(), 'webhook' ) );
		if ( $this->isJsLoggingActive() ) {
			add_action( 'woocommerce_api_' . self::ROUTE_SLUG_JS_LOGGER, array( new AirwallexController(), 'jsLog' ) );
		}
		add_action( 'woocommerce_api_' . Card::ROUTE_SLUG_ASYNC_INTENT, array( new AirwallexController(), 'asyncIntent' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AIRWALLEX_PLUGIN_PATH . AIRWALLEX_PLUGIN_NAME . '.php' ), array( $this, 'addPluginSettingsLink' ) );
		add_action( 'airwallex_check_pending_transactions', array( $this, 'checkPendingTransactions' ) );
		add_action( 'woocommerce_settings_saved', array( $this, 'updateMerchantCountryAfterSave' ) );
		add_action( 'requests-requests.before_request', array( $this, 'modifyRequestsForLogging' ), 10, 5 );
		add_action( 'wp_loaded', array( $this, 'createPages' ) );
		add_action(
			'wp_loaded',
			function () {
				add_shortcode( 'airwallex_payment_method_card', array( new Card(), 'output' ) );
				add_shortcode( 'airwallex_payment_method_wechat', array( new WeChat(), 'output' ) );
				add_shortcode( 'airwallex_payment_method_all', array( new MainGateway(), 'output' ) );
			}
		);
		add_filter( 'display_post_states', array( $this, 'addDisplayPostStates' ), 10, 2 );
		if ( ! is_admin() ) {
			add_filter( 'wp_get_nav_menu_items', array( $this, 'excludePagesFromMenu' ), 10, 3 );
			add_filter( 'wp_list_pages_excludes', array( $this, 'excludePagesFromList' ), 10, 1 );
		}
		add_action( 'woocommerce_blocks_loaded', array( $this, 'woocommerceBlockSupport' ) );
		add_action('woocommerce_init', [AdminSettings::class, 'init']);
	}

	public function registerSettings() {
		$this->apiSettings = new APISettings();
	}

	public function noticeApiKeyMissing() {
		$clientId = get_option( 'airwallex_client_id' );
		$apiKey   = get_option( 'airwallex_api_key' );

		if ( $clientId && $apiKey ) {
			return;
		}

		add_action(
			'admin_notices',
			function () {
				printf(
					/* translators: Placeholder 1: Opening div and strong tag. Placeholder 2: Close strong tag and insert new line. Placeholder 3: Open link tag. Placeholder 4: Close link and div tag. */
					esc_html__(
						'%1$sTo start using Airwallex payment methods, please enter your credentials first.%2$s %3$sAPI Settings%4$s',
						'airwallex-online-payments-gateway'
					),
					'<div class="notice notice-error is-dismissible" style="padding:12px 12px"><strong>',
					'</strong><br />',
					'<a class="button-primary" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=airwallex_general' ) ) . '">',
					'</a></div>'
				);
			}
		);
	}

	public function noticeExpressCheckoutDisabled(ExpressCheckout $gateway) {
		if ( ! $gateway->enabled ) {
			add_action(
				'admin_notices',
				function () {
					printf(
						/* translators: Placeholder 1: Opening div tag. Placeholder 2: Open link tag. Placeholder 3: Close link tag. Placeholder 4: Close div tag. */
						esc_html__(
							'%1$sYou have not activated any express checkout option. Remember to %2$sselect at least one option%3$s to let your customers enjoy faster, more secure checkouts.%4$s',
							'airwallex-online-payments-gateway'
						),
						'<div class="notice notice-warning is-dismissible" style="padding:12px 12px">',
						'<a href=">' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . ExpressCheckout::ID ) ) . '">',
						'</a>',
						'</div>'
					);
				}
			);
		}
	}

	public function modifyRequestsForLogging( $url, $headers, $data, $type, &$options ) {
		if ( ! $options['blocking'] && strpos( $url, 'airwallex' ) ) {
			if ( class_exists('\WpOrg\Requests\Transport\Fsockopen') ) {
				$options['transport'] = '\WpOrg\Requests\Transport\Fsockopen';
			} else {
				$options['transport'] = 'Requests_Transport_fsockopen';
			}
		}
	}

	public function updateMerchantCountryAfterSave() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $_POST['airwallex_client_id'] ) || empty( $_POST['airwallex_api_key'] ) ) {
			return;
		}
		$this->updateMerchantCountry();
	}

	protected function updateMerchantCountry() {
		if ( empty( get_option( 'airwallex_client_id' ) ) || empty( get_option( 'airwallex_api_key' ) ) ) {
			return;
		}
		$country = null;
		try {
			$client  = new AdminClient( get_option( 'airwallex_client_id' ), get_option( 'airwallex_api_key' ), false );
			$country = $client->getMerchantCountry();
		} catch ( Exception $e ) {
			LogService::getInstance()->error( __METHOD__ . ' failed to get merchant country.', $e->getMessage() );
		}
		if ( empty( $country ) ) {
			//try in sandbox
			try {
				$client  = new AdminClient( get_option( 'airwallex_client_id' ), get_option( 'airwallex_api_key' ), true );
				$country = $client->getMerchantCountry();
			} catch ( Exception $e ) {
				LogService::getInstance()->error( __METHOD__ . ' failed to get merchant country.', $e->getMessage() );
			}
		}
		update_option( self::OPTION_KEY_MERCHANT_COUNTRY, $country );
	}

	public function getMerchantCountry() {
		$country = get_option( self::OPTION_KEY_MERCHANT_COUNTRY );

		if ( empty( $country ) ) {
			$this->updateMerchantCountry();
			$country = get_option( self::OPTION_KEY_MERCHANT_COUNTRY );
		}
		if ( empty( $country ) ) {
			$country = get_option( 'woocommerce_default_country' );
		}
		return $country;
	}


	protected function registerOrderStatus() {

		add_filter(
			'init',
			function () {
				register_post_status(
					'airwallex-issue',
					array(
						'label'                     => __( 'Airwallex Issue', 'airwallex-online-payments-gateway' ),
						'public'                    => true,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
					)
				);
				register_post_status(
					'wc-airwallex-pending',
					array(
						'label'                     => __( 'Airwallex Pending', 'airwallex-online-payments-gateway' ),
						'public'                    => true,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
					)
				);
			}
		);

		add_filter(
			'wc_order_statuses',
			function ( $statusList ) {
				$statusList['wc-airwallex-pending'] = __( 'Airwallex Pending', 'airwallex-online-payments-gateway' );
				$statusList['airwallex-issue']      = __( 'Airwallex Issue', 'airwallex-online-payments-gateway' );
				return $statusList;
			}
		);
	}

	protected function registerCron() {
		$interval = (int) get_option( 'airwallex_cronjob_interval' );
		$interval = ( $interval < 3600 ) ? 3600 : $interval;
		add_action(
			'init',
			function () use ( $interval ) {
				if ( function_exists( 'as_schedule_cron_action' ) ) {
					if ( ! as_next_scheduled_action( 'airwallex_check_pending_transactions' ) ) {
						as_schedule_recurring_action(
							strtotime( 'midnight tonight' ),
							$interval,
							'airwallex_check_pending_transactions'
						);
					}
				}
			}
		);
	}

	/**
	 * Exclude airwallex payment pages from menu
	 *
	 * @param  array  $items An array of menu item post objects.
	 * @param  object $menu  The menu object.
	 * @param  array  $args  An array of arguments used to retrieve menu item objects.
	 * @return array  Menu item list exclude airwallex payment pages
	 */
	public function excludePagesFromMenu( $items ) {
		$cacheService   = new CacheService();
		$excludePageIds = explode( ',', $cacheService->get( self::AWX_PAGE_ID_CACHE_KEY ) );
		foreach ( $items as $key => $item ) {
			if ( in_array( strval( $item->object_id ), $excludePageIds, true ) ) {
				unset( $items[ $key ] );
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
	public function excludePagesFromList( $excludeArray ) {
		$cacheService   = new CacheService();
		$excludePageIds = explode( ',', $cacheService->get( self::AWX_PAGE_ID_CACHE_KEY ) );
		if ( is_array( $excludePageIds ) ) {
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

		$cardShortcode   = 'airwallex_payment_method_card';
		$wechatShortcode = 'airwallex_payment_method_wechat';
		$allShortcode    = 'airwallex_payment_method_all';

		$pages = array(
			'payment_method_card'   => array(
				'name'    => _x( 'airwallex_payment_method_card', 'Page slug', 'airwallex-online-payments-gateway' ),
				'title'   => _x( 'Payment', 'Page title', 'airwallex-online-payments-gateway' ),
				'content' => '<!-- wp:shortcode -->[' . $cardShortcode . ']<!-- /wp:shortcode -->',
			),
			'payment_method_wechat' => array(
				'name'    => _x( 'airwallex_payment_method_wechat', 'Page slug', 'airwallex-online-payments-gateway' ),
				'title'   => _x( 'Payment', 'Page title', 'airwallex-online-payments-gateway' ),
				'content' => '<!-- wp:shortcode -->[' . $wechatShortcode . ']<!-- /wp:shortcode -->',
			),
			'payment_method_all'    => array(
				'name'    => _x( 'airwallex_payment_method_all', 'Page slug', 'airwallex-online-payments-gateway' ),
				'title'   => _x( 'Payment', 'Page title', 'airwallex-online-payments-gateway' ),
				'content' => '<!-- wp:shortcode -->[' . $allShortcode . ']<!-- /wp:shortcode -->',
			),
		);

		$pageIds = array();
		foreach ( $pages as $key => $page ) {
			$pageIds[] = wc_create_page(
				esc_sql( $page['name'] ),
				'airwallex_' . $key . '_page_id',
				$page['title'],
				$page['content']
			);
		}

		$pageIdStr    = implode( ',', $pageIds );
		$cacheService = new CacheService();
		if ( $cacheService->get( self::AWX_PAGE_ID_CACHE_KEY ) !== $pageIdStr ) {
			$cacheService->set( self::AWX_PAGE_ID_CACHE_KEY, $pageIdStr, 0 );
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
	public function addDisplayPostStates( $post_states, $post ) {
		if ( get_option( 'airwallex_payment_method_card_page_id' ) === strval( $post->ID ) ) {
			$post_states['awx_page_for_card_method'] = __( 'Airwallex - Cards', 'airwallex-online-payments-gateway' );
		} elseif ( get_option( 'airwallex_payment_method_wechat_page_id' ) === strval( $post->ID ) ) {
			$post_states['awx_page_for_wechat_method'] = __( 'Airwallex - WeChat Pay', 'airwallex-online-payments-gateway' );
		} elseif ( get_option( 'airwallex_payment_method_all_page_id' ) === strval( $post->ID ) ) {
			$post_states['awx_page_for_all_method'] = __( 'Airwallex - All Payment Methods', 'airwallex-online-payments-gateway' );
		}

		return $post_states;
	}

	public function addPluginSettingsLink( $links ) {
		$settingsLink = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=airwallex_general' ) . '">' . __( 'Airwallex API settings', 'airwallex-online-payments-gateway' ) . '</a>';
		array_unshift( $links, $settingsLink );
		return $links;
	}

	public function addPaymentGateways( $gateways ) {
		$gateways[] = MainGateway::class;
		if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
			$gateways[] = CardSubscriptions::class;
		} else {
			$gateways[] = Card::class;
		}
		$gateways[] = WeChat::class;
		$gateways[] = $this->expressCheckout;
		return $gateways;
	}

	/**
	 * Handle order status change
	 *
	 * @param $orderId
	 * @param $statusFrom
	 * @param $statusTo
	 * @param WC_Order $order
	 */
	public function handleStatusChange( $orderId, $statusFrom, $statusTo, $order ) {
		$this->handleStatusChangeForCard( $statusTo, $order );
	}

	public function checkPendingTransactions() {
		( new OrderService() )->checkPendingTransactions();
	}

	/**
	 * Handle order status change for card payment
	 *
	 * @param $statusTo
	 * @param WC_Order $order
	 */
	private function handleStatusChangeForCard( $statusTo, $order ) {
		$cardGateway = new Card();

		if ( $order->get_payment_method() !== $cardGateway->id && $order->get_payment_method() !== ExpressCheckout::GATEWAY_ID ) {
			return;
		}

		if ( $cardGateway->is_capture_immediately() ) {
			return;
		}

		if ( $statusTo === $cardGateway->get_option( 'capture_trigger_order_status' ) || 'wc-' . $statusTo === $cardGateway->get_option( 'capture_trigger_order_status' ) ) {
			try {
				if ( ! $cardGateway->is_captured( $order ) ) {
					$cardGateway->capture( $order );
				} else {
					( new LogService() )->debug( 'skip capture by status change because order is already captured', $order );
				}
			} catch ( Exception $e ) {
				( new LogService() )->error( 'capture by status error', $e->getMessage() );
				$order->add_order_note( 'ERROR: ' . $e->getMessage() );
			}
		}
	}

	public function isJsLoggingActive() {
		return in_array( get_option( 'airwallex_do_js_logging' ), array( 'yes', 1, true, '1' ), true );
	}

	public function addJsLegacy() {
		$isCheckout  = is_checkout();
		$cardGateway = new Card();
		$jsUrl       = 'https://checkout.airwallex.com/assets/elements.bundle.min.js';
		$jsUrlLocal  = AIRWALLEX_PLUGIN_URL . '/assets/js/airwallex-local.js';
		$cssUrl      = AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout.css';

		$confirmationUrl  = $cardGateway->get_payment_confirmation_url();
		$confirmationUrl .= ( strpos( $confirmationUrl, '?' ) === false ) ? '?' : '&';

		$inlineScript = '
            const AirwallexParameters = {
                asyncIntentUrl: \'' . $cardGateway->get_async_intent_url() . '\',
                confirmationUrl: \'' . $confirmationUrl . '\'
            };';
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
			global $wp;
			$order_id = (int) $wp->query_vars['order-pay'];
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( is_a( $order, 'WC_Order' ) ) {
					$inlineScript .= 'AirwallexParameters.billingFirstName = ' . wp_json_encode( $order->get_billing_first_name() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingLastName = ' . wp_json_encode( $order->get_billing_last_name() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingAddress1 = ' . wp_json_encode( $order->get_billing_address_1() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingAddress2 = ' . wp_json_encode( $order->get_billing_address_2() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingState = ' . wp_json_encode( $order->get_billing_state() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingCity = ' . wp_json_encode( $order->get_billing_city() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingPostcode = ' . wp_json_encode( $order->get_billing_postcode() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingCountry = ' . wp_json_encode( $order->get_billing_country() ) . ';';
					$inlineScript .= 'AirwallexParameters.billingEmail = ' . wp_json_encode( $order->get_billing_email() ) . ';';
				}
			}
		}

		if ( $this->isJsLoggingActive() ) {
			$loggingInlineScript = "\nconst airwallexJsLogUrl = '" . WC()->api_request_url( self::ROUTE_SLUG_JS_LOGGER ) . "';";
			wp_enqueue_script( 'airwallex-js-logging-js', AIRWALLEX_PLUGIN_URL . '/assets/js/jsnlog.js', array(), AIRWALLEX_VERSION, false );
			wp_add_inline_script( 'airwallex-js-logging-js', $loggingInlineScript );

		}

		if ( ! $isCheckout ) {
			//separate pages for cc and wechat payment
			define( 'AIRWALLEX_INLINE_JS', $inlineScript );
			return;
		}

		wp_enqueue_script( 'airwallex-lib-js', $jsUrl, array(), null, true );
		wp_enqueue_script( 'airwallex-local-js', $jsUrlLocal, array(), AIRWALLEX_VERSION, true );

		wp_enqueue_style( 'airwallex-css', $cssUrl, array(), AIRWALLEX_VERSION );
		/* translators: Placeholder 1: error message returned from Airwallex. */
		$errorMessage      = __( 'An error has occurred. Please check your payment details (%s)', 'airwallex-online-payments-gateway' );
		$incompleteMessage = __( 'Your credit card details are incomplete', 'airwallex-online-payments-gateway' );
		$environment       = $cardGateway->is_sandbox() ? 'demo' : 'prod';
		$autoCapture       = $cardGateway->is_capture_immediately() ? 'true' : 'false';
		$airwallexOrderId  = absint( get_query_var( 'order-pay' ) );
		$locale            = \Airwallex\Services\Util::getLocale();
		$inlineScript     .= <<<AIRWALLEX

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
		wp_add_inline_script( 'airwallex-local-js', $inlineScript );
	}

	public function woocommerceBlockSupport() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new AirwallexMainWCBlockSupport() );
					$payment_method_registry->register( new AirwallexCardWCBlockSupport() );
					$payment_method_registry->register( new AirwallexWeChatWCBlockSupport() );
					$payment_method_registry->register( new AirwallexExpressCheckoutWCBlockSupport());
				}
			);
		}
	}

	public function registerExpressCheckoutButtons($expressCheckout) {
		add_action( 'woocommerce_after_add_to_cart_quantity', [ $expressCheckout, 'displayExpressCheckoutButtonHtml' ], 3 );
		add_action( 'woocommerce_after_add_to_cart_quantity', [ $expressCheckout, 'displayExpressCheckoutButtonSeparatorHtml' ], 4 );
		add_action( 'woocommerce_proceed_to_checkout', [ $expressCheckout, 'displayExpressCheckoutButtonHtml' ], 3 );
		add_action( 'woocommerce_proceed_to_checkout', [ $expressCheckout, 'displayExpressCheckoutButtonSeparatorHtml' ], 4 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $expressCheckout, 'displayExpressCheckoutButtonHtml' ], 3 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $expressCheckout, 'displayExpressCheckoutButtonSeparatorHtml' ], 4 );
	}
}
