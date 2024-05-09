<?php

namespace Airwallex\Gateways\Blocks;

use Airwallex\Gateways\GatewayFactory;
use Airwallex\Gateways\WeChat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class AirwallexWeChatWCBlockSupport extends AirwallexWCBlockSupport {

	protected $name = 'airwallex_wechat';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'airwallex-online-payments-gatewayairwallex_wechat_settings', array() );
		$this->enabled  = ! empty( $this->settings['enabled'] ) && in_array( $this->settings['enabled'], array( 'yes', 1, true, '1' ), true ) ? 'yes' : 'no';
		$this->gateway  = GatewayFactory::create(WeChat::class);
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
			'supports'    => $this->get_supported_features(),
		);

		return $data;
	}
}
