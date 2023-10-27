<?php

namespace Airwallex\Tests;

use Airwallex\Main;
use Airwallex\Controllers\AirwallexController;
use function Brain\Monkey\Functions\when;

class AirwallexMainTest extends AirwallexTestCase
{
    private $main;

    public function setUp(): void
	{
        parent::setUp();

        $this->main = Main::getInstance();
    }

    public function testGetInstance(): void {
        $instance1 = Main::getInstance();
        $this->assertInstanceOf(Main::class, $instance1);

        $instance2 = Main::getInstance();
        $this->assertSame($instance1, $instance2);
    }

    public function testGetInstanceKey() : void {
        $this->assertEquals(md5(AUTH_KEY), Main::getInstanceKey());
    }

    public function testInit(): void 
    {
        when('is_admin')->justReturn(false);
        when('get_option')->justReturn(3600);

        $main = Main::getInstance();
        $main->init();

        $this->assertNotFalse(has_filter('woocommerce_get_settings_checkout', [$main, 'addGlobalSettings']));
        $this->assertNotFalse(has_filter('woocommerce_payment_gateways', [$main, 'addPaymentGateways']));
        $this->assertNotFalse(has_action('woocommerce_order_status_changed', [$main, 'handleStatusChange']));
        $this->assertNotFalse(has_action('woocommerce_api_airwallex_card', [new AirwallexController, 'cardPayment']));
        $this->assertNotFalse(has_action('woocommerce_api_airwallex_main', [new AirwallexController, 'dropInPayment']));
        $this->assertNotFalse(has_action('woocommerce_api_airwallex_wechat', [new AirwallexController, 'weChatPayment']));
        $this->assertNotFalse(has_action('woocommerce_api_airwallex_payment_confirmation', [new AirwallexController, 'paymentConfirmation']));
        $this->assertNotFalse(has_action('woocommerce_api_airwallex_webhook', [new AirwallexController, 'webhook']));
        $this->assertFalse(has_action('woocommerce_api_airwallex_js_log', [new AirwallexController, 'jsLog']));
        $this->assertNotFalse(has_action('woocommerce_api_airwallex_async_intent', [new AirwallexController, 'asyncIntent']));
        $this->assertNotFalse(has_filter('plugin_action_links_airwallex-online-payments-gateway/airwallex-online-payments-gateway.php', [$main, 'addPluginSettingsLink']));
        $this->assertNotFalse(has_action('airwallex_check_pending_transactions', [$main, 'checkPendingTransactions']));
        $this->assertNotFalse(has_action('woocommerce_settings_saved', [$main, 'updateMerchantCountryAfterSave']));
        $this->assertNotFalse(has_action('requests-requests.before_request', [$main, 'modifyRequestsForLogging']));
        $this->assertNotFalse(has_action('wp_loaded', [$main, 'createPages']));
        $this->assertNotFalse(has_action('wp_loaded', 'function ()'));
        $this->assertNotFalse(has_filter('display_post_states', [$main, 'addDisplayPostStates']));
        $this->assertNotFalse(has_filter('wp_get_nav_menu_items', [$main, 'excludePagesFromMenu']));
        $this->assertNotFalse(has_filter('wp_list_pages_excludes', [$main, 'excludePagesFromList']));
        $this->assertNotFalse(has_filter('init', 'function ()'));
        $this->assertNotFalse(has_filter('wc_order_statuses', 'function ($statusList)'));
        $this->assertNotFalse(has_action('init', 'function ()'));
    }

    public function testInitInAdmin(): void
    {
        when('is_admin')->justReturn(true);
        when('get_option')->justReturn(3600);
        
        $main = Main::getInstance();
        $main->init();

        $this->assertFalse(has_filter('wp_get_nav_menu_items', [$main, 'excludePagesFromMenu']));
        $this->assertFalse(has_filter('wp_list_pages_excludes', [$main, 'excludePagesFromList']));
    }
}
