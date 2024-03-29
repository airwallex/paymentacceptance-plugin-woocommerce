<?php

namespace Airwallex\Controllers;

use Airwallex\Services\LogService;
use Airwallex\Client\GatewayClient;
use Exception;

defined('ABSPATH') || exit();

class QuoteController {
	const CONFIGURATION_ERROR = 'configuration_error';

	protected $gatewayClient;

	public function __construct() {
		$this->gatewayClient = GatewayClient::getInstance();
	}

	public function createQuoteForCurrencySwitching() {
        check_ajax_referer('wc-airwallex-lpm-create-quote-currency-switcher', 'security');
		
		$paymentCurrency = isset($_POST['payment_currency']) ? wc_clean(wp_unslash($_POST['payment_currency'])) : '';
		$targetCurrency = isset($_POST['target_currency']) ? wc_clean(wp_unslash($_POST['target_currency'])) : '';

		try {
			LogService::getInstance()->debug(__METHOD__ . ' - Create quote for ' . $paymentCurrency . ' and ' . $targetCurrency);
			$paymentAmount = WC()->cart->get_total(false);

			$quote = $this->gatewayClient->createQuoteForCurrencySwitching(
				$paymentCurrency,
				$targetCurrency,
				$paymentAmount
			);

			LogService::getInstance()->debug(__METHOD__ . ' - Quote created.', $quote);
			$quoteArr = $quote->toArray();
			unset($quoteArr['id']);
			wp_send_json(
				[
					'success' => true,
					'quote' => $quoteArr,
				]
			);
		} catch (Exception $e) {
			LogService::getInstance()->error(__METHOD__ . ' - Failed to create quote.', $e->getMessage());
			wp_send_json([
				'success' => false,
				'message' => __('Failed to create quote.', 'airwallex-online-payments-gateway'),
			]);
		}
    }
}
