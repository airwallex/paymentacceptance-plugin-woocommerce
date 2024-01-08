<?php

namespace Airwallex\Controllers;

use Airwallex\Client\CardClient;
use Airwallex\Gateways\Card;
use Airwallex\Gateways\Main;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use Airwallex\Services\WebhookService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Client\WeChatClient;
use Exception;
use WC_Order;

class AirwallexController {

	protected $logService;

	public function __construct() {
		$this->logService = new LogService();
	}

	public function cardPayment() {
		try {
			$apiClient = CardClient::getInstance();
			$orderId   = (int) WC()->session->get( 'airwallex_order' );
			if ( empty( $orderId ) ) {
				$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
			}
			$gateway = new Card();
			$order   = wc_get_order( $orderId );
			if ( empty( $order ) ) {
				throw new Exception( 'Order not found: ' . $orderId );
			}
			$paymentIntent             = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $gateway->is_submit_order_details() );
			$paymentIntentId           = $paymentIntent->getId();
			$paymentIntentClientSecret = $paymentIntent->getClientSecret();
			$confirmationUrl           = $gateway->get_payment_confirmation_url();
			$isSandbox                 = $gateway->is_sandbox();
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntentId );
			$this->logService->debug(
				'cardPayment()',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::CARD_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/card-payment.php';
			die;
		} catch ( Exception $e ) {
			( new LogService() )->error( 'card payment controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	public function dropInPayment() {
		try {
			$apiClient = CardClient::getInstance();
			$orderId   = (int) WC()->session->get( 'airwallex_order' );
			if ( empty( $orderId ) ) {
				$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
			}
			$gateway = new Main();
			$order   = wc_get_order( $orderId );
			if ( empty( $order ) ) {
				throw new Exception( 'Order not found: ' . $orderId );
			}
			$airwallexCustomerId = null;
			$orderService        = new OrderService();
			$isSubscription      = $orderService->containsSubscription( $order->get_id() );
			if ( $order->get_customer_id( '' ) || $isSubscription ) {
				$airwallexCustomerId = $orderService->getAirwallexCustomerId( $order->get_customer_id( '' ), $apiClient );
			}

			$paymentIntent             = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $gateway->is_submit_order_details(), $airwallexCustomerId );
			$paymentIntentId           = $paymentIntent->getId();
			$paymentIntentClientSecret = $paymentIntent->getClientSecret();
			$confirmationUrl           = $gateway->get_payment_confirmation_url();
			$isSandbox                 = $gateway->is_sandbox();
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntentId );
			$this->logService->debug(
				'dropInPayment()',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::CARD_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/drop-in-payment.php';
			die;
		} catch ( Exception $e ) {
			( new LogService() )->error( 'drop in payment controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	public function weChatPayment() {
		try {
			$apiClient = WeChatClient::getInstance();
			$orderId   = (int) WC()->session->get( 'airwallex_order' );
			if ( empty( $orderId ) ) {
				$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
			}
			$gateway = new WeChat();
			$order   = wc_get_order( $orderId );
			if ( empty( $order ) ) {
				throw new Exception( 'Order not found: ' . $orderId );
			}
			$paymentIntent             = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $gateway->is_submit_order_details() );
			$paymentIntentId           = $paymentIntent->getId();
			$paymentIntentClientSecret = $paymentIntent->getClientSecret();
			$confirmationUrl           = $gateway->get_payment_confirmation_url();
			$isSandbox                 = $gateway->is_sandbox();
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntentId );
			$this->logService->debug(
				'weChatPayment()',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::WECHAT_ELEMENT_TYPE
			);

			include AIRWALLEX_PLUGIN_PATH . '/html/wechat.php';
			die;
		} catch ( Exception $e ) {
			( new LogService() )->error( 'wechat payment controller action failed', $e->getMessage(), LogService::WECHAT_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	/**
	 * Card payment only
	 *
	 * @throws Exception
	 */
	public function asyncIntent() {
		try {
			$apiClient = CardClient::getInstance();
			if ( ! empty( $_GET['airwallexOrderId'] ) ) {
				$orderId = sanitize_text_field( wp_unslash( $_GET['airwallexOrderId'] ) );
				WC()->session->set( 'airwallex_order', $orderId );
			}
			if ( empty( $orderId ) ) {
				$orderId = (int) WC()->session->get( 'airwallex_order' );
			}
			if ( empty( $orderId ) ) {
				$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
			}
			$gateway = new Card();
			$order   = wc_get_order( $orderId );
			if ( empty( $order ) ) {
				$this->logService->debug( 'asyncIntent() can not find order', array( 'orderId' => $orderId ) );
				throw new Exception( 'Order not found: ' . $orderId );
			}
			$orderService        = new OrderService();
			$airwallexCustomerId = null;
			if ( $orderService->containsSubscription( $order->get_id() ) ) {
				$airwallexCustomerId = $orderService->getAirwallexCustomerId( $order->get_customer_id( '' ), $apiClient );
			}
			$this->logService->debug( 'asyncIntent() before create', array( 'orderId' => $orderId ) );
			$paymentIntent = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $gateway->is_submit_order_details(), $airwallexCustomerId );
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntent->getId() );

			$order->update_meta_data( '_tmp_airwallex_payment_intent', $paymentIntent->getId() );
			$order->save();

			header( 'Content-Type: application/json' );
			http_response_code( 200 );
			$response = array(
				'paymentIntent' => $paymentIntent->getId(),
				'orderId'       => $orderId,
				'createConsent' => ! empty( $airwallexCustomerId ),
				'customerId'    => ! empty( $airwallexCustomerId ) ? $airwallexCustomerId : '',
				'currency'      => $order->get_currency( '' ),
				'clientSecret'  => $paymentIntent->getClientSecret(),
			);
			$this->logService->debug(
				'asyncIntent() receive payment intent response',
				array(
					'response' => $response,
					'session'  => array(
						'cookie' => WC()->session->get_session_cookie(),
						'data'   => WC()->session->get_session_data(),
					),
				),
				LogService::CARD_ELEMENT_TYPE
			);
			echo wp_json_encode( $response );
			die;
		} catch ( Exception $e ) {
			$this->logService->error( 'async intent controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			http_response_code( 200 );
			echo wp_json_encode(
				array(
					'error' => 1,
				)
			);
			die;
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
							if ( in_array( $paymentIntent->getStatus(), PaymentIntent::SUCCESS_STATUSES ) ) {
								$this->logService->warning( __METHOD__ . ' return result does not match with intent status ', [
									'intentStatus' => $paymentIntent->getStatus(),
									'returnResult' => $awxReturnResult,
								] );
							} else {
								throw new Exception('Payment Incomplete: The transaction was not finalized by the user.');
							}
							break;
						default:
							break;
					}
				}
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
				if ( $paymentGateway instanceof Card ) {
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
}
