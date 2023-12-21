<?php

namespace Airwallex\Client;

use Airwallex\Gateways\Card;

class CardClient extends AbstractClient {

	public function __construct() {
		$this->gateway           = new Card();
		$this->clientId          = $this->gateway->get_client_id();
		$this->apiKey            = $this->gateway->get_api_key();
		$this->isSandbox         = in_array( get_option( 'airwallex_enable_sandbox' ), array( true, 'yes' ), true );
		$this->paymentDescriptor = (string) $this->gateway->get_option( 'payment_descriptor' );
	}
}
