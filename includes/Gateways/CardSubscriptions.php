<?php

namespace Airwallex\Gateways;

use Airwallex\Client\CardClient;
use Airwallex\Services\LogService;
use Airwallex\Struct\PaymentIntent;
use Exception;
use WC_Order;
use WC_Subscription;
use WC_Subscriptions_Cart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CardSubscriptions extends Card {

	public function __construct() {
		parent::__construct();
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'do_subscription_payment' ), 10, 2 );
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'subscription_payment_information' ), 10, 2 );
			add_filter( 'airwallexMustSaveCard', array( $this, 'mustSaveCard' ) );
		}
		$this->supports = array_merge(
			$this->supports,
			array(
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'multiple_subscriptions',
			)
		);
	}

	public function mustSaveCard( $mustSaveCard ) {
		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return true;
		}

		return $mustSaveCard;
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
			$cardClient                = CardClient::getInstance();
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
}
