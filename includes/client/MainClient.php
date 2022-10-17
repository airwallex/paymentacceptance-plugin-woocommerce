<?php

namespace Airwallex;

class MainClient extends AbstractClient
{
    public function __construct()
    {
        $this->gateway           = new \Airwallex\Gateways\Main();
        $this->clientId          = $this->gateway->get_client_id();
        $this->apiKey            = $this->gateway->get_api_key();
        $this->isSandbox         = in_array($this->gateway->get_option('sandbox'), [true, 'yes'], true);
        $this->paymentDescriptor = '';
    }
}
