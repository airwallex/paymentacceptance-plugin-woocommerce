<?php

namespace Airwallex\Gateways;

use Airwallex\Main;

trait AirwallexGatewayTrait
{
    public $iconOrder = [
        'card_visa' => 1,
        'card_mastercard' => 2,
        'card_amex' => 3,
        'card_jcb' => 4,
    ];

    public function sort_icons($iconArray)
    {
        uksort($iconArray, function($a, $b){
            $orderA = isset($this->iconOrder[$a])? $this->iconOrder[$a] : 999;
            $orderB = isset($this->iconOrder[$b])? $this->iconOrder[$b] : 999;
            return $orderA - $orderB;
        });
        return $iconArray;
    }

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

    public function temporary_order_status_after_decline()
    {
        return get_option('airwallex_temporary_order_status_after_decline')?:'pending';
    }

    public function is_sandbox()
    {
        return in_array(get_option('airwallex_enable_sandbox'), [true, 'yes'], true);
    }

    public function get_payment_url()
    {
        return WC()->api_request_url(static::ROUTE_SLUG);
    }

    public function needs_setup()
    {
        return true;
    }

    public function get_payment_confirmation_url()
    {
        return WC()->api_request_url(Main::ROUTE_SLUG_CONFIRMATION);
    }

    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }

    public function getPaymentPageUrl($type, $fallback = '') {
        $pageId = get_option($type . '_page_id');
        $permalink = !empty($pageId) ? get_permalink( $pageId ) : '';

        if (empty($permalink)) {
            $permalink = empty($fallback) ? get_home_url() : $fallback;
        }

	    return $permalink;
    }
}
