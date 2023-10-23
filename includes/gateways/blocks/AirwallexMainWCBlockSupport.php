<?php

namespace Airwallex\Gateways\Blocks;

use Airwallex\Gateways\Main;

class AirwallexMainWCBlockSupport extends AirwallexWCBlockSupport {

	protected $name = 'airwallex_main';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'airwallex-online-payments-gatewayairwallex_main_settings', array() );
		$this->enabled  = ! empty( $this->settings['enabled'] ) && in_array( $this->settings['enabled'], array( 'yes', 1, true, '1' ), true ) ? 'yes' : 'no';
		$this->gateway  = new Main();
	}

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$data = array(
			'enabled'     => $this->is_active(),
			'name'        => $this->name,
			'title'       => $this->settings['title'],
			'description' => $this->settings['description'],
			'methods'     => $this->settings['methods'],
			'icons'       => $this->getActivePaymentLogos(),
		);

		return $data;
	}

	public function getActivePaymentLogos() {
		$logos       = $this->gateway->getPaymentLogos();
		$chosenLogos = $this->gateway->get_option( 'icons' );

		$chosenLogoUrls = array();
		foreach ( $chosenLogos as $name ) {
			if ( ! empty( $logos[ $name ] ) ) {
				$chosenLogoUrls[ $name ] = $logos[ $name ];
			}
		}

		return $chosenLogoUrls;
	}
}
