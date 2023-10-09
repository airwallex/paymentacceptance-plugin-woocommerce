<?php

namespace Airwallex\Gateways\Blocks;

class AirwallexWeChatWCBlockSupport extends AirwallexWCBlockSupport
{
    protected $name = 'airwallex_wechat';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('airwallex-online-payments-gatewayairwallex_wechat_settings', []);
        $this->enabled = !empty($this->settings['enabled']) && in_array($this->settings['enabled'], ['yes', 1, true, '1'], true) ? 'yes' : 'no';
    }

    /**
     * Returns an associative array of data to be exposed for the payment method's client side.
     */
    public function get_payment_method_data()
    {
        $data = [
            'enabled' => $this->is_active(),
            'name' => $this->name,
            'title' => $this->settings['title'],
            'description' => $this->settings['description'],
        ];

        return $data;
    }
}
