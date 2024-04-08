<?php

namespace Airwallex\Gateways\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminSettings {
	public static function init() {
		add_action( 'woocommerce_settings_checkout', array( __CLASS__, 'output' ) );
		add_filter( 'wc_airwallex_settings_nav_tabs', array( __CLASS__, 'adminSettingsTabs' ), 20 );
		add_action( 'woocommerce_update_options_checkout', array( __CLASS__, 'save' ) );
	}

	public static function output() {
		global $current_section;
		do_action( 'woocommerce_airwallex_settings_checkout_' . $current_section );
	}

	public static function save() {
		global $current_section;
		if ( $current_section && ! did_action( 'woocommerce_update_options_checkout_' . $current_section ) ) {
			do_action( 'woocommerce_update_options_checkout_' . $current_section );
		}
	}

	public static function adminSettingsTabs( $tabs ) {
		// set the tab name for the first local payment method in alphabetical order
		// $tabs['airwallex_klarna'] = __( 'Local Payment Methods', 'airwallex-online-payments-gateway' );

		return $tabs;
	}
}
