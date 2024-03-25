<?php

namespace Airwallex\Gateways;

use Airwallex\Client\CardClient;
use Airwallex\Controllers\OrderController;
use Airwallex\Gateways\Settings\AirwallexSettingsTrait;
use Airwallex\Services\OrderService;
use Airwallex\Controllers\GatewaySettingsController;
use Airwallex\Controllers\PaymentConsentController;
use Airwallex\Controllers\PaymentSessionController;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Services\Util;
use Airwallex\Struct\Refund;
use Airwallex\Struct\PaymentIntent;
use Exception;
use WC_Payment_Gateway;
use WC_AJAX;
use WP_Error;
use WC_Subscriptions_Product;

if (!defined('ABSPATH')) {
	exit;
}

class ExpressCheckout extends WC_Payment_Gateway {

	use AirwallexGatewayTrait;
	use AirwallexSettingsTrait;

	const GATEWAY_ID               = 'airwallex_express_checkout';
	const APPLE_PAY                = 'apple_pay';
	const GOOGLE_PAY               = 'google_pay';
	const BUTTON_SIZE_MAP          = [
		'default' => '40px',
		'medium' => '48px',
		'large' => '56px',
	];
	const REGISTER_DOMAIN_URL      = 'https://www.airwallex.com/app/acquiring/settings/apple-pay/add-domain';
	const DEMO_REGISTER_DOMAIN_URL = 'https://demo.airwallex.com/app/acquiring/settings/apple-pay/add-domain';

	protected $cardGateway;
	protected $gatewaySettingsController;
	protected $orderController;
	protected $paymentConsentController;
	protected $paymentSessionController;
	protected $orderService;
	protected $cacheService;
	protected $cardClient;

	public function __construct(
		Card $cardGateway,
		GatewaySettingsController $gatewaySettingsController,
		OrderController $orderController,
		PaymentConsentController $paymentConsentController,
		PaymentSessionController $paymentSessionController,
		OrderService $orderService,
		CacheService $cacheService,
		CardClient $cardClient
	) {
		$this->plugin_id          = AIRWALLEX_PLUGIN_NAME;
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __('Airwallex - Express Checkout', 'airwallex-online-payments-gateway');
		$this->method_description = __(
			'Apple Pay and Google Pay express checkout.',
			'airwallex-online-payments-gateway'
		);
		$this->supports           = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
		);
		$this->tabTitle           = __('Express Checkout', 'airwallex-online-payments-gateway');

		$this->title                     = $this->method_title;
		$this->description               = __('Express Checkout', 'airwallex-online-payments-gateway');
		$this->has_fields                = false;
		$this->cardGateway               = $cardGateway;
		$this->gatewaySettingsController = $gatewaySettingsController;
		$this->orderController           = $orderController;
		$this->paymentConsentController  = $paymentConsentController;
		$this->paymentSessionController  = $paymentSessionController;
		$this->cacheService              = $cacheService;
		$this->orderService              = $orderService;
		$this->cardClient                = $cardClient;

		$this->init_settings();
		$this->init_form_fields();

