<?php

namespace Airwallex;

use Airwallex\Client\HttpClient;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Struct\Customer;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;
use Airwallex\Services\Util;

abstract class AbstractClient {

	const AUTH_URL_LIVE       = 'https://pci-api.airwallex.com/api/v1/';
	const AUTH_URL_SANDBOX    = 'https://pci-api-demo.airwallex.com/api/v1/';
	const PCI_URL_LIVE        = 'https://pci-api.airwallex.com/api/v1/';
	const PCI_URL_SANDBOX     = 'https://pci-api-demo.airwallex.com/api/v1/';
	const GENERAL_URL_LIVE    = 'https://api.airwallex.com/api/v1/';
	const GENERAL_URL_SANDBOX = 'https://api-demo.airwallex.com/api/v1/';
	const LOG_URL_LIVE        = 'https://api.airwallex.com/';
	const LOG_URL_SANDBOX     = 'https://api-demo.airwallex.com/';

	public static $instance;
	protected $clientId;
	protected $apiKey;
	protected $isSandbox;
	protected $gateway;
	protected $token;
	protected $tokenExpiry;
	protected $paymentDescriptor;
	protected $cacheService;

	/**
	 * Get instance of the AbstractClient class.
	 *
	 * @return AbstractClient
	 */
	final public static function getInstance() {
		if ( empty( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	final public function getAuthUrl( $action ) {
		return ( $this->isSandbox ? self::AUTH_URL_SANDBOX : self::AUTH_URL_LIVE ) . $action;
	}

	final public function getPciUrl( $action ) {
		return ( $this->isSandbox ? self::PCI_URL_SANDBOX : self::PCI_URL_LIVE ) . $action;
	}

	final public function getGeneralUrl( $action ) {
		return ( $this->isSandbox ? self::GENERAL_URL_SANDBOX : self::GENERAL_URL_LIVE ) . $action;
	}

	final public function getLogUrl( $action ) {
		return ( $this->isSandbox ? self::LOG_URL_SANDBOX : self::LOG_URL_LIVE ) . $action;
	}

	final protected function getCacheService() {
		if ( ! isset( $this->cacheService ) ) {
			$this->cacheService = new CacheService( $this->apiKey );
		}
		return $this->cacheService;
	}

	/**
	 * Get access token from airwallex and cache it for later use
	 *
	 * @throws Exception
	 */
	final public function getToken() {
		if ( ! empty( $this->token ) && $this->tokenExpiry > time() ) {
			return $this->token;
		}

		$cachedTokenData = $this->getCacheService()->get( 'token' );
		if ( ! empty( $cachedTokenData['token'] ) && ! empty( $cachedTokenData['expiry'] ) && $cachedTokenData['expiry'] > time() ) {
			$this->token       = $cachedTokenData['token'];
			$this->tokenExpiry = $cachedTokenData['expiry'];
			return $this->token;
		}

		$this->doAuth();

		if ( empty( $this->token ) ) {
			throw new Exception( 'Unable to authorize API' );
		}

		return $this->token;
	}

	final public function getHttpClient() {
		return new HttpClient();
	}

	/**
	 * Authenticate with airwallex using client id and api key
	 *
	 * @throws Exception
	 */
	final protected function doAuth() {
		if ( empty( $this->clientId ) || empty( $this->apiKey ) ) {
			return;
		}
		$client   = $this->getHttpClient();
		$response = $client->call(
			'POST',
			$this->getAuthUrl( 'authentication/login' ),
			wp_json_encode( $this->getReferrer() ),
			array(
				'x-client-id' => $this->clientId,
				'x-api-key'   => $this->apiKey,
			)
		);
		if ( ! empty( $response ) && ! empty( $response->data ) && ! empty( $response->data['token'] ) ) {
			$this->token       = $response->data['token'];
			$this->tokenExpiry = strtotime( $response->data['expires_at'] ) - 10;
			$this->getCacheService()->set(
				'token',
				array(
					'token'  => $this->token,
					'expiry' => $this->tokenExpiry,
				)
			);
		}
	}

	final public function testAuth() {
		$client   = $this->getHttpClient();
		$response = $client->call(
			'POST',
			$this->getAuthUrl( 'authentication/login' ),
			wp_json_encode( $this->getReferrer() ),
			array(
				'x-client-id' => $this->clientId,
				'x-api-key'   => $this->apiKey,
			)
		);
		return ! empty( $response->data['token'] );
	}

	/**
	 * Create new payment intent in airwallex
	 *
	 * @param $amount
	 * @param $orderId
	 * @param bool $withDetails
	 * @param null $customerId
	 * @return PaymentIntent
	 * @throws Exception
	 */
	final public function createPaymentIntent( $amount, $orderId, $withDetails = false, $customerId = null ) {
		$client      = $this->getHttpClient();
		$order       = wc_get_order( (int) $orderId );
		$orderNumber = $order->get_meta( '_order_number' );
		$orderNumber = $orderNumber ? $orderNumber : $orderId;
		$data        = array(
			'amount'            => $amount,
			'currency'          => $order->get_currency(),
			'descriptor'        => str_replace( '%order%', $orderId, $this->paymentDescriptor ),
			'metadata'          => array(
				'wp_order_id'     => $orderId,
				'wp_instance_key' => Main::getInstanceKey(),
			),
			'merchant_order_id' => $orderNumber,
			'return_url'        => WC()->api_request_url( Main::ROUTE_SLUG_CONFIRMATION ),
			'order'             => array(
				'type' => 'physical_goods',
			),
			'request_id'        => uniqid(),
		)
			+ ( null !== $customerId ? array( 'customer_id' => $customerId ) : array() );

		if ( mb_strlen( $data['descriptor'] ) > 32 ) {
			$data['descriptor'] = mb_substr( $data['descriptor'], 0, 32 );
		}

		// Set customer detail
		$customerAddress = array(
			'city'         => $order->get_billing_city(),
			'country_code' => $order->get_billing_country(),
			'postcode'     => $order->get_billing_postcode(),
			'state'        => $order->get_billing_state(),
			'street'       => $order->get_shipping_address_1(),
		);

		$customer = array(
			'email'                => $order->get_billing_email(),
			'first_name'           => $order->get_billing_first_name(),
			'last_name'            => $order->get_billing_last_name(),
			'merchant_customer_id' => $order->get_customer_id(),
			'phone_number'         => $order->get_billing_phone(),
		);

		if ( $order->get_billing_city() && $order->get_billing_country() && $order->get_shipping_address_1() ) {
			$data['customer']['address'] = $customerAddress;
		}

		$data['customer'] = null === $customerId ? $customer : null;

		// Set order details
		$orderData = array(
			'type'     => 'physical_goods',
			'products' => array(),
		);

		$orderItemTypes = array( 'line_item', 'shipping', 'fee', 'tax' );
		foreach ( $orderItemTypes as $type ) {
			foreach ( $order->get_items( $type ) as $item ) {
				$itemDetail = array(
					'name'       => ( mb_strlen( $item->get_name() ) <= 120 ? $item->get_name() : mb_substr( $item->get_name(), 0, 117 ) . '...' ),
					'desc'       => $item->get_name(),
					'quantity'   => $item->get_quantity(),
					'sku'        => '',
					'type'       => $item->get_type(),
					'unit_price' => is_callable( array( $item, 'get_total' ) ) ? $item->get_total() : 0,
				);

				if ( $item->is_type( 'line_item' ) ) {
					$product = $item->get_product();
					if ( ! empty( $product ) ) {
						$itemDetail['name']       = Util::truncateString( $product->get_name(), 117, '...' );
						$itemDetail['desc']       = $product->get_name();
						$itemDetail['sku']        = Util::truncateString( $product->get_sku(), 117, '...' );
						$itemDetail['type']       = $product->is_virtual() ? 'virtual' : 'physical';
						$itemDetail['unit_price'] = $product->get_price();
					} elseif ( $itemDetail['quantity'] > 0 ) {
						$itemDetail['unit_price'] /= $itemDetail['quantity'];
					}
				} elseif ( $item->is_type( 'shipping' ) ) {
					$itemDetail['sku'] = $item->get_method_id();
				} elseif ( $item->is_type( 'tax' ) ) {
					$itemDetail['unit_price'] = $item->get_tax_total() + $item->get_shipping_tax_total();
				}

				if ( $itemDetail['unit_price'] >= 0 ) {
					$itemDetail['unit_price'] = Util::round( $itemDetail['unit_price'], wc_get_price_decimals() );
					$orderData['products'][]  = $itemDetail;
				}
			}
		}

		if ( $order->has_shipping_address() ) {
			$shippingAddress       = array(
				'city'         => $order->get_shipping_city(),
				'country_code' => $order->get_shipping_country(),
				'postcode'     => $order->get_shipping_postcode(),
				'state'        => $order->get_shipping_state(),
				'street'       => $order->get_shipping_address_1(),
			);
			$orderData['shipping'] = array(
				'first_name'      => $order->get_shipping_first_name(),
				'last_name'       => $order->get_shipping_last_name(),
				'shipping_method' => $order->get_shipping_method(),
			);
			if ( $order->get_shipping_city() && $order->get_shipping_country() && $order->get_shipping_address_1() ) {
				$orderData['shipping']['address'] = $shippingAddress;
			}
		} elseif ( $order->has_billing_address() ) {

			$billingAddress        = array(
				'city'         => $order->get_billing_city(),
				'country_code' => $order->get_billing_country(),
				'postcode'     => $order->get_billing_postcode(),
				'state'        => $order->get_shipping_state(),
				'street'       => $order->get_billing_address_1(),
			);
			$orderData['shipping'] = array(
				'first_name'      => $order->get_billing_first_name(),
				'last_name'       => $order->get_billing_last_name(),
				'shipping_method' => $order->get_shipping_method(),
			);
			if ( $order->get_billing_city() && $order->get_billing_country() && $order->get_billing_address_1() ) {
				$orderData['shipping']['address'] = $billingAddress;
			}
		}

		$data['order'] = $orderData;

		$intent = $this->getCachedPaymentIntent( $data );
		if ( $intent && $intent instanceof PaymentIntent ) {
			$liveIntent = $this->getPaymentIntent( $intent->getId() );
			if ( $liveIntent ) {
				if ( number_format( $liveIntent->getAmount(), 2 ) === number_format( (float) $data['amount'], 2 ) ) {
					return $liveIntent;
				}
			}
		}

		$response = $client->call(
			'POST',
			$this->getPciUrl( 'pa/payment_intents/create' ),
			wp_json_encode(
				$data
				+ $this->getReferrer()
			),
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			),
			$this->getAuthorizationRetryClosure()
		);

		if ( empty( $response->data['id'] ) ) {
			throw new Exception( 'payment intent creation failed: ' . wp_json_encode( $response ) );
		}

		$returnIntent = new PaymentIntent( $response->data );
		$this->savePaymentIntentToCache( $data, $returnIntent );
		return $returnIntent;
	}

	protected function savePaymentIntentToCache( $data, PaymentIntent $paymentIntent ) {
		if ( isset( $data['request_id'] ) ) {
			unset( $data['request_id'] );
		}
		$key = 'payment-intent-' . md5( serialize( $data ) ); // phpcs:ignore
		return $this->getCacheService()->set( $key, $paymentIntent );
	}

	protected function getCachedPaymentIntent( $data ) {
		if ( isset( $data['request_id'] ) ) {
			unset( $data['request_id'] );
		}
		$key = 'payment-intent-' . md5( serialize( $data ) ); // phpcs:ignore
		return $this->getCacheService()->get( $key );
	}


	/**
	 * Send confirm payment intent request to airwallex
	 *
	 * @param $paymentIntentId
	 * @param $paymentConsentId
	 * @return PaymentIntent
	 * @throws Exception
	 */
	final public function confirmPaymentIntent( $paymentIntentId, $paymentConsentId ) {
		if ( empty( $paymentIntentId ) ) {
			throw new Exception( 'payment intent id empty' );
		}
		if ( empty( $paymentConsentId ) ) {
			throw new Exception( 'payment consent id empty' );
		}
		$client   = $this->getHttpClient();
		$response = $client->call(
			'POST',
			$this->getPciUrl( 'pa/payment_intents/' . $paymentIntentId . '/confirm' ),
			wp_json_encode(
				array(
					'payment_consent_reference' => array(
						'id' => $paymentConsentId,
					),
					'request_id'                => uniqid(),
				)
				+ $this->getReferrer()
			),
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);
		return new PaymentIntent( $response->data );
	}

	/**
	 * Get payment intent from airwallex by payment intent id
	 *
	 * @param string $paymentIntentId
	 * @return PaymentIntent
	 * @throws Exception
	 */
	final public function getPaymentIntent( $paymentIntentId ) {
		if ( empty( $paymentIntentId ) ) {
			throw new Exception( 'payment intent id empty' );
		}
		$client   = $this->getHttpClient();
		$response = $client->call(
			'GET',
			$this->getPciUrl( 'pa/payment_intents/' . $paymentIntentId ),
			null,
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);

		if ( 200 !== $response->status ) {
			throw new Exception( 'unable to get payment intent: ' . wp_json_encode( $response ) );
		}
		return new PaymentIntent( $response->data );
	}

	/**
	 * Send capture payment request to airwallex
	 *
	 * @param $paymentIntentId
	 * @param $amount
	 * @return PaymentIntent
	 * @throws Exception
	 */
	final public function capture( $paymentIntentId, $amount ) {
		if ( empty( $paymentIntentId ) ) {
			throw new Exception( 'payment intent id empty' );
		}
		$client   = $this->getHttpClient();
		$response = $client->call(
			'POST',
			$this->getPciUrl( 'pa/payment_intents/' . $paymentIntentId . '/capture' ),
			wp_json_encode(
				array(
					'amount'     => $amount,
					'request_id' => uniqid(),
				)
				+ $this->getReferrer()
			),
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);
		return new PaymentIntent( $response->data );
	}

	/**
	 * Create refund in airwallex
	 *
	 * @param $paymentIntentId
	 * @param $amount
	 * @param $reason
	 * @return Refund
	 * @throws Exception
	 */
	final public function createRefund( $paymentIntentId, $amount = null, $reason = '' ) {
		if ( empty( $paymentIntentId ) ) {
			throw new Exception( 'payment intent id empty', '1' );
		}
		$client = $this->getHttpClient();
		if ( null === $amount ) {
			$paymentIntent = $this->getPaymentIntent( $paymentIntentId );
			$amount        = $paymentIntent->getCapturedAmount();
		}

		$response = $client->call(
			'POST',
			$this->getPciUrl( 'pa/refunds/create' ),
			wp_json_encode(
				array(
					'payment_intent_id' => $paymentIntentId,
					'amount'            => $amount,
					'reason'            => $reason,
					'request_id'        => uniqid(),
				)
				+ $this->getReferrer()
			),
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);

		if ( empty( $response->data['id'] ) ) {
			throw new Exception( 'refund creation failed: ' . wp_json_encode( $response ), '1' );
		}

		return new Refund( $response->data );
	}


	final public function createCustomer( $wordpressCustomerId ) {
		if ( empty( $wordpressCustomerId ) ) {
			throw new Exception( 'customer id must not be empty' );
		}
		$client   = $this->getHttpClient();
		$response = $client->call(
			'POST',
			$this->getPciUrl( 'pa/customers/create' ),
			wp_json_encode(
				array(
					'merchant_customer_id' => $wordpressCustomerId,
					'request_id'           => uniqid(),
					//TODO add details
				)
				+ $this->getReferrer()
			),
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);

		if ( empty( $response->data['id'] ) ) {
			throw new Exception( 'customer creation failed: ' . wp_json_encode( $response ) );
		}

		return new Customer( $response->data );
	}

	final public function getCustomer( $wordpressCustomerId ) {
		if ( empty( $wordpressCustomerId ) ) {
			throw new Exception( 'customer id must not be empty' );
		}
		$client = $this->getHttpClient();

		$response = $client->call(
			'GET',
			$this->getPciUrl(
				'pa/customers?' . http_build_query(
					array(
						'merchant_customer_id' => $wordpressCustomerId,
					)
				)
			),
			null,
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);
		if ( empty( $response->data['items'] ) ) {
			return null;
		}
		return new Customer( $response->data['items'][0] );
	}

	final public function getPaymentMethodTypes() {
		$client   = $this->getHttpClient();
		$response = $client->call(
			'GET',
			$this->getPciUrl(
				'pa/config/payment_method_types?' . http_build_query(
					array(
						'active'      => 'true',
						'__resources' => 'true',
					)
				)
			),
			null,
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);
		if ( empty( $response->data['items'] ) ) {
			return null;
		}
		return $response->data['items'];
	}

	final public function getAccount() {
		$client   = $this->getHttpClient();
		$response = $client->call(
			'GET',
			$this->getGeneralUrl(
				'account'
			),
			null,
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);
		if ( empty( $response->data ) ) {
			return null;
		}
		return $response->data;
	}

	public function createCustomerClientSecret( $airwallexCustomerId ) {
		if ( empty( $airwallexCustomerId ) ) {
			throw new Exception( 'customer id must not be empty' );
		}
		$client = $this->getHttpClient();

		$response = $client->call(
			'GET',
			$this->getPciUrl( sprintf( '/pa/customers/%s/generate_client_secret', $airwallexCustomerId ) ),
			null,
			array(
				'Authorization' => 'Bearer ' . $this->getToken(),
			)
		);
		if ( empty( $response->data['client_secret'] ) ) {
			throw new Exception( 'customer secret creation failed: ' . wp_json_encode( $response ) );
		}
		return $response->data['client_secret'];
	}

	public function getAuthorizationRetryClosure() {
		$me = $this;
		return function () use ( $me ) {
			$me->doAuth();
			return 'Bearer ' . $me->getToken();
		};
	}

	protected function getReferrer() {
		return array(
			'referrer_data' => array(
				'type'    => 'woo_commerce',
				'version' => AIRWALLEX_VERSION,
			),
		);
	}
}
