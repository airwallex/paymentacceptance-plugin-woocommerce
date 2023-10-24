<?php

namespace Airwallex\Gateways\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

abstract class AirwallexWCBlockSupport extends AbstractPaymentMethodType {

	public $enabled = 'yes';
	protected $gateway;

	/**
	 * Returns whether this payment method is active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'airwallex-wc-blocks-integration',
			AIRWALLEX_PLUGIN_URL . '/build/index.js',
			array(),
			AIRWALLEX_VERSION,
			true
		);

		return array( 'airwallex-wc-blocks-integration' );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method only in the admin section.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles_for_admin() {
		wp_register_script(
			'airwallex-wc-blocks-integration',
			AIRWALLEX_PLUGIN_URL . '/build/index.js',
			array(),
			time(),
			true
		);

		return array( 'airwallex-wc-blocks-integration' );
	}

	/**
	 * Whether the subscription plugin is installed
	 *
	 * @return boolean
	 */
	public function canDoSubscription() {
		return class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		return $this->gateway->supports;
	}
}
