<?php
/**
 * Plugin Name: Airwallex Online Payments Gateway
 * Plugin URI:
 * Description: Official Airwallex Plugin
 * Author: Airwallex
 * Author URI: https://www.airwallex.com
 * Version: 1.1.5
 * Requires at least: 4.5
 * Tested up to:
 * WC requires at least: 3.0
 * WC tested up to:
 * Text Domain: airwallex-online-payments-gateway
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Required minimums and constants
 */
define('AIRWALLEX_VERSION', '1.1.5');
define('AIRWALLEX_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('AIRWALLEX_PLUGIN_PATH', __DIR__ . '/');
define('AIRWALLEX_PLUGIN_NAME', 'airwallex-online-payments-gateway');

function airwallex_init()
{

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>' . __('Airwallex requires WooCommerce to be installed and active.', AIRWALLEX_PLUGIN_NAME) . '</strong></p></div>';
        });
        return;
    }
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/Main.php';
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/struct/AbstractBase.php';
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/client/AbstractClient.php';
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/gateways/AirwallexGatewayTrait.php';
    foreach (glob(AIRWALLEX_PLUGIN_PATH . 'includes/*/*.php') as $includeFile) {
        require_once $includeFile;
    }
    $airwallex = \Airwallex\Main::getInstance();
    $airwallex->init();
    add_action('wp_enqueue_scripts', [$airwallex, 'addJs']);
}

add_action('plugins_loaded', 'airwallex_init');

