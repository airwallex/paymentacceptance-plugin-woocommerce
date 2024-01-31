<?php

namespace Airwallex\Gateways\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airwallex\Gateways\Card;
use Airwallex\Controllers\OrderController;
use Airwallex\Controllers\PaymentIntentController;
use Airwallex\Services\OrderService;
use Airwallex\Client\CardClient;
use Airwallex\Controllers\GatewaySettingsController;
use Airwallex\Controllers\PaymentConsentController;
use Airwallex\Controllers\PaymentSessionController;
use Airwallex\Services\Util;
use Airwallex\Gateways\ExpressCheckout;
use Airwallex\Services\LogService;
use Airwallex\Services\CacheService;

class AirwallexExpressCheckoutWCBlockSupport extends AirwallexWCBlockSupport {

	protected $name                  = 'airwallex_express_checkout';
	protected $enableExpressCheckout = false;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings              = get_option( 'airwallex-online-payments-gatewayairwallex_card_settings', array() );
		$this->enabled               = ! empty( $this->settings['enabled'] ) && in_array( $this->settings['enabled'], array( 'yes', 1, true, '1' ), true ) ? 'yes' : 'no';
		$cardClient                  = new CardClient();
		$cacheService                = new CacheService(Util::getClientSecret());
		$orderService                = new OrderService();
		$this->gateway               = new ExpressCheckout(
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
		$this->enableExpressCheckout = $this->shouldDisplay(); 
	}

	/**
	 * Enqueues the style needed for the payment block.
	 *
	 * @return void
	 */
	public function enqueue_style() {
		wp_enqueue_style(
			'airwallex-css',
			AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout.css',
			array(),
			AIRWALLEX_VERSION
		);
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$this->enqueue_style();

		$dependencies = ['jquery', 'jquery-blockui'];
		// only load the external script if the payment method is enabled
		if ($this->enableExpressCheckout) {
			if ($this->gateway->isMethodEnabled('apple_pay')) {
				$this->gateway->loadAppleScript();
				$dependencies[] = 'airwallex-apple-pay-block';
			}
		}

		wp_register_script(
			'airwallex-wc-ec-blocks-integration',
			AIRWALLEX_PLUGIN_URL . '/build/airwallex-wc-ec-blocks.min.js',
			$dependencies,
			AIRWALLEX_VERSION,
			true
		);

		return array( 'airwallex-wc-ec-blocks-integration' );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method only in the admin section.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles_for_admin() {
		$this->enqueue_style();

		$dependencies = ['jquery'];
		// only load the external script if the payment method is enabled
		if ($this->enableExpressCheckout) {
			if ($this->gateway->isMethodEnabled('apple_pay')) {
				$this->gateway->loadAppleScript();
				$dependencies[] = 'airwallex-apple-pay-block';
			}
		}

		wp_register_script(
			'airwallex-wc-ec-blocks-integration',
			AIRWALLEX_PLUGIN_URL . '/build/airwallex-wc-ec-blocks.min.js',
			$dependencies,
			AIRWALLEX_VERSION,
			true
		);

		return array( 'airwallex-wc-ec-blocks-integration' );
	}

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		return $this->gateway->getExpressCheckoutScriptData(true);
	}

	public function shouldDisplay() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if (!isset($gateways['airwallex_card']) || !isset($gateways['airwallex_express_checkout'])) {
			return false;
		}

		if (empty($this->gateway->get_option('payment_methods'))) {
			return false;
		}

		if (!$this->gateway->get_api_key() || !$this->gateway->get_client_id()) {
			LogService::getInstance()->debug('API Key and client secret are not set correctly.');
			return false;
		}

		if (!is_ssl()) {
			LogService::getInstance()->debug('Airwallex Express Checkout requires SSL.');
			return false;
		}

		// Don't show on the cart or checkout page if items in the cart are not supported.
		if (
			$this->gateway->isCartOrCheckout()
			&& !$this->gateway->isCartItemsAllowed()
		) {
			return false;
		}

		// Don't show on cart if disabled.
		if (is_cart() && !$this->gateway->shouldShowButtonOnPage('cart')) {
			return false;
		}

		// Don't show on checkout if disabled.
		if (is_checkout() && !$this->gateway->shouldShowButtonOnPage('checkout')) {
			return false;
		}

		return true;
	}
}
