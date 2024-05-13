<?php

namespace Airwallex\Gateways\Settings;

use Airwallex\Controllers\AirwallexController;
use Airwallex\Gateways\Settings\AbstractAirwallexSettings;
use Airwallex\Main;
use Airwallex\Services\Util;
use WC_AJAX;

if (!defined('ABSPATH')) {
	exit;
}

class APISettings extends AbstractAirwallexSettings {
	const ID = 'airwallex_general';

	public function __construct() {
		$this->id          = self::ID;
		$this->tabTitle    = __('API Settings', 'airwallex-online-payments-gateway');
		$this->customTitle = __('Airwallex - API Settings', 'airwallex-online-payments-gateway');

		parent::__construct();
	}

	public function hooks() {
		parent::hooks();
		add_action('woocommerce_update_options_checkout_' . $this->id, array($this, 'process_admin_options'));
		add_filter('wc_airwallex_settings_nav_tabs', array($this, 'adminNavTab'), 10);
		add_action('woocommerce_airwallex_settings_checkout_' . $this->id, array($this, 'admin_options'));
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
		add_action('wc_ajax_airwallex_connection_test', [new AirwallexController(), 'connectionTest']);
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'client_id'                            => array(
				'title' => __( 'Unique client ID', 'airwallex-online-payments-gateway' ),
				'type'  => 'text',
				'description'  => '',
				'id'    => 'airwallex_client_id',
				'value' => get_option( 'airwallex_client_id' ),
				'class' => 'wc-airwallex-client-id',
			),
			'api_key'                              => array(
				'title' => __( 'API key', 'airwallex-online-payments-gateway' ),
				'type'  => 'text',
				'description'  => '',
				'id'    => 'airwallex_api_key',
				'value' => get_option( 'airwallex_api_key' ),
				'class' => 'wc-airwallex-api-key',
			),
			'webhook_secret'                       => array(
				'title' => __( 'Webhook secret key', 'airwallex-online-payments-gateway' ),
				'type'  => 'password',
				'description'  => 'Webhook URL: ' . WC()->api_request_url( Main::ROUTE_SLUG_WEBHOOK ),
				'id'    => 'airwallex_webhook_secret',
				'value' => get_option( 'airwallex_webhook_secret' ),
			),
			'enable_sandbox'                       => array(
				'title'   => __( 'Enable sandbox', 'airwallex-online-payments-gateway' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'id'      => 'airwallex_enable_sandbox',
				'value'   => get_option( 'airwallex_enable_sandbox' ),
				'class' => 'wc-airwallex-sandbox',
			),
			'connection_test' => array(
				'type' => 'airwallex_button',
				'title' => __('Connection test', 'airwallex-online-payments-gateway'),
				'label' => __('Connection test', 'airwallex-online-payments-gateway'),
				'class' => 'wc-airwallex-connection-test button-secondary',
				'description' => __('Click this button to perform a connection test. If successful, your site is connected to Airwallex', 'airwallex-online-payments-gateway'),
			), 
			'temporary_order_status_after_decline' => array(
				'title'   => __( 'Temporary order status after decline during checkout', 'airwallex-online-payments-gateway' ),
				'id'      => 'airwallex_temporary_order_status_after_decline',
				'type'    => 'select',
				'description'    => __( 'This order status is set, when the payment has been declined and the customer redirected to the checkout page to try again.', 'airwallex-online-payments-gateway' ),
				'options' => array(
					'pending' => _x( 'Pending payment', 'Order status', 'airwallex-online-payments-gateway' ),
					'failed'  => _x( 'Failed', 'Order status', 'airwallex-online-payments-gateway' ),
				),
				'value'   => get_option( 'airwallex_temporary_order_status_after_decline' ),
			),
			'order_status_pending'                 => array(
				'title'   => __( 'Order state for pending payments', 'airwallex-online-payments-gateway' ),
				'id'      => 'airwallex_order_status_pending',
				'type'    => 'select',
				'description'    => __( 'Certain local payment methods have asynchronous payment confirmations that can take up to a few days. Card payments are always instant.', 'airwallex-online-payments-gateway' ),
				'options' => array_merge( array( '' => __( '[Do not change status]', 'airwallex-online-payments-gateway' ) ), wc_get_order_statuses() ),
				'value'   => get_option( 'airwallex_order_status_pending' ),
			),
			'order_status_authorized'              => array(
				'title'   => __( 'Order state for authorized payments', 'airwallex-online-payments-gateway' ),
				'id'      => 'airwallex_order_status_authorized',
				'type'    => 'select',
				'description'    => __( 'Status for orders that are authorized but not captured', 'airwallex-online-payments-gateway' ),
				'options' => array_merge( array( '' => __( '[Do not change status]', 'airwallex-online-payments-gateway' ) ), wc_get_order_statuses() ),
				'value'   => get_option( 'airwallex_order_status_authorized' ),
			),
			'cronjob_interval'                     => array(
				'title'   => __( 'Cronjob interval', 'airwallex-online-payments-gateway' ),
				'id'      => 'airwallex_cronjob_interval',
				'type'    => 'select',
				'description'    => '',
				'options' => array(
					'3600'  => __( 'Every hour (recommended)', 'airwallex-online-payments-gateway' ),
					'14400' => __( 'Every 4 hours', 'airwallex-online-payments-gateway' ),
					'28800' => __( 'Every 8 hours', 'airwallex-online-payments-gateway' ),
					'43200' => __( 'Every 12 hours', 'airwallex-online-payments-gateway' ),
				),
				'value'   => get_option( 'airwallex_cronjob_interval' ),
			),
			'do_js_logging'                        => array(
				'title'   => __( 'Activate JS logging', 'airwallex-online-payments-gateway' ),
				'description'    => __( 'Yes (only for special cases after contacting Airwallex)', 'airwallex-online-payments-gateway' ),
				'type'    => 'checkbox',
				'default' => '',
				'id'      => 'airwallex_do_js_logging',
				'value'   => get_option( 'airwallex_do_js_logging' ),
			),
			'do_remote_logging'                    => array(
				'title'   => __( 'Activate remote logging', 'airwallex-online-payments-gateway' ),
				'description'    => __( 'Send diagnostic data to Airwallex', 'airwallex-online-payments-gateway' ) . '<br/><small>' . __( 'Help Airwallex easily resolve your issues and improve your experience by automatically sending diagnostic data. Diagnostic data may include order details.', 'airwallex-online-payments-gateway' ) . '</small>',
				'type'    => 'checkbox',
				'default' => '',
				'id'      => 'airwallex_do_remote_logging',
				'value'   => get_option( 'airwallex_do_remote_logging' ),
			),
			'payment_page_template'                => array(
				'title'   => __( 'Payment form template', 'airwallex-online-payments-gateway' ),
				'id'      => 'airwallex_payment_page_template',
				'type'    => 'select',
				'description'    => '',
				'options' => array(
					'default'        => __( 'Default', 'airwallex-online-payments-gateway' ),
					'wordpress_page' => __( 'WordPress page shortcodes', 'airwallex-online-payments-gateway' ),
				),
				'value'   => get_option( 'airwallex_payment_page_template' ),
				'default' => Util::isNewClient() ? 'wordpress_page' : 'default',
			),
		);
	}

	public function init_settings() {
		parent::init_settings();

		// make it compatible with the old approach
		foreach ($this->settings as $key => $value) {
			$this->settings[$key] = get_option('airwallex_' . $key, $value);
		}
	}

	public function process_admin_options() {
		parent::process_admin_options();

		// make it compatible with the old approach
		foreach ($this->settings as $key => $value) {
			update_option('airwallex_' . $key, $value, 'yes');
		}
	}

	public function enqueueAdminScripts() {
		$this->enqueueAdminSettingsScripts();
		wp_add_inline_script(
			'airwallex-admin-settings',
			'var awxAdminSettings = ' . wp_json_encode($this->getExpressCheckoutSettingsScriptData()),
			'before'
		);
		wp_add_inline_script(
			'airwallex-admin-settings',
			'var awxAdminECSettings = "";',
			'before'
		);
	}

	public function getExpressCheckoutSettingsScriptData() {
		return [
			'apiSettings' => [
				'connected' => $this->isConnected(),
				'nonce' => [
					'connectionTest' => wp_create_nonce('wc-airwallex-admin-settings-connection-test'),
				],
				'ajaxUrl' => [
					'connectionTest' => WC_AJAX::get_endpoint('airwallex_connection_test'),
				],
			],
		];
	}
}
