<?php

namespace Airwallex\Gateways;

use Airwallex\Client\CardClient;
use Airwallex\Gateways\Settings\AirwallexSettingsTrait;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
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
	use AirwallexSettingsTrait;

	const ROUTE_SLUG              = 'airwallex_card';
	const ROUTE_SLUG_WECHAT       = 'airwallex_wechat';
	const GATEWAY_ID              = 'airwallex_card';
	const DESCRIPTION_PLACEHOLDER = '<!-- -->';

	public $method_title = 'Airwallex - Cards';
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
		$this->description = $this->get_option( 'description' ) ? $this->get_option( 'description' ) : ( $this->get_option( 'checkout_form_type' ) === 'inline' ? self::DESCRIPTION_PLACEHOLDER : '' );
		if ( $this->get_client_id() && $this->get_api_key() ) {
			$this->method_description = __( 'Accept only credit and debit card payments with your Airwallex account.', 'airwallex-online-payments-gateway' );
			$this->form_fields        = $this->get_form_fields();
		}

		$this->title      = $this->get_option( 'title' );
		$this->tabTitle   = 'Cards';
		$this->logService = new LogService();
		$this->registerHooks();
	}

	public function registerHooks() {
		add_filter( 'wc_airwallex_settings_nav_tabs', array( $this, 'adminNavTab' ), 11 );
		add_action( 'woocommerce_airwallex_settings_checkout_' . $this->id, array( $this, 'enqueueAdminScripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function enqueueScriptsForEmbeddedCard() {
		if (!is_checkout()) {
			return;
		}

		wp_enqueue_script( 'airwallex-card-js' );
		wp_enqueue_style( 'airwallex-css' );
		$cardScriptData = [
 			'autoCapture' => $this->is_capture_immediately() ? 'true' : 'false',
			'errorMessage' => __( 'An error has occurred. Please check your payment details (%s)', 'airwallex-online-payments-gateway' ),
			'incompleteMessage' => __( 'Your credit card details are incomplete', 'airwallex-online-payments-gateway' ),
		];
		wp_add_inline_script( 'airwallex-card-js', 'var awxEmbeddedCardData=' . json_encode($cardScriptData), 'before' );
	}

	public function enqueueScriptForRedirectCard() {
		wp_enqueue_script( 'airwallex-redirect-js' );
	}

	public function enqueueAdminScripts() {
	}

	public function getCardLogos() {
		$cacheService = new CacheService( $this->get_api_key() );
		$logos        = $cacheService->get( 'cardLogos' );
		if ( empty( $logos ) ) {
			$paymentMethodTypes = $this->getPaymentMethodTypes();
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
		return empty( $logos ) ? [] : array_reverse( $logos );
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
		$this->enqueueScriptsForEmbeddedCard();
		
		if ( $this->get_option( 'checkout_form_type' ) === 'inline' ) {
			echo '<p>' . wp_kses_post( $this->description ) . '</p>';
			echo '<div id="airwallex-card"></div>';
		} else {
			parent::payment_fields();
		}
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
		try {
			$order   = wc_get_order( $order_id );
			if ( empty( $order ) ) {
				$this->logService->debug( __METHOD__ . ' - can not find order', array( 'orderId' => $order_id ) );
				throw new Exception( 'Order not found: ' . $order_id );
			}

			$apiClient = CardClient::getInstance();
			$orderService        = new OrderService();
			$airwallexCustomerId = null;
			if ( $orderService->containsSubscription( $order->get_id() ) ) {
				$airwallexCustomerId = $orderService->getAirwallexCustomerId( $order->get_customer_id( '' ), $apiClient );
			}

			$this->logService->debug( __METHOD__ . ' - before create intent', array( 'orderId' => $order_id ) );
			$paymentIntent = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $this->is_submit_order_details(), $airwallexCustomerId );
			$this->logService->debug(
				__METHOD__ . ' - payment intent created ',
				array(
					'paymentIntent' => $paymentIntent,
					'session'  => array(
						'cookie' => WC()->session->get_session_cookie(),
						'data'   => WC()->session->get_session_data(),
					),
				),
				LogService::CARD_ELEMENT_TYPE
			);

			WC()->session->set( 'airwallex_order', $order_id );
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntent->getId() );
			$order->update_meta_data( '_tmp_airwallex_payment_intent', $paymentIntent->getId() );
			$order->save();

			$result = ['result' => 'success'];
			if ( 'redirect' === $this->get_option( 'checkout_form_type' ) ) {
				$redirectUrl = $this->get_payment_url( 'airwallex_payment_method_card' );
				$redirectUrl .= ( strpos( $redirectUrl, '?' ) === false ) ? '?' : '&';
				$redirectUrl .= 'order_id=' . $order_id;
				$result['redirect'] = $redirectUrl;
			} else {
				$result += [
					'paymentIntent' => $paymentIntent->getId(),
					'orderId'       => $order_id,
					'createConsent' => ! empty( $airwallexCustomerId ),
					'customerId'    => ! empty( $airwallexCustomerId ) ? $airwallexCustomerId : '',
					'currency'      => $order->get_currency( '' ),
					'clientSecret'  => $paymentIntent->getClientSecret(),
					'messages'      => __('Processing payment.', 'airwallex-online-payments-gateway'),
				];
			}
			
			return $result;
		} catch ( Exception $e ) {
			$this->logService->error( __METHOD__ . ' - card payment create intent failed.', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			throw new Exception( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ) );
		}
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
			if (empty($orderId)) {
				$this->logService->debug(__METHOD__ . ' - Detect order id from URL.');
				$orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
			}
			$order = wc_get_order( $orderId );
			if ( empty( $order ) ) {
				throw new Exception( 'Order not found: ' . $orderId );
			}

			$paymentIntentId = WC()->session->get( 'airwallex_payment_intent_id' );
			$paymentIntentId = empty( $paymentIntentId ) ? $order->get_meta('_tmp_airwallex_payment_intent') : $paymentIntentId;
			$apiClient                 = CardClient::getInstance();
			$paymentIntent             = $apiClient->getPaymentIntent( $paymentIntentId );
			$paymentIntentClientSecret = $paymentIntent->getClientSecret();
			$airwallexCustomerId       = $paymentIntent->getCustomerId();
			$confirmationUrl           = $this->get_payment_confirmation_url();
			$isSandbox                 = $this->is_sandbox();
			$autoCapture = $this->is_capture_immediately();
			$orderService = new OrderService();
			$isSubscription = $orderService->containsSubscription( $orderId );

			$this->logService->debug(
				__METHOD__ . ' - Redirect to the card payment page',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::CARD_ELEMENT_TYPE
			);

			$this->enqueueScriptForRedirectCard();
			include AIRWALLEX_PLUGIN_PATH . '/html/card-payment-shortcode.php';
		} catch ( Exception $e ) {
			$this->logService->error( __METHOD__ . ' - Card payment action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	public static function getMetaData() {
		$settings = self::getSettings();

		$data = [
			'enabled' => isset($settings['enabled']) ? $settings['enabled'] : 'no',
			'checkout_form_type' => isset($settings['checkout_form_type']) ? $settings['checkout_form_type'] : '',
		];

		return $data;
	}
}
