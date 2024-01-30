<?php

namespace Airwallex\Controllers;

use Airwallex\Client\CardClient;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use Airwallex\Struct\PaymentIntent;
use Exception;
use WC_AJAX;

if (!defined('ABSPATH')) {
	exit;
}

class PaymentIntentController {
	protected $cardClient;
	protected $orderService;
	protected $cacheService;

	public function __construct(
		CardClient $cardClient,
		CacheService $cacheService,
		OrderService $orderService
	) {
		$this->cardClient   = $cardClient;
		$this->cacheService = $cacheService;
		$this->orderService = $orderService;
	}

	public function confirmPaymentIntent() {
		check_ajax_referer('wc-airwallex-express-checkout', 'security');

		$paymentIntentId              = isset($_POST['commonPayload']['paymentIntentId']) ? sanitize_text_field(wp_unslash($_POST['commonPayload']['paymentIntentId'])) : '';
		$confirmPayload               = isset($_POST['confirmPayload']) ? wc_clean(wp_unslash($_POST['confirmPayload'])) : [];
		$origin                       = isset($_POST['origin']) ? wc_clean(wp_unslash($_POST['origin'])) : get_site_url();
		$confirmPayload['return_url'] = $origin . WC_AJAX::get_endpoint("airwallex_3ds&intent_id={$paymentIntentId}&origin={$confirmPayload['integration_data']['origin']}");

		LogService::getInstance()->debug(__METHOD__ . " - Confirm intent {$paymentIntentId}.");
		LogService::getInstance()->debug(__METHOD__ . ' - Payload.', $confirmPayload);

		try {
			$paymentIntent = $this->cardClient->confirmPaymentIntent($paymentIntentId, $confirmPayload);

			LogService::getInstance()->debug(__METHOD__ . ' - Payment intent confirmed.', $paymentIntent);
			
			wp_send_json([
				'success' => true,
				'confirmation' => $paymentIntent->toArray(),
			]);
		} catch (Exception $e) {
			LogService::getInstance()->error(__METHOD__ . " - Confirm payment intent {$paymentIntentId} failed.", $e->getMessage());
			wp_send_json([
				'success' => false,
				'error' => [
					'message' => sprintf(
						/* translators: Placeholder 1: Error message */
						__('Failed to complete payment: %s', 'airwallex-online-payments-gateway'),
						$e->getMessage()
					),
				],
			]);
		}
	}

	public function threeDS() {
		$paymentConsentId = isset($_GET['consent_id']) ? sanitize_text_field(wp_unslash($_GET['consent_id'])) : '';
		$paymentIntentId  = isset($_GET['intent_id']) ? sanitize_text_field(wp_unslash($_GET['intent_id'])) : '';
		$origin           = isset($_GET['origin']) ? sanitize_text_field(wp_unslash($_GET['origin'])) : '';

		try {
			if (!$paymentIntentId && $paymentConsentId) {
				$paymentIntentId = $this->cacheService->get('awx_consent_to_intent_id_' . $paymentConsentId);
			}

			if (! $paymentIntentId ) {
				throw new Exception('Payment Intent ID is empty.');
			}

			$confirmPayload = [
				'type' => '3ds_continue',
				'three_ds' => [
					'acs_response' => urldecode(http_build_query(wc_clean(wp_unslash($_POST)))), // phpcs:ignore WordPress.Security.NonceVerification
					'return_url' => $origin . WC_AJAX::get_endpoint( sprintf(
						'airwallex_3ds&intent_id=%s&origin=%s',
						$paymentIntentId,
						$origin
					)),
				],
			];

			LogService::getInstance()->debug(__METHOD__ . " - Confirm intent {$paymentIntentId} continue.");
			LogService::getInstance()->debug(__METHOD__ . ' - payload', $confirmPayload);

			$paymentIntent = $this->cardClient->paymentConfirmContinue($paymentIntentId, $confirmPayload);

			$eventData = [
				'type' => '',
				'data' => [],
			];
			if ( in_array($paymentIntent->getStatus(), PaymentIntent::SUCCESS_STATUSES, true)) {
				$eventData['type'] = '3dsSuccess';
			} elseif (isset($paymentIntent->getNextAction()['stage']) && 'WAITING_USER_INFO_INPUT' === $paymentIntent->getNextAction()['stage']) {
				// if it still need challenge, send event to parent window to trigger 3dsChallenge flow.
				$eventData['type'] = '3dsChallenge';
			} else {
				$eventData['type'] = '3dsFailures';
				LogService::getInstance()->debug(__METHOD__ . ' - Confirm payment intent {$paymentIntentId} failed.', $paymentIntent);
			}

			LogService::getInstance()->debug(__METHOD__ . ' on ' . $eventData['type']);

			$eventData['data'] = $paymentIntent->toArray();
			http_response_code( 200 );
			header( 'Content-Type: text/html' );
			printf(
				/* translators: Placeholder 1: Event data object  */
				'<html><body><script type="text/javascript">window.parent.postMessage(%1$s, \'*\');</script></body></html>',
				wp_json_encode($eventData)
			);
			die;
		} catch (Exception $e) {
			LogService::getInstance()->error(__METHOD__ . " - Confirm payment intent {$paymentIntentId} failed.", $e->getMessage());
			$eventData = [
				'type' => '3dsFailures',
				'data' => [],
			];
			http_response_code( 200 );
			header( 'Content-Type: text/html' );
			printf(
				/* translators: Placeholder 1: Event data object  */
				'html><body><script type="text/javascript">window.parent.postMessage(%1$s, \'*\');</script></body></html>',
				wp_json_encode($eventData)
			);
			die;
		}
	}
}
