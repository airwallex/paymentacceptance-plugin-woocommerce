<?php

namespace Airwallex\Client;

use Airwallex\Services\Util;
use Airwallex\Struct\Quote;
use Exception;

defined( 'ABSPATH' ) || exit;

class GatewayClient extends AbstractClient {
    /**
     * Get active payment method types from Airwallex
	 * 
	 * @param int $pageNum
	 * @param int $pageSize
	 * @return array Payment method types
     */
    public function getActivePaymentMethodTypes($pageNum = 0, $pageSize = 200) {
        $client   = $this->getHttpClient();
		$response = $client->call(
			'GET',
			$this->getPciUrl(
				'pa/config/payment_method_types?' . http_build_query(
					[
						'active'    => 'true',
						'__resources' => 'true',
                        'page_num'  => $pageNum,
						'page_size' => $pageSize,
					]
				)
			),
			null,
			[
				'Authorization' => 'Bearer ' . $this->getToken(),
			]
		);

		if ( empty( $response->data['items'] ) ) {
			return null;
		}
        
		return $response->data;
    }

	/**
	 * Get currency settings
	 * 
	 * @param int $pageNum
	 * @param int $pageSize
	 * @return array Payment method types
	 */
	public function getCurrencySettings($pageNum = 0, $pageSize = 200) {
        $client   = $this->getHttpClient();
		$response = $client->call(
			'GET',
			$this->getPciUrl(
				'pa/config/currency_settings?' . http_build_query(
					[
                        'page_num'  => $pageNum,
						'page_size' => $pageSize,
					]
				)
			),
			null,
			[
				'Authorization' => 'Bearer ' . $this->getToken(),
			]
		);

		if ( empty( $response->data['items'] ) ) {
			return null;
		}
        
		return $response->data;
	}

	/**
	 * Create quote for currency switching
	 * 
	 * @param string $paymentCurrency
	 * @param string $targetCurrency
	 * @param float $paymentAmount
	 * @return Quote quote created
	 */
	public function createQuoteForCurrencySwitching($paymentCurrency, $targetCurrency, $paymentAmount) {
		$cacheKey = 'quote_' . $paymentCurrency . $targetCurrency . $paymentAmount;
		$quote = $this->getCacheService()->get( $cacheKey );
		if ( isset( $quote['refresh_at'] ) && strtotime( $quote['refresh_at'] ) > time() ) {
			return new Quote($quote);
		}

		$client = $this->getHttpClient();
		$response = $client->call(
			'POST',
			$this->getPciUrl('pa/quotes/create'),
			wp_json_encode([
				'payment_currency' => $paymentCurrency,
				'target_currency' => $targetCurrency,
				'type' => 'currency_switcher',
				'payment_amount' => $paymentAmount,
				'request_id' => Util::generateUuidV4(),
			]),
			[
				'Authorization' => 'Bearer ' . $this->getToken(),
			],
			$this->getAuthorizationRetryClosure()
		);

		if (in_array($response->status, HttpClient::HTTP_STATUSES_FAILED, true)) {
			throw new Exception( 'Failed to create quote for currency switching, ' . isset($response->data['message']) ? $response->data['message'] : '' );
		}

		$this->getCacheService()->set($cacheKey, $response->data, HOUR_IN_SECONDS);

		return new Quote($response->data);
	}
}
