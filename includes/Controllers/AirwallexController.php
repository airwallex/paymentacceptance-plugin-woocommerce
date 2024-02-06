<?php

namespace Airwallex\Controllers;

use Airwallex\Client\AbstractClient;
use Airwallex\Client\AdminClient;
use Airwallex\Client\CardClient;
use Airwallex\Client\MainClient;
use Airwallex\Gateways\Card;
use Airwallex\Gateways\Main;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use Airwallex\Services\WebhookService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Client\WeChatClient;
use Airwallex\Gateways\ExpressCheckout;
use Exception;
use WC_Order;

class AirwallexController {

	protected $logService;

	public function __construct() {
		$this->logService = new LogService();
	}

	private function getPaymentDetailForRedirect(AbstractClient $apiClient, $gateway) {
		$orderId = (int) WC()->session->get( 'airwallex_order' );
		$orderId = empty( $orderId ) ? (int) WC()->session->get( 'order_awaiting_payment' ) : $orderId;
		if (empty($orderId)) {
			$this->logService->debug(__METHOD__ . ' - Detect order id from URL.');
			$orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
		}
		$order   = wc_get_order( $orderId );
		if ( empty( $order ) ) {
			throw new Exception( 'Order not found: ' . $orderId );
		}

		$paymentIntentId = WC()->session->get( 'airwallex_payment_intent_id' );
		$paymentIntentId = empty( $paymentIntentId ) ? $order->get_meta('_tmp_airwallex_payment_intent') : $paymentIntentId;
		$paymentIntent   = $apiClient->getPaymentIntent( $paymentIntentId );
		$clientSecret = $paymentIntent->getClientSecret();
		$customerId = $paymentIntent->getCustomerId();
		$confirmationUrl = $gateway->get_payment_confirmation_url();
		$isSandbox       = $gateway->is_sandbox();

		return [$orderId, $paymentIntentId, $clientSecret, $customerId, $confirmationUrl, $isSandbox];
	}

