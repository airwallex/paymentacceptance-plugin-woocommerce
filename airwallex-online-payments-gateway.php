<?php
/**
 * Plugin Name: Airwallex Online Payments Gateway
 * Plugin URI: https://www.airwallex.com
 * Description: Official Airwallex Plugin
 * Author: Airwallex
 * Author URI: https://www.airwallex.com
 * Version: 1.7.0
 * Requires at least: 4.5
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.4
 * Text Domain: airwallex-online-payments-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'AIRWALLEX_VERSION', '1.7.0' );
define( 'AIRWALLEX_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'AIRWALLEX_PLUGIN_PATH', __DIR__ . '/' );
define( 'AIRWALLEX_PLUGIN_NAME', 'airwallex-online-payments-gateway' );

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

function airwallex_init() {

	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', function () {
			echo '<div class="error"><p><strong>' . esc_html__('Airwallex requires WooCommerce to be installed and active.', 'airwallex-online-payments-gateway') . '</strong></p></div>';
		});
		return;
	}

	$autoloader = AIRWALLEX_PLUGIN_PATH . '/vendor/autoload.php';
    if ( file_exists( $autoloader ) && PHP_VERSION_ID >= 50600 ) {
        require_once $autoloader;
    } else {
        require_once AIRWALLEX_PLUGIN_PATH . 'includes/Main.php';
        require_once AIRWALLEX_PLUGIN_PATH . 'includes/Struct/AbstractBase.php';
        require_once AIRWALLEX_PLUGIN_PATH . 'includes/Client/AbstractClient.php';
        require_once AIRWALLEX_PLUGIN_PATH . 'includes/Gateways/AirwallexGatewayTrait.php';
        foreach (glob(AIRWALLEX_PLUGIN_PATH . 'includes/*/*.php') as $includeFile) {
            require_once $includeFile;
        }
        require_once AIRWALLEX_PLUGIN_PATH . 'includes/Gateways/Blocks/AirwallexWCBlockSupport.php';
        foreach (glob(AIRWALLEX_PLUGIN_PATH . 'includes/*/*/*.php') as $includeFile) {
            require_once $includeFile;
        }
    }
    require_once $autoloader;

	$airwallex = \Airwallex\Main::getInstance();
	$airwallex->init();
	add_action('wp_enqueue_scripts', [$airwallex, 'addJsLegacy']);
}

add_action( 'plugins_loaded', 'airwallex_init' );
