<?php

namespace Airwallex\Gateways\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

abstract class AirwallexWCBlockSupport extends AbstractPaymentMethodType
{
    public $enabled = 'yes';
    protected $gateway;

    /**
     * Returns whether this payment method is active.
     *
     * @return boolean
     */
    public function is_active()
    {
        return $this->enabled === 'yes';
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'airwallex-wc-blocks-integration',
            AIRWALLEX_PLUGIN_URL . '/build/index.js',
            [],
            AIRWALLEX_VERSION,
            true
        );

        return ['airwallex-wc-blocks-integration'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method only in the admin section.
     *
     * @return array
     */
    public function get_payment_method_script_handles_for_admin()
    {
        wp_register_script(
            'airwallex-wc-blocks-integration',
            AIRWALLEX_PLUGIN_URL . '/build/index.js',
            [],
            time(),
            true
        );

        return ['airwallex-wc-blocks-integration'];
    }
}
