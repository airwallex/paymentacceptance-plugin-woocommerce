<?php

namespace Airwallex\Gateways;

use Airwallex\Services\LogService;
use Airwallex\Struct\Refund;
use Airwallex\WeChatClient;
use Exception;
use WC_Payment_Gateway;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WeChat extends WC_Payment_Gateway {

	use AirwallexGatewayTrait;

	const ROUTE_SLUG           = 'airwallex_wechat';
	public $method_title       = 'Airwallex - WeChat Pay';
	public $method_description = '';
	public $title              = 'Airwallex - WeChat Pay';
	public $description        = '';
	public $icon               = '';
	public $id                 = 'airwallex_wechat';
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);
	public $logService;

	public function __construct() {
		$this->plugin_id = AIRWALLEX_PLUGIN_NAME;
		$this->init_settings();
		$this->description = $this->get_option( 'description' );
		if ( $this->get_client_id() && $this->get_api_key() ) {
			$this->method_description = __( 'Accept only WeChat Pay payments with your Airwallex account.', 'airwallex-online-payments-gateway' );
			$this->form_fields        = $this->get_form_fields();
		}
		$this->title      = $this->get_option( 'title' );
		$this->logService = new LogService();
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}



	public function get_form_fields() {
		return apply_filters( // phpcs:ignore
			'wc_airwallex_settings', // phpcs:ignore
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'airwallex-online-payments-gateway' ),
					'label'       => __( 'Enable Airwallex WeChat Pay', 'airwallex-online-payments-gateway' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'airwallex-online-payments-gateway' ),
					'default'     => __( 'WeChat Pay', 'airwallex-online-payments-gateway' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'airwallex-online-payments-gateway' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		$return = array(
			'result' => 'success',
		);
		WC()->session->set( 'airwallex_order', $order_id );
		$return['redirect'] = $this->get_payment_url( 'airwallex_payment_method_wechat' );
		return $return;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order           = wc_get_order( $order_id );
		$paymentIntentId = $order->get_transaction_id();
		$apiClient       = WeChatClient::getInstance();
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
				add_post_meta( $order->get_id(), $metaKey, array( 'status' => Refund::STATUS_CREATED ) );
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

	public function output( $attrs ) {
		if ( is_admin() || empty( WC()->session ) ) {
			$this->logService->debug( 'Update wechat payment shortcode.', array(), LogService::WECHAT_ELEMENT_TYPE );
			return;
		}

		$shortcodeAtts = shortcode_atts(
			array(
				'style' => '',
				'class' => '',
			),
			$attrs,
			'airwallex_payment_method_wechat'
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

			$apiClient                 = WeChatClient::getInstance();
			$paymentIntent             = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $this->is_submit_order_details() );
			$paymentIntentId           = $paymentIntent->getId();
			$paymentIntentClientSecret = $paymentIntent->getClientSecret();
			$confirmationUrl           = $this->get_payment_confirmation_url();
			$isSandbox                 = $this->is_sandbox();
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntentId );

			$this->logService->debug(
				'Redirect to the wechat payment page',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::WECHAT_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/wechat-shortcode.php';
		} catch ( Exception $e ) {
			$this->logService->error( 'Wechat payment action failed', $e->getMessage(), LogService::WECHAT_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}
}
