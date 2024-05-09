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
use Airwallex\Controllers\PaymentSessionController;
use Airwallex\Gateways\Blocks\AirwallexExpressCheckoutWCBlockSupport;
use Airwallex\Gateways\ExpressCheckout;
use Airwallex\Gateways\Settings\AdminSettings;
use Airwallex\Gateways\Settings\APISettings;
use Airwallex\Services\Util;
use Airwallex\Gateways\Blocks\AirwallexKlarnaWCBlockSupport;
use Airwallex\Gateways\Klarna;
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
		$this->noticeApiKeyMissing();
	}

	public function registerEvents() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'addPaymentGateways' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handleStatusChange' ), 10, 4 );
		add_action( 'woocommerce_api_' . Card::ROUTE_SLUG, array( new AirwallexController(), 'cardPayment' ) );
		add_action( 'woocommerce_api_' . MainGateway::ROUTE_SLUG, array( new AirwallexController(), 'dropInPayment' ) );
		add_action( 'woocommerce_api_' . WeChat::ROUTE_SLUG, array( new AirwallexController(), 'weChatPayment' ) );
		add_action( 'woocommerce_api_' . self::ROUTE_SLUG_CONFIRMATION, array( new AirwallexController(), 'paymentConfirmation' ) );
		add_action( 'woocommerce_api_' . self::ROUTE_SLUG_WEBHOOK, array( new AirwallexController(), 'webhook' ) );
		add_action( 'woocommerce_api_airwallex_process_order_pay', [new AirwallexController(), 'processOrderPay'] );
		if ( $this->isJsLoggingActive() ) {
			add_action( 'woocommerce_api_' . self::ROUTE_SLUG_JS_LOGGER, array( new AirwallexController(), 'jsLog' ) );
		}
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
		add_filter( 'woocommerce_available_payment_gateways', [$this, 'disableGatewayOrderPay'] );
		add_action( 'wp_enqueue_scripts', [$this, 'enqueueScripts'] );
		add_action( 'admin_enqueue_scripts', [$this, 'enqueueAdminScripts'] );
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

		try {
			$client  =  AdminClient::getInstance();
			$country = $client->getMerchantCountry();
			update_option( self::OPTION_KEY_MERCHANT_COUNTRY, $country );
		} catch ( Exception $e ) {
			LogService::getInstance()->error( __METHOD__ . ' failed to get merchant country.', $e->getMessage() );
		}
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
		$excludePageIds = explode( ',', (string) $cacheService->get( self::AWX_PAGE_ID_CACHE_KEY ) );
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
		$excludePageIds = explode( ',', (string) $cacheService->get( self::AWX_PAGE_ID_CACHE_KEY ) );
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
		if (!is_wc_endpoint_url('order-pay')) {
			$gateways[] = Klarna::class;
		}

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
					LogService::getInstance()->debug( 'skip capture by status change because order is already captured', $order );
				}
			} catch ( Exception $e ) {
				LogService::getInstance()->error( 'capture by status error', $e->getMessage() );
				$order->add_order_note( 'ERROR: ' . $e->getMessage() );
			}
		}
	}

	public function isJsLoggingActive() {
		return in_array( get_option( 'airwallex_do_js_logging' ), array( 'yes', 1, true, '1' ), true );
	}

	public function enqueueScripts() {
		// register all the scripts and styles
		$awxHost = Util::getCheckoutUIEnvHost( Util::getEnvironment() );
		wp_register_script(
			'airwallex-lib-js',
			$awxHost . '/assets/elements.bundle.min.js',
			[],
			date('Ymd'),
			true
		);
		wp_register_script(
			'airwallex-common-js',
			AIRWALLEX_PLUGIN_URL . '/assets/js/airwallex-local.js',
			['jquery'],
			AIRWALLEX_VERSION,
			true
		);
		wp_register_script(
			'airwallex-lpm-js', 
			AIRWALLEX_PLUGIN_URL . '/build/airwallex-lpm.min.js', 
			['airwallex-common-js', 'airwallex-lib-js'],
			AIRWALLEX_VERSION,
			true
		);
		wp_register_script(
			'airwallex-card-js',
			AIRWALLEX_PLUGIN_URL . '/build/airwallex-card.min.js',
			['airwallex-common-js', 'airwallex-lib-js', 'jquery-blockui'],
			AIRWALLEX_VERSION,
			true
		);
		wp_register_script(
			'airwallex-redirect-js',
			AIRWALLEX_PLUGIN_URL . '/build/airwallex-redirect.min.js',
			['airwallex-common-js', 'airwallex-lib-js'],
			AIRWALLEX_VERSION,
			true
		);
		wp_register_script(
			'airwallex-express-checkout',
			AIRWALLEX_PLUGIN_URL . '/build/airwallex-express-checkout.min.js',
			['airwallex-lib-js', 'jquery', 'jquery-blockui'],
			AIRWALLEX_VERSION,
			true
		);
		wp_register_script(
			'airwallex-js-logging-js',
			AIRWALLEX_PLUGIN_URL . '/assets/js/jsnlog.js',
			[],
			AIRWALLEX_VERSION,
			false
		);

		wp_register_style(
			'airwallex-css',
			AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout.css',
			[],
			AIRWALLEX_VERSION
		);
		wp_register_style(
			'airwallex-redirect-element-css',
			AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex.css',
			[],
			AIRWALLEX_VERSION
		);
		wp_register_style(
			'airwallex-block-css',
			AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout-blocks.css',
			[],
			AIRWALLEX_VERSION
		);

		if ( $this->isJsLoggingActive() ) {
			wp_enqueue_script( 'airwallex-js-logging-js' );
			wp_add_inline_script( 'airwallex-js-logging-js', "var airwallexJsLogUrl = '" . WC()->api_request_url( self::ROUTE_SLUG_JS_LOGGER ) . "';", 'before' );
		}

		$confirmationUrl  = WC()->api_request_url( Main::ROUTE_SLUG_CONFIRMATION );
		$confirmationUrl .= ( strpos( $confirmationUrl, '?' ) === false ) ? '?' : '&';
		$commonScriptData = [
			'env' => Util::getEnvironment(),
			'locale' => Util::getLocale(),
			'confirmationUrl' => $confirmationUrl,
			'isOrderPayPage'  => is_wc_endpoint_url( 'order-pay' ),
		];
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
			global $wp;
			$order_id = (int) $wp->query_vars['order-pay'];
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( is_a( $order, 'WC_Order' ) ) {
					$orderKey = isset($_GET['key']) ? wp_unslash( $_GET['key'] ) : '';
					$orderPayUrl = WC()->api_request_url('airwallex_process_order_pay');
					$orderPayUrl .= ( strpos( $orderPayUrl, '?' ) === false ) ? '?' : '&';
					$orderPayUrl .= 'order_id=' . $order_id . '&key=' . $orderKey;
					$commonScriptData['processOrderPayUrl'] = $orderPayUrl;
					$commonScriptData['billingFirstName'] = $order->get_billing_first_name();
					$commonScriptData['billingLastName'] = $order->get_billing_last_name();
					$commonScriptData['billingAddress1'] = $order->get_billing_address_1();
					$commonScriptData['billingAddress2'] = $order->get_billing_address_2();
					$commonScriptData['billingState'] = $order->get_billing_state();
					$commonScriptData['billingCity'] = $order->get_billing_city();
					$commonScriptData['billingPostcode'] = $order->get_billing_postcode();
					$commonScriptData['billingCountry'] = $order->get_billing_country();
					$commonScriptData['billingEmail'] = $order->get_billing_email();
				}
			}
		}
		wp_add_inline_script( 'airwallex-common-js', 'var awxCommonData=' . wp_json_encode($commonScriptData), 'before' );
	}

	public function enqueueAdminScripts() {
		wp_register_script(
			'airwallex-admin-settings',
			AIRWALLEX_PLUGIN_URL . '/assets/js/admin/airwallex-admin-settings.js',
			['jquery'],
			AIRWALLEX_VERSION,
			true
		);
		
		wp_register_style(
			'airwallex-admin-css',
			AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout-admin.css',
			[],
			AIRWALLEX_VERSION
		);
	}

	public function woocommerceBlockSupport() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new AirwallexMainWCBlockSupport() );
					$payment_method_registry->register( new AirwallexCardWCBlockSupport() );
					$payment_method_registry->register( new AirwallexWeChatWCBlockSupport() );
					$payment_method_registry->register( new AirwallexExpressCheckoutWCBlockSupport() );
					$payment_method_registry->register( new AirwallexKlarnaWCBlockSupport() );
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

	public function disableGatewayOrderPay($available_gateways) {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			unset( $available_gateways['airwallex_express_checkout'] );
		}

		return $available_gateways;
	}
}
