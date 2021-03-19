<?php

namespace Airwallex;

use Airwallex\Gateways\WeChat;
use Airwallex\Struct\PaymentIntent;
use Exception;

class WeChatClient extends AbstractClient
{
    public function __construct()
    {
        $this->gateway           = new WeChat();
        $this->clientId          = $this->gateway->get_client_id();
        $this->apiKey            = $this->gateway->get_api_key();
        $this->isSandbox         = in_array($this->gateway->get_option('sandbox'), [true, 'yes'], true);
        $this->paymentDescriptor = '';
    }

    /**
     * @param string $paymentIntentId
     * @param string $clientSecret
     * @return PaymentIntent
     * @throws Exception
     * @deprecated not needed
     */
    final public function confirmPaymentIntent($paymentIntentId, $clientSecret)
    {

        $client = $this->getHttpClient();

        $data     = [
            'payment_method' => [
                'type' => 'wechatpay',
                'wechatpay' => [
                    'flow' => 'webqr',
                ],
            ],
        ];
        $response = $client->call(
            'POST',
            $this->getPciUrl('pa/payment_intents/' . $paymentIntentId . '/confirm'),
            json_encode($data),
            [
                'client-secret: ' . $clientSecret,
            ]
        );

        if (empty($response->data['id'])) {
            throw new Exception('payment intent confirmation failed: ' . json_encode($response));
        }

        return new PaymentIntent($response->data);
    }
}
