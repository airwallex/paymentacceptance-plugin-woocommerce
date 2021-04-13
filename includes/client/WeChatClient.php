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
}
