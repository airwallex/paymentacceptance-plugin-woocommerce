<?php

namespace Airwallex\Gateways\Blocks;

use Airwallex\Gateways\Card;
use Airwallex\Services\Util;
use Airwallex\CardClient;
use Airwallex\Gateways\CardSubscriptions;
use Airwallex\Services\OrderService;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

class AirwallexCardWCBlockSupport extends AirwallexWCBlockSupport {

	protected $name = 'airwallex_card';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'airwallex-online-payments-gatewayairwallex_card_settings', array() );
		$this->enabled  = ! empty( $this->settings['enabled'] ) && in_array( $this->settings['enabled'], array( 'yes', 1, true, '1' ), true ) ? 'yes' : 'no';
		$this->gateway  = $this->canDoSubscription() ? new CardSubscriptions() : new Card();

		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'addPaymentIntent' ), 9999, 2 );
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

		return parent::get_payment_method_script_handles();
	}

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$data = array(
			'enabled'             => $this->is_active(),
			'name'                => $this->name,
			'title'               => $this->settings['title'],
			'description'         => $this->settings['description'],
			'checkout_form_type'  => $this->settings['checkout_form_type'],
			'payment_descriptor'  => $this->settings['payment_descriptor'],
			'capture_immediately' => in_array( $this->settings['capture_immediately'], array( true, 'yes' ), true ),
			'icons'               => $this->gateway->getCardLogos(),
			'environment'         => $this->gateway->is_sandbox() ? 'demo' : 'prod',
			'locale'              => Util::getLocale(),
			'confirm_url'         => $this->gateway->get_payment_confirmation_url(),
			'supports'            => $this->get_supported_features(),
		);

		return $data;
	}

	/**
	 * Create and set payment intent.
	 *
	 * This is configured to execute after legacy payment processing has
	 * happened on the woocommerce_rest_checkout_process_payment_with_context
	 * action hook.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function addPaymentIntent( PaymentContext $context, PaymentResult &$result ) {
		if ( $this->name === $context->payment_method && ! empty( $context->payment_data['is-airwallex-card-block'] ) ) {
			$order     = $context->order;
			$apiClient = CardClient::getInstance();

			$airwallexCustomerId = null;
			$orderService        = new OrderService();
			if ( $order->get_customer_id( '' ) || $orderService->containsSubscription( $order->get_id() ) ) {
				$airwallexCustomerId = $orderService->getAirwallexCustomerId( $order->get_customer_id( '' ), $apiClient );
			}

			$paymentIntent = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $this->gateway->is_submit_order_details(), $airwallexCustomerId );

			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntent->getId() );
			update_post_meta( $order->get_id(), '_tmp_airwallex_payment_intent', $paymentIntent->getId() );

			$paymentDetails['airwallexPaymentIntent'] = $paymentIntent->getId();
			$paymentDetails['wcOrderId']              = $order->get_id();
			$paymentDetails['airwallexCreateConsent'] = ! empty( $airwallexCustomerId );
			$paymentDetails['airwallexCustomerId']    = ! empty( $airwallexCustomerId ) ? $airwallexCustomerId : '';
			$paymentDetails['airwallexCurrency']      = $order->get_currency( '' );
			$paymentDetails['airwallexClientSecret']  = $paymentIntent->getClientSecret();
			$result->set_payment_details( $paymentDetails );
		}
	}
}
