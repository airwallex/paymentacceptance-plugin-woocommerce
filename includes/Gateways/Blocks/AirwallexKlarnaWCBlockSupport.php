<?php
namespace Airwallex\Gateways\Blocks;

use Airwallex\Gateways\Klarna;
use Airwallex\Gateways\GatewayFactory;

defined( 'ABSPATH' ) || exit();

class AirwallexKlarnaWCBlockSupport extends AirwallexWCBlockSupport {
    protected $name = 'airwallex_klarna';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->gateway = GatewayFactory::create(Klarna::class);
		$this->settings = get_option( 'airwallex-online-payments-gatewayairwallex_klarna_settings', array() );
		$this->enabled  = ! empty( $this->settings['enabled'] ) && in_array( $this->settings['enabled'], array( 'yes', 1, true, '1' ), true ) ? 'yes' : 'no';
	}

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$data = parent::get_payment_method_data();
		$data['icon'] = $this->gateway->getIcon();
		$data['paymentMethodName'] = $this->gateway->paymentMethodName;
		$data['paymentMethodDocURL'] = $this->gateway->getPaymentMethodDocURL();
		$data += $this->gateway->getLPMScriptData();

		return $data;
	}
}
