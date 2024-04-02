<?php

namespace Airwallex\Gateways;

defined( 'ABSPATH' ) || exit();

class GatewayFactory {
    private static $klarnaGateway;    

    /**
     * @param string $gateway
     *
     * @return AbstractAirwallexGateway|null
     */
    public static function create($gateway) {
        $name = self::getPropertyNameForGateway($gateway);
        if ( class_exists($gateway) && property_exists(self::class, $name) ) {
            if (self::${$name}) {
                return self::${$name};
            }
            return new $gateway();
        }

        return null;
    }

    /**
     * Set mock gateway instance for unit testing
     * 
     * @param string $name
     * @param AbstractAirwallexGateway $gateway
     */
    public static function setCustomGateway($name, $gateway) {
        if ( $gateway instanceof AbstractAirwallexGateway && property_exists(self::class, $name) ) {
            self::${$name} = $gateway;
        }
    }

    /**
     * Get the property name for the gateway
     * 
     * @param string $gateway
     * 
     * @return string
     */
    public static function getPropertyNameForGateway($gateway) {
        return lcfirst(substr($gateway, strrpos($gateway, '\\') + 1)) . 'Gateway';
    }
}