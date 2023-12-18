<?php

namespace Airwallex\Controllers;

use Airwallex\Services\LogService;
use Airwallex\Client\CardClient;
use Exception;

if (!defined('ABSPATH')) {
	exit;
}

class PaymentSessionController {
	const CONFIGURATION_ERROR = 'configuration_error';

	protected $cardClient;

	public function __construct(CardClient $cardClient) {
		$this->cardClient = $cardClient;
	}

	public function startPaymentSession() {
		check_ajax_referer('wc-airwallex-express-checkout-start-payment-session', 'security');

		$validationURL = isset($_POST['validationURL']) ? wc_clean(wp_unslash($_POST['validationURL'])) : '';
		$origin        = isset($_POST['origin']) ? wc_clean(wp_unslash($_POST['origin'])) : '';

		LogService::getInstance()->debug(__METHOD__ . " - Start payment session for {$origin} with {$validationURL}.");
		try {
			$paymentSession = $this->cardClient->startPaymentSession($validationURL, $origin);

			LogService::getInstance()->debug(__METHOD__ . ' - Payment session started.');

			wp_send_json([
				'success' => true,
				'paymentSession' => $paymentSession->toArray(),
			]);
		} catch (Exception $e) {
			LogService::getInstance()->error(__METHOD__ . ' - Start payment session failed.', $e->getMessage());
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
