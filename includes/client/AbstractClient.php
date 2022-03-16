<?php

namespace Airwallex;

use Airwallex\Client\HttpClient;
use Airwallex\Services\CacheService;
use Airwallex\Struct\Customer;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;

abstract class AbstractClient
{
    const AUTH_URL_LIVE = 'https://pci-api.airwallex.com/api/v1/';
    const AUTH_URL_SANDBOX = 'https://pci-api-demo.airwallex.com/api/v1/';
    const PCI_URL_LIVE = 'https://pci-api.airwallex.com/api/v1/';
    const PCI_URL_SANDBOX = 'https://pci-api-demo.airwallex.com/api/v1/';

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
     * @return AbstractClient
     */
    final public static function getInstance()
    {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    final public function getAuthUrl($action)
    {
        return ($this->isSandbox ? self::AUTH_URL_SANDBOX : self::AUTH_URL_LIVE) . $action;
    }

    final public function getPciUrl($action)
    {
        return ($this->isSandbox ? self::PCI_URL_SANDBOX : self::PCI_URL_LIVE) . $action;
    }

    final protected function getCacheService()
    {
        if (!isset($this->cacheService)) {
            $this->cacheService = new CacheService($this->apiKey);
        }
        return $this->cacheService;
    }

    /**
     * @throws Exception
     */
    final public function getToken()
    {
        if (!empty($this->token) && $this->tokenExpiry > time()) {
            return $this->token;
        }

        $cachedTokenData = $this->getCacheService()->get('token');
        if (!empty($cachedTokenData['token']) && !empty($cachedTokenData['expiry']) && $cachedTokenData['expiry'] > time()) {
            $this->token = $cachedTokenData['token'];
            $this->tokenExpiry = $cachedTokenData['expiry'];
            return $this->token;
        }

        $this->doAuth();

        if (empty($this->token)) {
            throw new Exception('Unable to authorize API');
        }

        return $this->token;
    }

    final public function getHttpClient()
    {
        return new HttpClient();
    }

    /**
     * @throws Exception
     */
    final protected function doAuth()
    {
        $client = $this->getHttpClient();
        $response = $client->call('POST', $this->getAuthUrl('authentication/login'), null, [
            'x-client-id' => $this->clientId,
            'x-api-key' => $this->apiKey,
        ]);
        $this->token = $response->data['token'];
        $this->tokenExpiry = strtotime($response->data['expires_at']) - 10;
        $this->getCacheService()->set('token', [
            'token' => $this->token,
            'expiry' => $this->tokenExpiry,
        ]);
    }

    /**
     * @param $amount
     * @param $orderId
     * @param bool $withDetails
     * @param null $customerId
     * @return PaymentIntent
     * @throws Exception
     */
    final public function createPaymentIntent($amount, $orderId, $withDetails = false, $customerId = null)
    {
        $client = $this->getHttpClient();
        $order = wc_get_order((int)$orderId);
        $orderNumber = ($orderNumber = $order->get_meta('_order_number'))?$orderNumber:$orderId;
        $data = [
                'amount' => $amount,
                'currency' => $order->get_currency(),
                'descriptor' => str_replace('%order%', $orderId, $this->paymentDescriptor),
                'metadata'=>[
                    'wp_order_id'=>$orderId,
                ],
                'merchant_order_id' => $orderNumber,
                'order' => [
                    'type' => 'physical_goods',
                ],
                'request_id' => uniqid(),
            ]
            + ($customerId !== null ? ['customer_id' => $customerId] : []);

        if (mb_strlen($data['descriptor']) > 32) {
            $data['descriptor'] = mb_substr($data['descriptor'], 0, 32);
        }

        if ($withDetails) {

            $orderData = [
                'type' => 'physical_goods',
                'test' => 1,
                'products' => [],
            ];

            foreach ($order->get_items() as $item) {

                $price = 0;
                if (is_callable([$item, 'get_total'])) {
                    $price = $item->get_quantity() ? $item->get_total() / $item->get_quantity() : 0;
                }

                if (is_callable([$item, 'get_product'])) {
                    $sku = $item->get_product() ? $item->get_product()->get_sku() : null;
                } else {
                    $sku = uniqid();
                }

                $orderData['products'][] = [
                    'desc' => $item->get_name(),
                    'name' => (mb_strlen($item->get_name()) <= 120 ? $item->get_name() : mb_substr($item->get_name(), 0, 117) . '...'),
                    'quantity' => $item->get_quantity(),
                    'sku' => $sku,
                    'type' => 'physical',
                    'unit_price' => round($price, 2),
                ];
            }

            if ($order->has_shipping_address()) {
                $orderData['shipping'] = [
                    'address' => [
                        'city' => $order->get_shipping_city(),
                        'country_code' => $order->get_shipping_country(),
                        'postcode' => $order->get_shipping_postcode(),
                        'state' => $order->get_shipping_state(),
                        'street' => $order->get_shipping_address_1(),
                    ],
                    'first_name' => $order->get_shipping_first_name(),
                    'last_name' => $order->get_shipping_last_name(),
                    'shipping_method' => $order->get_shipping_method(),
                ];
            } elseif ($order->has_billing_address()) {
                $orderData['shipping'] = [
                    'address' => [
                        'city' => $order->get_billing_city(),
                        'country_code' => $order->get_billing_country(),
                        'postcode' => $order->get_billing_postcode(),
                        'state' => $order->get_shipping_state(),
                        'street' => $order->get_billing_address_1(),
                    ],
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'shipping_method' => $order->get_shipping_method(),
                ];
            }
            //var_dump($orderData);die;
            $data['order'] = $orderData;
        }

        $response = $client->call(
            'POST',
            $this->getPciUrl('pa/payment_intents/create'),
            json_encode($data),
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
            $this->getAuthorizationRetryClosure()
        );

        if (empty($response->data['id'])) {
            throw new Exception('payment intent creation failed: ' . json_encode($response));
        }

        return new PaymentIntent($response->data);
    }


    /**
     * @param $paymentIntentId
     * @param $paymentConsentId
     * @return PaymentIntent
     * @throws Exception
     */
    final public function confirmPaymentIntent($paymentIntentId, $paymentConsentId)
    {
        if (empty($paymentIntentId)) {
            throw new Exception('payment intent id empty');
        }
        if (empty($paymentConsentId)) {
            throw new Exception('payment consent id empty');
        }
        $client = $this->getHttpClient();
        $response = $client->call(
            'POST',
            $this->getPciUrl('pa/payment_intents/' . $paymentIntentId . '/confirm'),
            json_encode([
                'payment_consent_reference' => [
                    'id' => $paymentConsentId,
                ],
                'request_id' => uniqid(),
            ]),
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );
        return new PaymentIntent($response->data);
    }

    /**
     * @param string $paymentIntentId
     * @return PaymentIntent
     * @throws Exception
     */
    final public function getPaymentIntent($paymentIntentId)
    {
        if (empty($paymentIntentId)) {
            throw new Exception('payment intent id empty');
        }
        $client = $this->getHttpClient();
        $response = $client->call(
            'GET',
            $this->getPciUrl('pa/payment_intents/' . $paymentIntentId),
            '',
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );

        if ($response->status !== 200) {
            throw new Exception('unable to get payment intent: ' . json_encode($response));
        }
        return new PaymentIntent($response->data);
    }

    /**
     * @param $paymentIntentId
     * @param $amount
     * @return PaymentIntent
     * @throws Exception
     */
    final public function capture($paymentIntentId, $amount)
    {
        if (empty($paymentIntentId)) {
            throw new Exception('payment intent id empty');
        }
        $client = $this->getHttpClient();
        $response = $client->call(
            'POST',
            $this->getPciUrl('pa/payment_intents/' . $paymentIntentId . '/capture'),
            json_encode(['amount' => $amount, 'request_id' => uniqid()]),
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );
        return new PaymentIntent($response->data);
    }

    /**
     * @param $paymentIntentId
     * @param $amount
     * @param $reason
     * @return Refund
     * @throws Exception
     */
    final public function createRefund($paymentIntentId, $amount = null, $reason = '')
    {
        if (empty($paymentIntentId)) {
            throw new Exception('payment intent id empty');
        }
        $client = $this->getHttpClient();
        if ($amount === null) {
            $paymentIntent = $this->getPaymentIntent($paymentIntentId);
            $amount = $paymentIntent->getCapturedAmount();
        }

        $response = $client->call(
            'POST',
            $this->getPciUrl('pa/refunds/create'),
            json_encode(
                [
                    'payment_intent_id' => $paymentIntentId,
                    'amount' => $amount,
                    'reason' => $reason,
                    'request_id' => uniqid(),
                ]
            ),
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );

        if (empty($response->data['id'])) {
            throw new Exception('refund creation failed: ' . json_encode($response));
        }

        return new Refund($response->data);

    }


    final public function createCustomer($wordpressCustomerId)
    {
        if (empty($wordpressCustomerId)) {
            throw new Exception('customer id must not be empty');
        }
        $client = $this->getHttpClient();

        $response = $client->call(
            'POST',
            $this->getPciUrl('pa/customers/create'),
            json_encode(
                [
                    'merchant_customer_id' => $wordpressCustomerId,
                    'request_id' => uniqid(),
                    //TODO add details
                ]
            ),
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );

        if (empty($response->data['id'])) {
            throw new Exception('customer creation failed: ' . json_encode($response));
        }

        return new Customer($response->data);
    }

    final public function getCustomer($wordpressCustomerId)
    {
        if (empty($wordpressCustomerId)) {
            throw new Exception('customer id must not be empty');
        }
        $client = $this->getHttpClient();

        $response = $client->call(
            'GET',
            $this->getPciUrl('pa/customers?' . http_build_query(
                    [
                        'merchant_customer_id' => $wordpressCustomerId,
                    ]
                )
            ),
            null,
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );
        if (empty($response->data['items'])) {
            return null;
        }
        return new Customer($response->data['items'][0]);
    }

    final public function getPaymentMethodTypes()
    {
        $client = $this->getHttpClient();
        $response = $client->call(
            'GET',
            $this->getPciUrl('pa/config/payment_method_types?' . http_build_query(
                    [
                        'active' => 'true',
                        '__resources' => 'true',
                    ]
                )
            ),
            null,
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );

        if (empty($response->data['items'])) {
            return null;
        }
        return $response->data['items'];
    }

    public function createCustomerClientSecret($airwallexCustomerId)
    {
        if (empty($airwallexCustomerId)) {
            throw new Exception('customer id must not be empty');
        }
        $client = $this->getHttpClient();

        $response = $client->call(
            'GET',
            $this->getPciUrl(sprintf('/pa/customers/%s/generate_client_secret', $airwallexCustomerId)),
            null,
            [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        );
        if (empty($response->data['client_secret'])) {
            throw new Exception('customer secret creation failed: ' . json_encode($response));
        }
        return $response->data['client_secret'];
    }

    public function getAuthorizationRetryClosure(){
        $me = $this;
        return function() use ($me){
            $me->doAuth();
            return 'Bearer ' . $me->getToken();
        };
    }
}
