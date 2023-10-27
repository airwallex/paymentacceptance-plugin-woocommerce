<?php

namespace Airwallex\Services;

use Airwallex\Client\AbstractClient;
use Airwallex\Client\CardClient;
use Airwallex\Gateways\Card;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;
use WC_Order;

class OrderService {

	/**
	 * Get order by payment intent ID
	 *
	 * @param $paymentIntentId
	 * @return WC_Order|null
	 */
	public function getOrderByPaymentIntentId( $paymentIntentId ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'
            SELECT p.ID FROM ' . $wpdb->posts . ' p
                JOIN ' . $wpdb->postmeta . " pm ON (p.ID = pm.post_id AND pm.meta_key = '_transaction_id')
            WHERE
                p.post_type = 'shop_order'
                    AND
                pm.meta_value = %s",
				array(
					$paymentIntentId,
				)
			)
		);
		if ( $row ) {
			$order = wc_get_order( (int) $row->ID );
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}
		return null;
	}

	public function getRefundByAmountAndTime( $orderId, $amount ) {
		global $wpdb;

		$startTime = ( new \DateTime( 'now - 600 seconds', new \DateTimeZone( '+0000' ) ) )->format( 'Y-m-d H:i:s' );
		$endTime   = ( new \DateTime( 'now + 600 seconds', new \DateTimeZone( '+0000' ) ) )->format( 'Y-m-d H:i:s' );
		$row       = $wpdb->get_row(
			$wpdb->prepare(
				'
            SELECT p.ID FROM 
                             ' . $wpdb->posts . ' p
                             JOIN ' . $wpdb->postmeta . " pm ON (p.ID = pm.post_id AND pm.meta_key = '_refund_amount')
                             JOIN " . $wpdb->postmeta . " pm_payment ON (p.post_parent = pm_payment.post_id AND pm_payment.meta_key = '_payment_method' AND  pm_payment.meta_value LIKE %s)
                             LEFT JOIN " . $wpdb->postmeta . " pm_refund_id ON (p.ID = pm.post_id AND pm.meta_key = '_airwallex_refund_id')
            WHERE
                p.post_type = 'shop_order_refund'
                    AND
                p.post_parent = %s
                    AND
                p.post_date_gmt > %s
                    AND
                p.post_date_gmt < %s
                    AND
                pm.meta_value = %s
                    AND
                pm_refund_id.meta_value IS NULL",
				array(
					'airwallex_%',
					$orderId,
					$startTime,
					$endTime,
					number_format( $amount, 2 ),
				)
			)
		);
		if ( $row ) {
			return $row->ID;
		}
		return null;
	}

	/**
	 * Get WC refund id by airwallex refund ID
	 *
	 * @param string $refundId
	 * @return null|string
	 */
	public function getRefundIdByAirwallexRefundId( $refundId ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'
            SELECT p.ID FROM 
                             ' . $wpdb->posts . ' p
                             JOIN ' . $wpdb->postmeta . " pm ON (p.ID = pm.post_id AND pm.meta_key = '_airwallex_refund_id')
            WHERE
                p.post_type = 'shop_order_refund'
                    AND
                pm.meta_value = %s",
				array(
					$refundId,
				)
			)
		);
		if ( $row ) {
			return $row->ID;
		}
		return null;
	}

	/**
	 * Get WC order by airwallex refund ID
	 *
	 * @param string $refundId
	 * @return bool|WC_Order
	 */
	public function getOrderByAirwallexRefundId( $refundId ) {
		global $wpdb;

		$orderId = $wpdb->get_var(
			$wpdb->prepare(
				"
                SELECT wc_order.id
                FROM {$wpdb->posts} wc_order
                INNER JOIN {$wpdb->postmeta} order_meta ON wc_order.id = order_meta.post_id
                WHERE wc_order.post_type = 'shop_order' AND order_meta.meta_key = %s
            ",
				Refund::META_REFUND_ID . $refundId
			)
		);

		return empty( $orderId ) ? false : wc_get_order( $orderId );
	}

	/**
	 * Get airwallex customer ID
	 *
	 * @param int $wordpressCustomerId
	 * @param AbstractClient $client
	 * @return int|mixed
	 * @throws Exception
	 */
	public function getAirwallexCustomerId( $wordpressCustomerId, AbstractClient $client ) {

		if ( empty( $wordpressCustomerId ) ) {
			$wordpressCustomerId = uniqid();
		}

		$customer = $client->getCustomer( $wordpressCustomerId );
		if ( $customer ) {
			return $customer->getId();
		}
		$customer = $client->createCustomer( $wordpressCustomerId );
		return $customer->getId();
	}


	public function containsSubscription( $orderId ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $orderId ) || wcs_is_subscription( $orderId ) || wcs_order_contains_renewal( $orderId ) ) );
	}

	protected function getPendingPaymentOrders() {
		global $wpdb;
		return $wpdb->get_results(
			'
            SELECT p.ID FROM ' . $wpdb->posts . ' p
                JOIN ' . $wpdb->postmeta . " pm ON (p.ID = pm.post_id AND pm.meta_key = '_payment_method' AND pm.meta_value = 'airwallex_card')
            WHERE
                p.post_type = 'shop_order'
                    AND
                p.post_status = 'wc-pending'"
		);
	}

	public function checkPendingTransactions() {
		static $isStarted;
		if ( empty( $isStarted ) ) {
			$isStarted = true;
		} else {
			return;
		}

		$logService = new LogService();
		$logService->debug( '⏱ start checkPendingTransactions()' );
		$orders = $this->getPendingPaymentOrders();
		foreach ( $orders as $order ) {
			$order           = new WC_Order( (int) $order->ID );
			$paymentIntentId = $order->get_transaction_id();
			if ( $paymentIntentId ) {
				$paymentMethod = get_post_meta( $order->get_id(), '_payment_method', true );
				if ( Card::GATEWAY_ID === $paymentMethod ) {
					try {
						$paymentIntent = CardClient::getInstance()->getPaymentIntent( $paymentIntentId );
						( new OrderService() )->setPaymentSuccess( $order, $paymentIntent, 'cron' );
					} catch ( Exception $e ) {
						$logService->warning( 'checkPendingTransactions failed for order #' . $order->get_id() . ' with paymentIntent ' . $paymentIntentId );
					}
				}
			}
		}
	}

	public function setPaymentSuccess( \WC_Order $order, PaymentIntent $paymentIntent, $referrer = 'webhook' ) {
		$logService = new LogService();
		$logIcon    = ( 'webhook' === $referrer ? '🖧' : '⏱' );
		if ( PaymentIntent::STATUS_SUCCEEDED === $paymentIntent->getStatus() ) {
			$logService->debug( $logIcon . ' payment success', $paymentIntent->toArray() );
			$order->payment_complete( $paymentIntent->getId() );
		} elseif ( PaymentIntent::STATUS_REQUIRES_CAPTURE === $paymentIntent->getStatus() ) {
			$apiClient   = CardClient::getInstance();
			$cardGateway = new Card();
			if ( $cardGateway->is_capture_immediately() ) {
				$paymentIntentAfterCapture = $apiClient->capture( $paymentIntent->getId(), $paymentIntent->getAmount() );
				if ( $paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED ) {
					$order->payment_complete( $paymentIntent->getId() );
					$logService->debug( $logIcon . ' payment success', $paymentIntentAfterCapture->toArray() );
				} else {
					$logService->debug( $logIcon . ' capture failed', $paymentIntentAfterCapture->toArray() );
					$order->add_order_note( 'Airwallex payment failed capture' );
				}
			} else {
				$order->payment_complete( $paymentIntent->getId() );
				$order->add_order_note( 'Airwallex payment authorized' );
				$logService->debug(
					$logIcon . ' payment authorized',
					array(
						'order'          => $order->get_id(),
						'payment_intent' => $paymentIntent->getId(),
					)
				);
			}
		}
	}

	/**
	 * Set temporary order status after the payment is declined
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function setTemporaryOrderStateAfterDecline( $order ) {
		$orderStatus = get_option( 'airwallex_temporary_order_status_after_decline' );
		if ( $orderStatus ) {
			$order->update_status( $orderStatus, 'Airwallex status update (decline)' );
		}
	}

	/**
	 * Set pending status to order
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function setPendingStatus( $order ) {
		$orderStatus = get_option( 'airwallex_order_status_pending' );
		if ( $orderStatus ) {
			$order->update_status( $orderStatus, 'Airwallex status update (pending)' );
		}
	}

	/**
	 * Set authorized status to order
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function setAuthorizedStatus( $order ) {
		$orderStatus = get_option( 'airwallex_order_status_authorized' );
		if ( $orderStatus ) {
			$order->update_status( $orderStatus, 'Airwallex status update (authorized)' );
		}
	}
}
