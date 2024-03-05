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
		$confirmPayload['return_url'] = $origin . WC_AJAX::get_endpoint("airwallex_3ds&intent_id={$paymentIntentId}&origin={$origin}");

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
}
