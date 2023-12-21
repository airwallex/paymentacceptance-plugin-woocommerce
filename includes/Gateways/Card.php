<?php

namespace Airwallex\Gateways;

use Airwallex\Client\CardClient;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;
use WC_HTTPS;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Card extends WC_Payment_Gateway {

	use AirwallexGatewayTrait;

	const ROUTE_SLUG              = 'airwallex_card';
	const ROUTE_SLUG_WECHAT       = 'airwallex_wechat';
	const ROUTE_SLUG_ASYNC_INTENT = 'airwallex_async_intent';
	const GATEWAY_ID              = 'airwallex_card';
	public $method_title          = 'Airwallex - Cards';
	public $method_description;
	public $title       = 'Airwallex - Cards';
	public $description = '';
	public $icon        = AIRWALLEX_PLUGIN_URL . '/assets/images/airwallex_cc_icon.svg';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);
	public $logService;

	public function __construct() {

		$this->plugin_id = AIRWALLEX_PLUGIN_NAME;
		$this->init_settings();
		$this->description = $this->get_option( 'description' ) ? $this->get_option( 'description' ) : ( $this->get_option( 'checkout_form_type' ) === 'inline' ? '<!-- -->' : '' );
		if ( $this->get_client_id() && $this->get_api_key() ) {
			$this->method_description = __( 'Accept only credit and debit card payments with your Airwallex account.', 'airwallex-online-payments-gateway' );
			$this->form_fields        = $this->get_form_fields();
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		$this->title      = $this->get_option( 'title' );
		$this->logService = new LogService();
	}

	public function getCardLogos() {
		$cacheService = new CacheService( $this->get_api_key() );
		$logos        = $cacheService->get( 'cardLogos' );
		if ( empty( $logos ) ) {
			$apiClient          = CardClient::getInstance();
			$paymentMethodTypes = $apiClient->getPaymentMethodTypes();
			if ( $paymentMethodTypes ) {
				$logos = array();
				foreach ( $paymentMethodTypes as $paymentMethodType ) {
					if ( 'card' === $paymentMethodType['name'] && empty( $logos ) ) {
						foreach ( $paymentMethodType['card_schemes'] as $cardType ) {
							if ( isset( $cardType['resources']['logos']['svg'] ) ) {
								$logos[ 'card_' . $cardType['name'] ] = $cardType['resources']['logos']['svg'];
							}
						}
					}
				}
				$logos = $this->sort_icons( $logos );
				$cacheService->set( 'cardLogos', $logos, 86400 );
			}
		}
		return array_reverse( $logos );
	}

	public function get_icon() {
		$return = '';
		$logos  = $this->getCardLogos();
		if ( $logos ) {
			foreach ( $logos as $logo ) {
				$return .= '<img src="' . WC_HTTPS::force_https_url( $logo ) . '" class="airwallex-card-icon" alt="' . esc_attr( $this->get_title() ) . '" />';
			}
			apply_filters( 'woocommerce_gateway_icon', $return, $this->id ); // phpcs:ignore
			return $return;
		} else {
			return parent::get_icon();
		}
	}

	public function payment_fields() {
		if ( $this->get_option( 'checkout_form_type' ) === 'inline' ) {
			echo '<p>' . wp_kses_post( $this->description ) . '</p>';
			echo '<div id="airwallex-card"></div>';
		} else {
			parent::payment_fields();
		}
	}

	public function get_async_intent_url() {
		$url  = \WooCommerce::instance()->api_request_url( self::ROUTE_SLUG_ASYNC_INTENT );
		$url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . 'request_id=' . uniqid();
		return $url;
	}

	public function get_form_fields() {
		$isEmbeddedFieldsAllowed = ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.8.0', '>=' ) );
		return apply_filters( // phpcs:ignore
			'wc_airwallex_settings', // phpcs:ignore
			array(

				'enabled'                      => array(
					'title'       => __( 'Enable/Disable', 'airwallex-online-payments-gateway' ),
					'label'       => __( 'Enable Airwallex Card Payments', 'airwallex-online-payments-gateway' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'                        => array(
					'title'       => __( 'Title', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'airwallex-online-payments-gateway' ),
					'default'     => __( 'Credit Card', 'airwallex-online-payments-gateway' ),
				),
				'description'                  => array(
					'title'       => __( 'Description', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'airwallex-online-payments-gateway' ),
					'default'     => '',
				),
				'checkout_form_type'           => array(
					'title'       => __( 'Checkout form', 'airwallex-online-payments-gateway' ),
					'type'        => 'select',
					'description' => ( ! $isEmbeddedFieldsAllowed ? ' ' . __( 'Please upgrade WooCommerce to 4.8.0+ to use embedded credit card input fields', 'airwallex-online-payments-gateway' ) : '' ),
					'default'     => $isEmbeddedFieldsAllowed ? 'inline' : 'redirect',
					'options'     =>
						( $isEmbeddedFieldsAllowed ? array( 'inline' => __( 'Embedded', 'airwallex-online-payments-gateway' ) ) : array() )
						+ array( 'redirect' => __( 'On separate page', 'airwallex-online-payments-gateway' ) ),
				),
				'payment_descriptor'           => array(
					'title'             => __( 'Statement descriptor', 'airwallex-online-payments-gateway' ),
					'type'              => 'text',
					'custom_attributes' => array(
						'maxlength' => 28,
					),
					/* translators: Placeholder 1: Order number. */
					'description'       => __( 'Descriptor that will be displayed to the customer. For example, in customer\'s credit card statement. Use %order% as a placeholder for the order\'s ID.', 'airwallex-online-payments-gateway' ),
					/* translators: Placeholder 1: Order number. */
					'default'           => __( 'Your order %order%', 'airwallex-online-payments-gateway' ),
				),
				'capture_immediately'          => array(
					'title'       => __( 'Capture immediately', 'airwallex-online-payments-gateway' ),
					'label'       => __( 'yes', 'airwallex-online-payments-gateway' ),
					'type'        => 'checkbox',
					'description' => __( 'Choose this option if you do not want to rely on status changes for capturing the payment', 'airwallex-online-payments-gateway' ),
					'default'     => 'yes',
				),
				'capture_trigger_order_status' => array(
					'title'       => __( 'Capture status', 'airwallex-online-payments-gateway' ),
					'label'       => '',
					'type'        => 'select',
					'description' => __( 'When this status is assigned to an order, the funds will be captured', 'airwallex-online-payments-gateway' ),
					'options'     => array_merge( array( '' => '' ), wc_get_order_statuses() ),
					'default'     => '',
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		$return = array(
			'result' => 'success',
		);
		WC()->session->set( 'airwallex_order', $order_id );
		if ( 'redirect' === $this->get_option( 'checkout_form_type' ) ) {
			$return['redirect'] = $this->get_payment_url( 'airwallex_payment_method_card' );
		} else {
			$return['messages'] = '<!--Airwallex payment processing-->';
		}
		return $return;
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
			$this->logService->debug( __METHOD__ . " - Order: {$order_id}, refund initiated, {$refund->getId()}" );
		} catch ( \Exception $e ) {
			$this->logService->debug( __METHOD__ . " - Order: {$order_id}, refund failed, {$e->getMessage()}" );
			return new WP_Error( $e->getCode(), 'Refund failed, ' . $e->getMessage() );
		}

		return true;
	}

	/**
	 * Capture payment
	 *
	 * @param WC_Order $order
	 * @param float $amount
	 * @throws Exception
	 */
	public function capture( WC_Order $order, $amount = null ) {
		$apiClient       = CardClient::getInstance();
		$paymentIntentId = $order->get_transaction_id();
		if ( empty( $paymentIntentId ) ) {
			throw new Exception( 'No Airwallex payment intent found for this order: ' . esc_html( $order->get_id() ) );
		}
		if ( null === $amount ) {
			$amount = $order->get_total();
		}
		$paymentIntentAfterCapture = $apiClient->capture( $paymentIntentId, $amount );
		if ( $paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED ) {
			( new LogService() )->debug( 'capture successful', $paymentIntentAfterCapture->toArray() );
			$order->add_order_note( 'Airwallex payment capture success' );
		} else {
			( new LogService() )->error( 'capture failed', $paymentIntentAfterCapture->toArray() );
			$order->add_order_note( 'Airwallex payment failed capture' );
		}
	}

	public function is_captured( $order ) {
		$apiClient       = CardClient::getInstance();
		$paymentIntentId = $order->get_transaction_id();
		$paymentIntent   = $apiClient->getPaymentIntent( $paymentIntentId );
		if ( $paymentIntent->getStatus() === PaymentIntent::STATUS_SUCCEEDED ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get is capture immediately option
	 *
	 * @return bool
	 */
	public function is_capture_immediately() {
		return in_array( $this->get_option( 'capture_immediately' ), array( true, 'yes' ), true );
	}

	public function output( $attrs ) {
		if ( is_admin() || empty( WC()->session ) ) {
			$this->logService->debug( 'Update card payment shortcode.', array(), LogService::CARD_ELEMENT_TYPE );
			return;
		}

		$shortcodeAtts = shortcode_atts(
			array(
				'style' => '',
				'class' => '',
			),
			$attrs,
			'airwallex_payment_method_card'
		);

		try {
			$orderId = (int) WC()->session->get( 'airwallex_order' );
			if ( empty( $orderId ) ) {
				$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
			}
			$order = wc_get_order( $orderId );
			if ( empty( $order ) ) {
				throw new Exception( 'Order not found: ' . $orderId );
			}

			$apiClient                 = CardClient::getInstance();
			$paymentIntent             = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $this->is_submit_order_details() );
			$paymentIntentId           = $paymentIntent->getId();
			$paymentIntentClientSecret = $paymentIntent->getClientSecret();
			$confirmationUrl           = $this->get_payment_confirmation_url();
			$isSandbox                 = $this->is_sandbox();
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntentId );

			$this->logService->debug(
				'Redirect to the card payment page',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::CARD_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/card-payment-shortcode.php';
		} catch ( Exception $e ) {
			$this->logService->error( 'Card payment action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}
}
