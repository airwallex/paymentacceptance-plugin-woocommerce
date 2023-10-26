<?php
/**
 * Plugin Name: Airwallex Online Payments Gateway
 * Plugin URI:
 * Description: Official Airwallex Plugin
 * Author: Airwallex
 * Author URI: https://www.airwallex.com
 * Version: 1.3.0
 * Requires at least: 4.5
 * Tested up to: 6.2
 * WC requires at least: 3.0
 * WC tested up to: 7.9
 * Text Domain: airwallex-online-payments-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'AIRWALLEX_VERSION', '1.3.0' );
define( 'AIRWALLEX_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'AIRWALLEX_PLUGIN_PATH', __DIR__ . '/' );
define( 'AIRWALLEX_PLUGIN_NAME', 'airwallex-online-payments-gateway' );

function airwallex_init() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="error"><p><strong>' . esc_html__( 'Airwallex requires WooCommerce to be installed and active.', 'airwallex-online-payments-gateway' ) . '</strong></p></div>';
			}
		);
		return;
	}
	require_once AIRWALLEX_PLUGIN_PATH . 'includes/Main.php';
	require_once AIRWALLEX_PLUGIN_PATH . 'includes/struct/AbstractBase.php';
	require_once AIRWALLEX_PLUGIN_PATH . 'includes/client/AbstractClient.php';
	require_once AIRWALLEX_PLUGIN_PATH . 'includes/gateways/AirwallexGatewayTrait.php';
	foreach ( glob( AIRWALLEX_PLUGIN_PATH . 'includes/*/*.php' ) as $includeFile ) {
		require_once $includeFile;
	}

	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once AIRWALLEX_PLUGIN_PATH . 'includes/gateways/blocks/AirwallexWCBlockSupport.php';
		require_once AIRWALLEX_PLUGIN_PATH . 'includes/gateways/blocks/AirwallexMainWCBlockSupport.php';
		require_once AIRWALLEX_PLUGIN_PATH . 'includes/gateways/blocks/AirwallexCardWCBlockSupport.php';
		require_once AIRWALLEX_PLUGIN_PATH . 'includes/gateways/blocks/AirwallexWeChatWCBlockSupport.php';
	}

	$airwallex = \Airwallex\Main::getInstance();
	$airwallex->init();
	add_action( 'wp_enqueue_scripts', array( $airwallex, 'addJsLegacy' ) );
}

add_action( 'plugins_loaded', 'airwallex_init' );
