<?php

namespace Airwallex;

use Airwallex\Client\HttpClient;
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
    protected $paymentDescriptor;

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

    /**
     * @throws Exception
     */
    final public function getToken()
    {
        if (empty($this->token)) {
            $this->doAuth();
        }
        if (empty($this->token)) {
            throw new Exception('Unable to authorize card API');
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
        $client      = $this->getHttpClient();
        $response    = $client->call('POST', $this->getAuthUrl('authentication/login'), null, [
            'x-client-id'=>$this->clientId,
            'x-api-key'=> $this->apiKey
        ]);
        $this->token = $response->data['token'];
    }

    /**
     * @param $amount
     * @param $orderId
     * @param bool $withDetails
     * @return PaymentIntent
     * @throws Exception
     */
    final public function createPaymentIntent($amount, $orderId, $withDetails = false)
    {
        $client = $this->getHttpClient();
        $order  = wc_get_order((int)$orderId);
        $data   = [
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'descriptor' => str_replace('%order%', $orderId, $this->paymentDescriptor),
            'merchant_order_id' => $orderId,
            'order' => [
                'type' => 'physical_goods',
            ],
            'request_id' => uniqid(),
        ];
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
                    'name' => $item->get_name(),
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
                'Authorization'=>'Bearer ' . $this->getToken(),
            ]
        );

        if (empty($response->data['id'])) {
            throw new Exception('payment intent creation failed: ' . json_encode($response));
        }

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
        $client   = $this->getHttpClient();
        $response = $client->call(
            'GET',
            $this->getPciUrl('pa/payment_intents/' . $paymentIntentId),
            '',
            [
                'Authorization'=>'Bearer ' . $this->getToken()
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
        $client   = $this->getHttpClient();
        $response = $client->call(
            'POST',
            $this->getPciUrl('pa/payment_intents/' . $paymentIntentId . '/capture'),
            json_encode(['amount' => $amount, 'request_id' => uniqid()]),
            [
                'Authorization'=>'Bearer ' . $this->getToken()
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
            $amount        = $paymentIntent->getCapturedAmount();
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
                'Authorization'=>'Bearer ' . $this->getToken()
            ]
        );

        if (empty($response->data['id'])) {
            throw new Exception('refund creation failed: ' . json_encode($response));
        }

        return new Refund($response->data);

    }
}