	public function cardPayment() {
		try {
			$gateway = new Card();
			$apiClient = CardClient::getInstance();
			list( $orderId, $paymentIntentId, $paymentIntentClientSecret, $airwallexCustomerId, $confirmationUrl, $isSandbox ) = $this->getPaymentDetailForRedirect($apiClient, $gateway);
			$this->logService->debug(
				__METHOD__ . ' - Card payment redirect',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::CARD_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/card-payment.php';
			die;
		} catch ( Exception $e ) {
			LogService::getInstance()->error(__METHOD__ . ' - Card payment controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	public function dropInPayment() {
		try {
			$gateway = new Main();
			$apiClient = MainClient::getInstance();
			list( $orderId, $paymentIntentId, $paymentIntentClientSecret, $airwallexCustomerId, $confirmationUrl, $isSandbox ) = $this->getPaymentDetailForRedirect($apiClient, $gateway);
			
			$orderService = new OrderService();
			$order = wc_get_order( $orderId );
			$isSubscription = $orderService->containsSubscription( $orderId );
			$this->logService->debug(
				__METHOD__ . ' - Drop in payment redirect',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::DROP_IN_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/drop-in-payment.php';
			die;
		} catch ( Exception $e ) {
			LogService::getInstance()->error( __METHOD__ . ' - Drop in payment controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	public function weChatPayment() {
		try {
			$gateway = new WeChat();
			$apiClient = WeChatClient::getInstance();
			list( $orderId, $paymentIntentId, $paymentIntentClientSecret, $airwallexCustomerId, $confirmationUrl, $isSandbox ) = $this->getPaymentDetailForRedirect($apiClient, $gateway);
			$this->logService->debug(
				__METHOD__ . 'WeChat payment redirect',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::WECHAT_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/wechat.php';
			die;
		} catch ( Exception $e ) {
			( new LogService() )->error( __METHOD__ . ' - WeChat payment controller action failed', $e->getMessage(), LogService::WECHAT_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	public function processOrderPay() {
		try {
			if ( isset( $_POST['woocommerce_pay'], $_GET['key'] ) ) {
				$nonce_value = wc_get_var( $_REQUEST['woocommerce-pay-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.
	
				if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-pay' ) ) {
					throw new Exception( __( 'Invalid request.', 'airwallex-online-payments-gateway' ) );
				}
			} else {
				throw new Exception( __( 'Invalid request.', 'airwallex-online-payments-gateway' ) );
			}

			$order_key = isset($_GET['key']) ? wp_unslash( $_GET['key'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$order_id  = isset($_GET['order_id']) ? absint( $_GET['order_id'] ) : 0;
			$order     = wc_get_order( $order_id );
			if ( ! $order || ! hash_equals( $order_key, $order->get_order_key() ) || ! $order->needs_payment() ) {
				throw new Exception( __( 'You are not authorized to update this order.', 'airwallex-online-payments-gateway' ) );
			}

			$payment_method_id = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : false;
			if ( ! $payment_method_id ) {
				throw new Exception( __( 'Invalid payment method.', 'airwallex-online-payments-gateway' ) );
			}
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$payment_method     = isset( $available_gateways[ $payment_method_id ] ) ? $available_gateways[ $payment_method_id ] : false;
			if ( ! $payment_method ) {
				throw new Exception( __( 'Invalid payment method.', 'woocommerce' ) );
			}
			$order->set_payment_method( $payment_method );
			$order->save();

			$result = $payment_method->process_payment($order->get_id());

			wp_send_json($result);
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_send_json([
				'result' => 'fail',
				'error' => $e->getMessage(),
			]);
		}
	}

	private function getOrderAndPaymentIntentForConfirmation() {
		$orderId = (int) WC()->session->get( 'airwallex_order' );
		if ( empty( $orderId ) ) {
			$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
		}
		if ( empty( $orderId ) && ! empty( $_GET['order_id'] ) ) {
			$this->logService->debug( 'detected order id from URL', array( 'get' => $_GET ) );
			$orderId = (int) $_GET['order_id'];
		}

		if ( empty( $orderId ) ) {
			$this->logService->debug( 'getOrderAndPaymentIntentForConfirmation() do not have order id', array( 'orderId' => $orderId ) );
			throw new Exception( 'I tried hard, but no order was found for confirmation' );
		}

		$paymentIntentId = WC()->session->get( 'airwallex_payment_intent_id' );
		if ( empty( $paymentIntentId ) ) {
			$order = wc_get_order( $orderId );
			if ( $order ) {
				$paymentIntentId = $order->get_meta('_tmp_airwallex_payment_intent');
			}
		}

		if ( ! empty( $_GET['intent_id'] ) ) {
			$intentIdFromUrl = sanitize_text_field( wp_unslash( $_GET['intent_id'] ) );
			if ( ! empty( $paymentIntentId ) && $paymentIntentId !== $intentIdFromUrl ) {
				$this->logService->warning(
					'different intent ids from url and session',
					array(
						'from_session' => $paymentIntentId,
						'from_url'     => $intentIdFromUrl,
					)
				);
				if ( ! empty( $_GET['order_id'] ) ) {
					throw new Exception( 'different intent ids from url and session - fraud suspected' );
				}
			} else {
				$paymentIntentId = $intentIdFromUrl;
			}
		}
		return array(
			'order_id'          => $orderId,
			'payment_intent_id' => $paymentIntentId,
		);
	}

	public function paymentConfirmation() {
		try {
			$orderInformation = $this->getOrderAndPaymentIntentForConfirmation();
			$orderId          = $orderInformation['order_id'];
			$paymentIntentId  = $orderInformation['payment_intent_id'];
			$orderService     = new OrderService();

			$this->logService->debug(
				'paymentConfirmation() init',
				array(
					'paymentIntent' => $paymentIntentId,
					'orderId'       => $orderId,
					'session'       => array(
						'cookie' => WC()->session->get_session_cookie(),
						'data'   => WC()->session->get_session_data(),
					),
				)
			);

			$apiClient     = CardClient::getInstance();
			$paymentIntent = $apiClient->getPaymentIntent( $paymentIntentId );
			$this->logService->debug(
				'paymentConfirmation() payment intent',
				array(
					'paymentIntent' => $paymentIntent->toArray(),
				)
			);

			if ( ! empty( $_GET['awx_return_result'] ) ) {
				$this->handleRedirectWithReturnResult( $paymentIntent );
			}

			$order = wc_get_order( $orderId );

			if ( empty( $order ) ) {
				throw new Exception( 'Order not found: ' . $orderId );
			}

			if ( $paymentIntent->getPaymentConsentId() ) {
				$this->logService->debug( 'paymentConfirmation() save consent id', array( $paymentIntent->toArray() ) );
				$order->add_meta_data( 'airwallex_consent_id', $paymentIntent->getPaymentConsentId() );
				$order->add_meta_data( 'airwallex_customer_id', $paymentIntent->getCustomerId() );
				$order->save();
			}

			$this->handleStatusForConfirmation( $paymentIntent, $order );

			if ( number_format( $paymentIntent->getAmount(), 2 ) !== number_format( $order->get_total(), 2 ) ) {
				//amount mismatch
				$this->logService->error( 'paymentConfirmation() payment amounts did not match', array( number_format( $paymentIntent->getAmount(), 2 ), number_format( $order->get_total(), 2 ), $paymentIntent->toArray() ) );
				$this->setTemporaryOrderStateAfterDecline( $order );
				wc_add_notice( 'Airwallex payment error', 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				die;
			}

			if ( $paymentIntent->getStatus() === PaymentIntent::STATUS_SUCCEEDED ) {
				$order->payment_complete( $paymentIntentId );
				( new LogService() )->debug( 'paymentConfirmation() payment success during checkout', $paymentIntent->toArray() );
				$order->add_order_note( 'Airwallex payment complete' );
			} elseif ( $paymentIntent->getStatus() === PaymentIntent::STATUS_REQUIRES_CAPTURE ) {
				$orderService->setAuthorizedStatus( $order );
				$paymentGateway = wc_get_payment_gateway_by_order( $order );
				if ( $paymentGateway instanceof Card || $paymentGateway instanceof ExpressCheckout ) {
					if ( $paymentGateway->is_capture_immediately() ) {
						$this->logService->debug( 'paymentConfirmation() start capture', array( $paymentIntent->toArray() ) );
						$paymentIntentAfterCapture = $apiClient->capture( $paymentIntentId, $paymentIntent->getAmount() );
						if ( $paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED ) {
							$order->payment_complete( $paymentIntentId );
							$order->add_order_note( 'Airwallex payment captured' );
							( new LogService() )->debug( 'paymentConfirmation() payment success during checkout', $paymentIntent->toArray() );
						} else {
							( new LogService() )->error( 'paymentConfirmation() payment capture failed during checkout', $paymentIntentAfterCapture->toArray() );
							$this->setTemporaryOrderStateAfterDecline( $order );
							wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
							wp_safe_redirect( wc_get_checkout_url() );
							die;
						}
					} else {
						$this->logService->debug( 'paymentConfirmation() payment complete', array() );
						$order->payment_complete( $paymentIntentId );
						$order->add_order_note( 'Airwallex payment authorized' );
					}
				}
			} elseif ( in_array( $paymentIntent->getStatus(), PaymentIntent::PENDING_STATUSES, true ) ) {
				$orderService->setPendingStatus( $order );
			}
			WC()->cart->empty_cart();
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			die;
		} catch ( Exception $e ) {
			$this->logService->error( 'paymentConfirmation() payment confirmation controller action failed', $e->getMessage() );
			if ( ! empty( $order ) ) {
				$this->setTemporaryOrderStateAfterDecline( $order );
			}
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	/**
	 * Handle the redirect if awx_return_result is available in the redirect url.
	 * 
	 * Some payment method supports different return url for success/back/cancel payment.
	 * If the payment is not successful, we should redirect the shopper back to the checkout page.
	 * 
	 * @param PaymentIntent $paymentIntent
	 */
	private function handleRedirectWithReturnResult( $paymentIntent ) {
		$awxReturnResult = wc_clean( $_GET['awx_return_result'] );
		$latestAttempt = $paymentIntent->getLatestPaymentAttempt();
		// the awx_return_result param is only available for Klarna right now 
		if ( ! empty( $latestAttempt['payment_method']['type'] ) && 'klarna' === $latestAttempt['payment_method']['type'] ) {
			switch ($awxReturnResult) {
				case 'success':
					break;
				case 'failure':
				case 'cancel':
				case 'back':
					if ( in_array( $paymentIntent->getStatus(), PaymentIntent::SUCCESS_STATUSES, true ) ) {
						$this->logService->warning( __METHOD__ . ' Return result does not match with intent status. ', [
							'intentStatus' => $paymentIntent->getStatus(),
							'returnResult' => $awxReturnResult,
						] );
					} else {
						$this->logService->debug( __METHOD__ . ' Payment Incomplete: The transaction was not finalized by the user. Return result ' . $awxReturnResult );
						wp_safe_redirect( wc_get_checkout_url() );
						die;
					}
					break;
				default:
					break;
			}
		}
	}

	/**
	 * Set temporary order status after payment is declined
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	private function setTemporaryOrderStateAfterDecline( $order ) {
		( new OrderService() )->setTemporaryOrderStateAfterDecline( $order );
	}

	public function webhook() {
		$body = file_get_contents( 'php://input' );
		$this->logService->debug( '🖧 webhook body', array( 'body' => $body ) );
		$webhookService = new WebhookService();
		try {
			$webhookService->process( $this->getRequestHeaders(), $body );
			http_response_code( 200 );
			header( 'Content-Type: application/json' );
			echo wp_json_encode( array( 'success' => 1 ) );
			die;
		} catch ( Exception $exception ) {
			$this->logService->warning( 'webhook exception', array( 'msg' => $exception->getMessage() ) );
			http_response_code( 401 );
			header( 'Content-Type: application/json' );
			echo wp_json_encode( array( 'success' => 0 ) );
			die;
		}
	}

	private function getRequestHeaders() {
		$headers = array();
		if ( function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $k => $v ) {
				$headers[ strtolower( $k ) ] = $v;
			}
			return $headers;
		}

		foreach ( $_SERVER as $name => $value ) {
			if ( substr( $name, 0, 5 ) === 'HTTP_' ) {
				$headers[ str_replace( ' ', '-', strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ] = $value;
			}
		}
		return $headers;
	}

	protected function handleStatusForConfirmation( PaymentIntent $paymentIntent, WC_Order $order ) {
		$validStatuses = array_merge(
			array(
				PaymentIntent::STATUS_SUCCEEDED,
				PaymentIntent::STATUS_REQUIRES_CAPTURE,
			),
			PaymentIntent::PENDING_STATUSES
		);

		if ( ! in_array( $paymentIntent->getStatus(), $validStatuses, true ) ) {
			$this->logService->warning( 'paymentConfirmation() invalid status', array( $paymentIntent->toArray() ) );
			//no valid payment intent
			$this->setTemporaryOrderStateAfterDecline( $order );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
		if ( in_array( $paymentIntent->getStatus(), PaymentIntent::PENDING_STATUSES, true ) ) {
			$this->logService->debug( 'paymentConfirmation() pending status', array( $paymentIntent->toArray() ) );
			( new OrderService() )->setPendingStatus( $order );
		}
	}

	/**
	 * Log js errors on the server side
	 */
	public function jsLog() {
		$body = json_decode( file_get_contents( 'php://input' ), true );
		if ( empty( $body['lg'] ) ) {
			return;
		}

		foreach ( $body['lg'] as $log ) {
			if ( $log['l'] <= 3000 ) {
				$this->logService->debug( $log['m'] );
			} elseif ( $log['lg'] <= 4000 ) {
				$this->logService->warning( $log['m'] );
			} else {
				$this->logService->error( $log['m'] );
			}
		}
	}

	public function connectionTest() {
		check_ajax_referer('wc-airwallex-admin-settings-connection-test', 'security');

		$clientId  = isset($_POST['client_id']) ? wc_clean(wp_unslash($_POST['client_id'])) : '';
		$apiKey    = isset($_POST['api_key']) ? wc_clean(wp_unslash($_POST['api_key'])) : '';
		$isSandbox = isset($_POST['is_sandbox']) ? wc_clean(wp_unslash($_POST['is_sandbox'])) : '';

		if (empty($clientId) || empty($apiKey)) {
			wp_send_json([
				'success' => false,
				'message' => __('You must enter your Client ID and API key before performing a connection test.', 'airwallex-online-payments-gateway'),
			]);
		}

		try {
			$apiClient = new AdminClient($clientId, $apiKey, 'checked' === $isSandbox);

			if ( $apiClient->testAuth() ) {
				wp_send_json([
					'success' => true,
					'message' => __('Connection test to Airwallex was successful.', 'airwallex-online-payments-gateway'),
				]);
			} else {
				wp_send_json([
					'success' => false,
					'message' => __('Invalid client id or api key, please check your entries.', 'airwallex-online-payments-gateway'),
				]);
			}
		} catch (Exception $e) {
			wp_send_json([
				'success' => false,
				'message' => __('Something went wrong.', 'airwallex-online-payments-gateway'),
			]);
		}
	}
}
