<?php

namespace Airwallex\Client;

use Airwallex\Gateways\WeChat;

class WeChatClient extends AbstractClient {

	public function __construct() {
		$this->gateway           = new WeChat();
		$this->clientId          = $this->gateway->get_client_id();
		$this->apiKey            = $this->gateway->get_api_key();
		$this->isSandbox         = in_array( get_option( 'airwallex_enable_sandbox' ), array( true, 'yes' ), true );
		$this->paymentDescriptor = '';
	}
}