		$this->registerHooks();
	}

	public function registerHooks() {
		add_filter( 'wc_airwallex_settings_nav_tabs', array( $this, 'adminNavTab' ), 12 );
		add_action( 'woocommerce_airwallex_settings_checkout_' . $this->id, array( $this, 'enqueueAdminScripts' ) );
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'addOrderMeta' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueueScripts' ] );

		add_action('wc_ajax_airwallex_get_cart_details', [$this->orderController, 'getCartDetails']);
		add_action('wc_ajax_airwallex_get_shipping_options', [$this->orderController, 'getShippingOptions']);
		add_action('wc_ajax_airwallex_update_shipping_method', [$this->orderController, 'updateShippingMethod']);
		add_action('wc_ajax_airwallex_create_order', [$this->orderController, 'createOrderFromCart']);
		add_action('wc_ajax_airwallex_add_to_cart', [$this->orderController, 'addToCart']);
		add_action('wc_ajax_airwallex_start_payment_session', [$this->paymentSessionController, 'startPaymentSession']);
		add_action('wc_ajax_airwallex_register_apple_pay_domain', [$this->gatewaySettingsController, 'registerDomain']);
		add_action('wc_ajax_airwallex_get_estimated_cart_details', [$this->orderController, 'getEstimatedCartDetail']);

		add_filter('woocommerce_registration_error_email_exists', [$this, 'registrationEmailExistsError'], 10, 2);

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'do_subscription_payment' ), 10, 2 );
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'subscription_payment_information' ), 10, 2 );
		}
	}

	public function init_form_fields() {
		$isCardGatewayEnabled = $this->isCardGatewayEnabled();

		$formFields = array_merge(
			$isCardGatewayEnabled ? [] : [
				'card_not_enable_alert' => [
					'type' => 'alert',
					'html' => sprintf(
						/* translators: Placeholder 1: Opening link tag. Placeholder 2: Close link tag. */
						__('To use Express Checkout, you must first enable %1$sAirwallex Card Payments%2$s', 'airwallex-online-payments-gateway'),
						'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=airwallex_card') . '">',
						'</a>'
					),
				]
			],
			[
				'register_domain_instruction' => [
					'type' => 'apple_pay_instruction',
					'title' => __('Apple Pay', 'airwallex-online-payments-gateway'),
					'label' => __('Register', 'airwallex-online-payments-gateway'),
					'class' => 'wc-airwallex-register-apple-pay-domain button-secondary',
					'disabled'    => !$isCardGatewayEnabled,
				],
				'enabled'     => [
					'title'       => __( 'Enable/Disable', 'airwallex-online-payments-gateway' ),
					'label'       => __( 'Enable Airwallex Express Checkout', 'airwallex-online-payments-gateway' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
					'disabled'    => !$isCardGatewayEnabled,
				],
				'payment_methods' => [
					'title'       => __( 'Express Checkout', 'airwallex-online-payments-gateway' ),
					'type' => 'multiselect',
					'class' => 'wc-enhanced-select',
					'options' => [
						'apple_pay' => __('Apple Pay', 'airwallex-online-payments-gateway'),
						'google_pay' => __('Google Pay', 'airwallex-online-payments-gateway'),
					],
					'default' => ['apple_pay', 'google_pay'],
					'disabled'    => !$isCardGatewayEnabled,
				],
				'show_button_on' => [
					'title'       => __( 'Show Button On', 'airwallex-online-payments-gateway' ),
					'type' => 'multiselect',
					'class' => 'wc-enhanced-select',
					'options' => [
						'checkout' => __('Checkout', 'airwallex-online-payments-gateway'),
						'product_detail' => __('Product Page', 'airwallex-online-payments-gateway'),
						'cart' => __('Cart', 'airwallex-online-payments-gateway'),
					],
					'default' => ['checkout', 'cart'],
					'disabled'    => !$isCardGatewayEnabled,
				],
				'call_to_action' => [
					'title'       => __( 'Call To Action', 'airwallex-online-payments-gateway' ),
					'type' => 'select',
					'class' => 'wc-enhanced-select',
					'options' => [
						'plain' => __('Only Icon', 'airwallex-online-payments-gateway'),
						'buy' => __('Buy', 'airwallex-online-payments-gateway'),
						'donate' => __('Donate', 'airwallex-online-payments-gateway'),
						'book' => __('Book', 'airwallex-online-payments-gateway'),
					],
					'description' => __('Select a button label that fits best with the flow of purchase or payment experience on your store.', 'airwallex-online-payments-gateway'),
					'default' => 'buy',
					'disabled'    => !$isCardGatewayEnabled,
				],
				/*'appearance_size' => [
					'title'       => __( 'Button Size', 'airwallex-online-payments-gateway' ),
					'type' => 'select',
					'class' => 'wc-enhanced-select',
					'options' => [
						'default' => __('Default (40px)', 'airwallex-online-payments-gateway'),
						'medium' => __('Medium (48px)', 'airwallex-online-payments-gateway'),
						'large' => __('Large (56px)', 'airwallex-online-payments-gateway'),
					],
					'default' => 'default',
					'disabled'    => !$isCardGatewayEnabled,
				],*/
				'appearance_theme' => [
					'title'       => __( 'Button Theme', 'airwallex-online-payments-gateway' ),
					'type' => 'select',
					'class' => 'wc-enhanced-select',
					'options' => [
						'black' => __('Dark', 'airwallex-online-payments-gateway'),
						'white' => __('Light', 'airwallex-online-payments-gateway'),
					],
					'description' => __('Color of the Express Checkout Button.', 'airwallex-online-payments-gateway'),
					'default' => 'black',
					'disabled'    => !$isCardGatewayEnabled,
				],
				'awx_ec_button_preview' => [
					'type' => 'button_preview',
				],
		]);

		$this->form_fields = apply_filters('wc_airwallex_settings', $formFields);
	}

	public function generate_apple_pay_instruction_html($key, $data) {
		$fieldKey           = $this->get_field_key( $key );
		$data      = wp_parse_args(
			$data,
			array(
				'label'       => '',
				'title'       => '',
				'class'       => '',
				'style'       => '',
				'description' => '',
				'disabled'    => false,
			)
		);

		ob_start();

		include AIRWALLEX_PLUGIN_PATH . 'includes/Gateways/Settings/views/apple-pay-instruction.php';

		return ob_get_clean();
	}

	public function generate_google_pay_instruction_html($key, $data) {
		$fieldKey           = $this->get_field_key( $key );
		$data      = wp_parse_args(
			$data,
			array(
				'label'       => '',
				'title'       => '',
				'class'       => '',
				'style'       => '',
				'description' => '',
				'disabled'    => false,
			)
		);

		$data['value'] = $this->get_option( $key );
		ob_start();

		include AIRWALLEX_PLUGIN_PATH . 'includes/Gateways/Settings/views/google-pay-instruction.php';

		return ob_get_clean();
	}

	public function generate_alert_html($key, $value) {
		ob_start();
		?>
			<div class="awx-settings-alert-box">
				<div><?php echo wp_kses_post($value['html']); ?></div>
			</div>
		<?php

		return ob_get_clean();
	}

	public function generate_button_preview_html($key, $data) {

		ob_start();

		include __DIR__ . '/Settings/views/express-checkout-button-preview.php';

		return ob_get_clean();
	}

	public function getShowButtonOn() {
		return (array) $this->get_option('show_button_on');
	}

	public function getButtonType() {
		return $this->get_option('call_to_action');
	}

	public function getButtonSize() {
		return 'default';
		// return $this->get_option('appearance_size');
	}

	public function getButtonTheme() {
		return $this->get_option('appearance_theme');
	}

	public function validate_payment_methods_field($key, $value) {
		return is_array($value) ? array_map('wc_clean', array_map('stripslashes', $value)) : '';
	}

	public function enqueueAdminScripts() {
		$this->enqueueAdminSettingsScripts();
		wp_add_inline_script(
			'airwallex-admin-settings',
			'var awxAdminSettings = "";'
		);
		wp_add_inline_script(
			'airwallex-admin-settings',
			'var awxAdminECSettings = ' . wp_json_encode($this->getExpressCheckoutSettingsScriptData())
		);
	}

	public function getExpressCheckoutSettingsScriptData() {
		try {
			$data = [
				'env' => Util::getEnvironment(),
				'locale' => Util::getLocale(),
				'mode' => 'payment',
				'buttonType' => $this->getButtonType(),
				'size' => $this->getButtonSize(),
				'sizeMap' => self::BUTTON_SIZE_MAP,
				'theme' => $this->getButtonTheme(),
				'merchantId' => Util::getMerchantInfoFromJwtToken($this->cardClient->getToken()),
				'apiSettings' => [
					'nonce' => [
						'registerDomain' => wp_create_nonce('wc-airwallex-admin-settings-register-apple-pay-domain'),
					],
					'ajaxUrl' => [
						'registerDomain' => WC_AJAX::get_endpoint('airwallex_register_apple_pay_domain'),
					],
				],
			];

			return $data;
		} catch (Exception $e) {
			LogService::getInstance()->error(__METHOD__ . ' Cannot load the express checkout settings script data.', $e->getMessage());
			return [];
		}
	}

	public function isMethodEnabled($method) {
		return in_array($this->enabled, ['yes', 1, true, '1'], true) && in_array($method, (array) $this->get_option('payment_methods'), true);
	}

	public function isCardGatewayEnabled() {
		return in_array($this->cardGateway->enabled, ['yes', 1, true, '1'], true);
	}

	public function addOrderMeta($order_id, $posted_data) {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $_POST['payment_method_type'] ) || ! isset( $_POST['payment_method'] ) || 'airwallex_express_checkout' !== $_POST['payment_method'] ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// phpcs:ignore WordPress.Security.NonceVerification
		$paymentMethodType = wc_clean( wp_unslash( $_POST['payment_method_type'] ) );

		if ( 'applepay' === $paymentMethodType ) {
			$order->set_payment_method_title( 'Apple Pay' );
			$order->save();
		} elseif ( 'googlepay' === $paymentMethodType ) {
			$order->set_payment_method_title( 'Google Pay' );
			$order->save();
		}
	}

	public function loadAppleScript() {
		wp_enqueue_script(
			'airwallex-apple-pay-block',
			'https://applepay.cdn-apple.com/jsapi/v1.1.0/apple-pay-sdk.js',
			array(),
			null,
			true
		);
	}

	public function enqueueScripts() {
		wp_enqueue_script( 'airwallex-lib-js', Util::getCheckoutUIEnvHost(Util::getEnvironment()) . '/assets/elements.bundle.min.js', array(), null, true );
		$dependencies = ['jquery', 'jquery-blockui', 'airwallex-lib-js'];
		wp_enqueue_script(
			'airwallex-express-checkout',
			AIRWALLEX_PLUGIN_URL . '/build/airwallex-express-checkout.min.js',
			$dependencies,
			AIRWALLEX_VERSION,
			true
		);
	}

	public function displayExpressCheckoutButtonHtml() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if (!isset($gateways['airwallex_card']) || !isset($gateways['airwallex_express_checkout'])) {
			return;
		}

		if (empty($this->get_option('payment_methods'))) {
			return;
		}

		if (!$this->isPageSupported()) {
			return;
		}

		if (!$this->shouldShowExpressCheckoutButton()) {
			return;
		}

		?>
		<div id="awx-express-checkout-wrapper" style="clear:both;padding-top:1.5em;">
		<div class="awx-express-checkout-error"></div>
			<?php if (is_checkout()) : ?>
				<fieldset id="awx-express-checkout-button" class="awx-express-checkout-button-set">
				<legend><?php esc_html_e('Express Checkout', 'airwallex-online-payments-gateway'); ?></legend>
			<?php else : ?>
				<div id="awx-express-checkout-button" class="awx-express-checkout-button">
			<?php endif; ?>

			<?php if ($this->isMethodEnabled('apple_pay')) : ?>
				<div id="awx-ec-apple-pay-btn" class="awx-ec-button awx-apple-pay-btn">
				</div>
			<?php endif; ?>

			<?php if ($this->isMethodEnabled('google_pay')) : ?>
				<div id="awx-ec-google-pay-btn" class="awx-ec-button awx-google-pay-btn">
				</div>
			<?php endif; ?>

			<?php if (is_checkout()) : ?>
				</fieldset>
			<?php else : ?>
				</div>
			<?php endif; ?>
		</div>
	<?php
		wp_add_inline_script(
			'airwallex-express-checkout',
			'var awxExpressCheckoutSettings = ' . wp_json_encode($this->getExpressCheckoutScriptData(false))
		);
	}

	public function displayExpressCheckoutButtonSeparatorHtml() {
		?>
		<p id="awx-express-checkout-button-separator" style="margin-top:1.5em;text-align:center;display:none;">&mdash; <?php esc_html_e('OR', 'airwallex-online-payments-gateway'); ?> &mdash;</p>
		<?php
	}

	/**
	 * Returns true if the current page supports express checkout button, false otherwise.
	 *
	 * @return  boolean  True if the current page is supported, false otherwise.
	 */
	private function isPageSupported() {
		return $this->isProduct()
			|| $this->isCartOrCheckout()
			|| is_wc_endpoint_url('order-pay');
	}

	/**
	 * Checks if this is a product page or content contains a product_page shortcode.
	 *
	 * @return boolean
	 */
	public function isProduct() {
		return is_product() || wc_post_content_has_shortcode('product_page');
	}

	/**
	 * Checks if this is a product page or content contains a product_page shortcode.
	 *
	 * @return boolean
	 */
	public function isCartOrCheckout() {
		return is_cart() || is_checkout();
	}

	/**
	 * Checks whether cart contains a subscription product or this is a subscription product page.
	 * 
	 * @return boolean
	 */
	public function hasSubscriptionProduct() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) || is_admin() ) {
			return false;
		}

		if ( $this->isProduct() ) {
			$product = $this->getProduct();
			if ( \WC_Subscriptions_Product::is_subscription( $product ) ) {
				return true;
			}
		} elseif ( $this->isCartOrCheckout() ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				if ( \WC_Subscriptions_Product::is_subscription( $_product ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns true if Payment Request Buttons are supported on the current page, false
	 * otherwise.
	 *
	 * @return  boolean  True if express checkout buttons are supported on current page, false otherwise
	 */
	public function shouldShowExpressCheckoutButton() {
		if (!$this->get_api_key() || !$this->get_client_id()) {
			LogService::getInstance()->debug('API Key and client secret are not set correctly.');
			return false;
		}

		if (!is_ssl()) {
			LogService::getInstance()->debug('Airwallex Express Checkout requires SSL.');
			return false;
		}

		// Don't show on the cart or checkout page if items in the cart are not supported.
		if (
			$this->isCartOrCheckout()
			&& !$this->isCartItemsAllowed()
		) {
			return false;
		}

		// Don't show on cart if disabled.
		if (is_cart() && !$this->shouldShowButtonOnPage('cart')) {
			return false;
		}

		// Don't show on checkout if disabled.
		if (is_checkout() && !$this->shouldShowButtonOnPage('checkout')) {
			return false;
		}

		// Don't show if product page is disabled.
		if ($this->isProduct() && !$this->shouldShowButtonOnPage('product_detail')) {
			return false;
		}

		// Don't show if product on current page is not supported.
		if ($this->isProduct() && !$this->isProductSupported($this->getProduct())) {
			return false;
		}

		if ($this->isProduct() && in_array($this->getProduct()->get_type(), ['variable', 'variable-subscription'], true)) {
			$stock_availability = array_column($this->getProduct()->get_available_variations(), 'is_in_stock');
			// Don't show if all product variations are out-of-stock.
			if (!in_array(true, $stock_availability, true)) {
				return false;
			}
		}

		return true;
	}

	public function getSupportedProductTypes() {
		return apply_filters(
			'airwallex_express_checkout_supported_product_types',
			[
				'simple',
				'variable',
				'variation',
				'subscription',
				'variable-subscription',
				'subscription_variation',
				'booking',
				'bundle',
				'composite',
			]
		);
	}

	/**
	 * Get product from product page or product_page shortcode.
	 *
	 * @return WC_Product Product object.
	 */
	public function getProduct() {
		global $post;

		if (is_product()) {
			return wc_get_product($post->ID);
		} elseif (wc_post_content_has_shortcode('product_page')) {
			// Get id from product_page shortcode.
			preg_match('/\[product_page id="(?<id>\d+)"\]/', $post->post_content, $shortcode_match);

			if (!isset($shortcode_match['id'])) {
				return false;
			}

			return wc_get_product($shortcode_match['id']);
		}

		return false;
	}

	/**
	 * Returns boolean on whether product is charged upon release.
	 *
	 * @param object $product
	 *
	 * @return bool
	 */
	public function isPreOrderProductChargedUponRelease( $product ) {
		return class_exists( 'WC_Pre_Orders' ) && class_exists( 'WC_Pre_Orders_Product' ) && \WC_Pre_Orders_Product::product_is_charged_upon_release( $product );
	}

	/**
	 * Returns true if a the provided product is supported, false otherwise.
	 *
	 * @param WC_Product $param  The product that's being checked for support.
	 *
	 * @return boolean  True if the provided product is supported, false otherwise.
	 */
	private function isProductSupported($product) {
		if (!is_object($product) || !in_array($product->get_type(), $this->getSupportedProductTypes(), true)) {
			return false;
		}

		// Trial subscriptions are not supported.
		if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::get_trial_length( $product ) > 0 ) {
			return false;
		}

		// Pre Orders charge upon release not supported.
		if ( $this->isPreOrderProductChargedUponRelease( $product ) ) {
			return false;
		}

		// Composite products are not supported on the product page.
		if ( class_exists( 'WC_Composite_Products' ) && function_exists( 'is_composite_product' ) && is_composite_product() ) {
			return false;
		}

		// File upload addon not supported
		if (class_exists('WC_Product_Addons_Helper')) {
			$product_addons = \WC_Product_Addons_Helper::get_product_addons($product->get_id());
			foreach ($product_addons as $addon) {
				if ('file_upload' === $addon['type']) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns pre-order product from cart.
	 *
	 * @return object|null
	 */
	public function getPreOrderProductFromCart() {
		if ( ! class_exists( 'WC_Pre_Orders' ) || ! class_exists( 'WC_Pre_Orders_Cart' ) ) {
			return false;
		}
		return \WC_Pre_Orders_Cart::get_pre_order_product();
	}

	public function isCartItemsAllowed() {
		// Pre Orders compatibility, do we support charge upon release.
		if ( $this->isPreOrderProductChargedUponRelease( $this->getPreOrderProductFromCart() ) ) {
			return false;
		}

		// when loading cart or checkout blocks, the cart could be unavailable
		if (is_null(WC()->cart)) {
			return true;
		}

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

			if (!in_array($_product->get_type(), $this->getSupportedProductTypes(), true)) {
				return false;
			}

			// Trial subscriptions not supported.
			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $_product ) && WC_Subscriptions_Product::get_trial_length( $_product ) > 0 ) {
				return false;
			}
		}

		// multiple packages order
		$packages = WC()->cart->get_shipping_packages();
		if ( 1 < count( $packages ) ) {
			return false;
		}

		return true;
	}

	public function shouldShowButtonOnPage($pageType) {
		return in_array($pageType, $this->getShowButtonOn(), true);
	}

	/**
	 * Checks whether account creation is possible upon checkout.
	 *
	 * @return bool
	 */
	public function isAccountCreationPossible() {
		// If automatically generate username/password are disabled, the Payment Request API
		// can't include any of those fields, so account creation is not possible.
		return (
			'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ) &&
			'yes' === get_option( 'woocommerce_registration_generate_username', 'yes' ) &&
			'yes' === get_option( 'woocommerce_registration_generate_password', 'yes' )
		);
	}

	/**
	 * Checks whether authentication is required for checkout.
	 *
	 * @return bool
	 */
	public function isAuthenticationRequired() {
		// If guest checkout is disabled and account creation upon checkout is not possible, authentication is required.
		if ( 'no' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ) && ! $this->isAccountCreationPossible() ) {
			return true;
		}
		// If cart contains subscription and account creation upon checkout is not possible, authentication is required.
		if ( $this->hasSubscriptionProduct() && ! $this->isAccountCreationPossible() ) {
			return true;
		}

		return false;
	}

	/**
	 * Settings array for the user authentication dialog and redirection.
	 *
	 * @return array
	 */
	public function getLoginConfirmationSettings() {
		if ( is_user_logged_in() || ! $this->isAuthenticationRequired() ) {
			return false;
		}

		$message      = __( 'To complete your transaction with the selected payment method, you must log in or create an account with our site.', 'airwallex-online-payments-gateway' );
		$redirect_url = add_query_arg(
			[
				'_wpnonce'                               => wp_create_nonce( 'wc-airwallex-set-redirect-url' ),
			],
			home_url()
		);

		return [
			'message'      => $message,
			'redirect_url' => wp_sanitize_redirect( esc_url_raw( $redirect_url ) ),
		];
	}

	public function registrationEmailExistsError($message, $email = '') {
		$accountLink = get_option( 'woocommerce_myaccount_page_id' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url();

		return sprintf(
			/* translators: Placeholder 1: Email. Placeholder 2: Account page url */
			__( 'An account is already registered with email address %1$s. <a href="%2$s" target="_blank">Please log in</a> and refresh the page.', 'airwallex-online-payments-gateway' ),
			$email,
			esc_attr( $accountLink )
		);
	}

	public function getActiveCardSchemes($countryCode, $currencyCode) {
		$cacheKey = 'wc_airwallex_ec_card_schemas';
		$schemes  = $this->cacheService->get( $cacheKey );

		if (!empty($schemes)) {
			return $schemes;
		}

		$schemes = [
			'googlepay' => [
				'oneoff' => [],
				'recurring' => [],
			],
			'applepay' => [
				'oneoff' => [],
				'recurring' => [],
			],

		];

		try {
			$items = $this->cardClient->getActiveCardSchemes($countryCode, $currencyCode);

			if (empty($items)) {
				return $schemes;
			}

			foreach ($items as $method) {
				if ( 'googlepay' === $method['name'] || 'applepay' === $method['name'] ) {
					$type = $method['name'];
					$mode = $method['transaction_mode'];
					foreach ($method['card_schemes'] as $scheme) {
						$schemes[$type][$mode][] = $scheme['name'];
					}
				}
			}

			$this->cacheService->set($cacheKey, $schemes, MINUTE_IN_SECONDS);
		} catch (Exception $e) {
			LogService::getInstance()->error(__METHOD__ . ' - Failed to get active card schemas.', $e->getMessage());
		}

		return $schemes;
	}

	public function getCheckoutDetail() {
		if (is_admin()) {
			return [];
		}

		$requiresShipping = false;
		$subTotal         = 0;
		if ($this->isProduct()) {
			$product = $this->getProduct();
			if ($product) {
				$requiresShipping = $product->needs_shipping();
				$subTotal         = $product->get_price();
			}
		} elseif ($this->isCartOrCheckout()) {
			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}
	
			WC()->cart->calculate_totals();
			$requiresShipping = WC()->cart->needs_shipping();
			$subTotal         = WC()->cart->get_total( false );
		}

		$countryCode = wc_get_base_location()['country'];
		return [
			'autoCapture'   => $this->cardGateway->is_capture_immediately(),
			'countryCode' => $countryCode,
			'currencyCode' => get_woocommerce_currency(),
			'requiresShipping'    => $requiresShipping,
			'requiresPhone' => 'required' === get_option('woocommerce_checkout_phone_field', 'required'),
			'allowedCardNetworks' => $this->getActiveCardSchemes($countryCode, strtolower(get_woocommerce_currency())),
			'totalPriceLabel' => get_bloginfo('name'),
			'totalPriceStatus' => 'ESTIMATED',
			'subTotal' => $subTotal,
		];

	}

	public function getExpressCheckoutScriptData($isBlock) {
		$data = [];
		try {
			$data = [
				'ajaxUrl' => WC_AJAX::get_endpoint('%%endpoint%%'),
				'env' => $this->is_sandbox() ? 'demo' : 'prod',
				'locale' => Util::getLocale(),
				'isProductPage' => $this->isProduct(),
				'login_confirmation' => $this->getLoginConfirmationSettings(),
				'googlePayEnabled' => $this->isMethodEnabled('google_pay'),
				'applePayEnabled' => $this->isMethodEnabled('apple_pay'),
				'supportedProductTypes' => $this->getSupportedProductTypes(),
				'merchantInfo' => array_merge(
					Util::getMerchantInfoFromJwtToken($this->cardClient->getToken()),
					['businessName' => get_bloginfo('name')],
				),
				'button' => [
					'mode' => $this->hasSubscriptionProduct() ? 'recurring' : 'payment',
					'buttonType' => $this->getButtonType(),
					'theme' => $this->getButtonTheme(),
					'height' => self::BUTTON_SIZE_MAP[$this->getButtonSize()],
				],
				'checkout' => $this->getCheckoutDetail(),
				'nonce'              => [
					'payment'                   => wp_create_nonce('wc-airwallex-express-checkout'),
					'shipping'                  => wp_create_nonce('wc-airwallex-express-checkout-shipping'),
					'updateShipping'           => wp_create_nonce('wc-airwallex-express-checkout-update-shipping-method'),
					'checkout'                  => wp_create_nonce('woocommerce-process_checkout'),
					'addToCart'               => wp_create_nonce('wc-airwallex-express-checkout-add-to-cart'),
					'startPaymentSession' => wp_create_nonce('wc-airwallex-express-checkout-start-payment-session'),
					'estimateCart' => wp_create_nonce('wc-airwallex-express-checkout-estimate-cart'),
				],
				'errorMsg' => [
					'cannotShowPaymentSheet' => __('Failed to start the payment, please refresh and try again.', 'airwallex-online-payments-gateway')
				],
				'transactionId' => Util::generateUuidV4(),
				'supports' => $this->supports,
			];

			return $data;
		} catch (Exception $ex) {
			LogService::getInstance()->error(__METHOD__ . ' Cannot load the express checkout script data', $ex->getMessage());
			return [];
		}
	}

	/**
	 * Get is capture immediately option
	 *
	 * @return bool
	 */
	public function is_capture_immediately() {
		return in_array( $this->cardGateway->get_option( 'capture_immediately' ), array( true, 'yes' ), true );
	}

	public function subscription_payment_information( $paymentMethodName, $subscription ) {
		$customerId = $subscription->get_customer_id();
		if ( $subscription->get_payment_method() !== $this->id || ! $customerId ) {
			return $paymentMethodName;
		}
		//add additional payment details
		return $paymentMethodName;
	}

	public function do_subscription_payment( $amount, $order ) {

		try {
			$subscriptionId            = $order->get_meta( '_subscription_renewal' );
			$subscription              = wcs_get_subscription( $subscriptionId );
			$originalOrderId           = $subscription->get_parent();
			$originalOrder             = wc_get_order( $originalOrderId );
			$airwallexCustomerId       = $originalOrder->get_meta( 'airwallex_customer_id' );
			$airwallexPaymentConsentId = $originalOrder->get_meta( 'airwallex_consent_id' );
			$cardClient                = new CardClient();
			$paymentIntent             = $cardClient->createPaymentIntent( $amount, $order->get_id(), false, $airwallexCustomerId );
			$paymentIntentAfterCapture = $cardClient->confirmPaymentIntent( $paymentIntent->getId(), [ 'payment_consent_reference' => [ 'id' => $airwallexPaymentConsentId ] ] );

			if ( $paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED ) {
				( new LogService() )->debug( 'capture successful', $paymentIntentAfterCapture->toArray() );
				$order->add_order_note( 'Airwallex payment capture success' );
				$order->payment_complete( $paymentIntent->getId() );
			} else {
				( new LogService() )->error( 'capture failed', $paymentIntentAfterCapture->toArray() );
				$order->add_order_note( 'Airwallex payment failed capture' );
			}
		} catch ( Exception $e ) {
			( new LogService() )->error( 'do_subscription_payment failed', $e->getMessage() );
		}
	}

	public function process_payment( $order_id ) {
		// Create payment intent
		$response = [];
		try {
			$order = wc_get_order( $order_id );

			if ( empty( $order ) ) {
				LogService::getInstance()->debug(__METHOD__ . ' can not find order', array( 'orderId' => $order_id ) );
				throw new Exception( 'Order not found: ' . $order_id );
			}

			$orderContainsSubscription = $this->orderService->containsSubscription( $order->get_id() );
			// we cannot create intent with amount 0, for subscription product with free trail, we need to go with the create consent flow
			if ( 0 == $order->get_total() && $orderContainsSubscription ) {
				return [
					'result' => 'success',
					'redirect' => apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order ),
				];
			}

			$apiClient = CardClient::getInstance();

			// create customer if subscription 
			$airwallexCustomerId = null;
			if ( $orderContainsSubscription ) {
				$airwallexCustomerId = $this->orderService->getAirwallexCustomerId( $order->get_customer_id( '' ), $apiClient );
			}

			LogService::getInstance()->debug(__METHOD__ . ' before create intent', array( 'orderId' => $order_id ) );
			$paymentIntent = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $this->is_submit_order_details(), $airwallexCustomerId );
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntent->getId() );

			$order->update_meta_data( '_tmp_airwallex_payment_intent', $paymentIntent->getId() );
			$order->save();
			WC()->session->set( 'airwallex_order', $order_id );

			$confirmationUrl  = $this->get_payment_confirmation_url();
			$confirmationUrl .= ( strpos( $confirmationUrl, '?' ) === false ) ? '?' : '&';
			$confirmationUrl .= "order_id={$order_id}&intent_id={$paymentIntent->getId()}";
			$response         = [
				'result' => 'success',
				'payload' => [
					'paymentIntentId' => $paymentIntent->getId(),
					'orderId'       => $order_id,
					'createConsent' => ! empty( $airwallexCustomerId ),
					'customerId'    => ! empty( $airwallexCustomerId ) ? $airwallexCustomerId : '',
					'currency'      => $order->get_currency( '' ),
					'clientSecret'  => $paymentIntent->getClientSecret(),
					'autoCapture'   => $this->cardGateway->is_capture_immediately(),
					'confirmationUrl' => $confirmationUrl,
				],
			];
			LogService::getInstance()->debug(
				__METHOD__ . ' receive create payment intent response',
				array(
					'response' => $response,
					'session'  => array(
						'cookie' => WC()->session->get_session_cookie(),
						'data'   => WC()->session->get_session_data(),
					),
				),
				WC()->session->get('airwallex_express_checkout_payment_method')
			);
		} catch ( Exception $e ) {
			LogService::getInstance()->error( __METHOD__ . 'create payment intent failed', $e->getMessage(), WC()->session->get('airwallex_express_checkout_payment_method') );
			$response = [
				'success' => false,
				'message' => __('Failed to complete payment.', 'airwallex-online-payments-gateway'),
			];
		}

		return $response;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order           = wc_get_order( $order_id );
		$paymentIntentId = $order->get_transaction_id();
		$apiClient       = CardClient::getInstance();
		try {
			$refund  = $apiClient->createRefund( $paymentIntentId, $amount, $reason );
			$metaKey = $refund->getMetaKey();
			if ( ! $order->meta_exists( $metaKey ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: Placeholder 1: Airwallex refund ID. */
						__( 'Airwallex refund initiated: %s', 'airwallex-online-payments-gateway' ),
						$refund->getId()
					)
				);
				$order->add_meta_data( $metaKey, array( 'status' => Refund::STATUS_CREATED ) );
				$order->save();
			} else {
				throw new Exception( "refund {$refund->getId()} already exist.", '1' );
			}
			LogService::getInstance()->debug( __METHOD__ . " - Order: {$order_id}, refund initiated, {$refund->getId()}" );
		} catch ( \Exception $e ) {
			LogService::getInstance()->debug( __METHOD__ . " - Order: {$order_id}, refund failed, {$e->getMessage()}" );
			return new WP_Error( $e->getCode(), 'Refund failed, ' . $e->getMessage() );
		}

		return true;
	}

	public static function getMetaData() {
		$settings = self::getSettings();

		$data = [
			'enabled' => isset($settings['enabled']) ? $settings['enabled'] : 'no',
			'methods' => isset($settings['payment_methods']) ? $settings['payment_methods'] : '',
		];

		return $data;
	}
}
