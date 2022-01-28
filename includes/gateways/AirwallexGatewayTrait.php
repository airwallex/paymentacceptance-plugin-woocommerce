<?php

namespace Airwallex\Gateways;

use Airwallex\Main;

trait AirwallexGatewayTrait
{
    public function get_client_id()
    {
        return get_option('airwallex_client_id');
    }

    public function get_api_key()
    {
        return get_option('airwallex_api_key');
    }

    public function is_submit_order_details()
    {
        return in_array(get_option('airwallex_submit_order_details'), ['yes', 1, true, '1'], true);
    }

    public function is_sandbox()
    {
        return in_array($this->get_option('sandbox'), [true, 'yes'], true);
    }

    public function get_payment_url()
    {
        return \WooCommerce::instance()->api_request_url(static::ROUTE_SLUG);
    }

    public function needs_setup()
    {
        return true;
    }

    public function get_payment_confirmation_url()
    {
        return \WooCommerce::instance()->api_request_url(Main::ROUTE_SLUG_CONFIRMATION);
    }

    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }
}
