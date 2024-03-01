<?php

namespace Airwallex\Gateways;

use Airwallex\Main;
use Airwallex\Client\CardClient;
use Exception;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;

trait AirwallexGatewayTrait {

	public $iconOrder = array(
		'card_visa'       => 1,
		'card_mastercard' => 2,
		'card_amex'       => 3,
		'card_jcb'        => 4,
	);

	public function sort_icons( $iconArray ) {
		uksort(
			$iconArray,
			function ( $a, $b ) {
				$orderA = isset( $this->iconOrder[ $a ] ) ? $this->iconOrder[ $a ] : 999;
				$orderB = isset( $this->iconOrder[ $b ] ) ? $this->iconOrder[ $b ] : 999;
				return $orderA - $orderB;
			}
		);
		return $iconArray;
	}

	public function get_client_id() {
		return get_option( 'airwallex_client_id' );
	}

	public function get_api_key() {
		return get_option( 'airwallex_api_key' );
	}

	public function is_submit_order_details() {
		return in_array( get_option( 'airwallex_submit_order_details' ), array( 'yes', 1, true, '1' ), true );
	}

	public function temporary_order_status_after_decline() {
		$temporaryOrderStatus = get_option( 'airwallex_temporary_order_status_after_decline' );
		return $temporaryOrderStatus ? $temporaryOrderStatus : 'pending';
	}

	public function is_sandbox() {
		return in_array( get_option( 'airwallex_enable_sandbox' ), array( true, 'yes' ), true );
	}

	public function isJsLoggingEnabled() {
		return in_array( get_option( 'do_js_logging' ), array( 'yes', 1, true, '1' ), true );
	}

	public function isRemoteLoggingEnabled() {
		return in_array( get_option( 'do_remote_logging' ), array( 'yes', 1, true, '1' ), true );
	}

	public function getPaymentFormTemplate() {
		return get_option( 'airwallex_payment_page_template' );
	}

	public function get_payment_url( $type ) {
		$template = get_option( 'airwallex_payment_page_template' );
		if ( 'wordpress_page' === $template ) {
			return $this->getPaymentPageUrl( $type );
		}

		return WC()->api_request_url( static::ROUTE_SLUG );
	}

	public function needs_setup() {
		return true;
	}

	public function get_payment_confirmation_url() {
		return WC()->api_request_url( Main::ROUTE_SLUG_CONFIRMATION );
	}

	public function init_settings() {
		parent::init_settings();
		$this->enabled = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}

	public function getPaymentPageUrl( $type, $fallback = '' ) {
		$pageId    = get_option( $type . '_page_id' );
		$permalink = ! empty( $pageId ) ? get_permalink( $pageId ) : '';

		if ( empty( $permalink ) ) {
			$permalink = empty( $fallback ) ? get_home_url() : $fallback;
		}

		return $permalink;
	}

	public static function getSettings() {
		return get_option(AIRWALLEX_PLUGIN_NAME . self::GATEWAY_ID . '_settings', []);
	}

	public function getPaymentMethodTypes() {
		$cacheService = new CacheService( $this->get_api_key() );
		$paymentMethodTypes = $cacheService->get( 'rawPaymentMethods' );

		if ( is_null( $paymentMethodTypes ) ) {
			$apiClient = CardClient::getInstance();
			try {
				$paymentMethodTypes = $apiClient->getPaymentMethodTypes();

				$cacheService->set( 'rawPaymentMethods', $paymentMethodTypes, HOUR_IN_SECONDS );
			} catch ( Exception $e ) {
				LogService::getInstance()->error(__METHOD__ . ' Failed to get payment method types.');
			}
		}

		return $paymentMethodTypes;
	}
}
