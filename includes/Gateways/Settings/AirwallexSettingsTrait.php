<?php

namespace Airwallex\Gateways\Settings;

use Airwallex\Client\MainClient;
use Airwallex\Services\LogService;
use Airwallex\Services\Util;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AirwallexSettingsTrait {
	protected $tabTitle;
	protected $customTitle;
	protected $customDescription = '';
	public static $connected = null;

	private $adminOutput = false;

	public function adminNavTab( $tabs ) {
		$tabs[ $this->id ] = $this->tabTitle;

		return $tabs;
	}

	public function isActive( $key ) {
		return wc_string_to_bool( $this->get_option( $key ) );
	}

	public function admin_options() {
		if ( $this->adminOutput ) {
			return;
		}
		$this->displayErrors();
		$this->outputSettingsNav();
		$this->outputOptions();
		$this->adminOutput = true;
	}

	public function outputSettingsNav() {
		include AIRWALLEX_PLUGIN_PATH . 'includes/Gateways/Settings/views/settings-nav.php';
	}

	public function outputOptions() {
		echo '<div class="wc-airwallex-settings-container ' . $this->id . '">';
		$this->displayCustomHeader();
		parent::admin_options();
		echo '</div>';
	}

	public function displayCustomHeader() {
		if ($this->customTitle) {
			echo '<h2>' . esc_html( $this->customTitle );
			wc_back_link( __( 'Return to payments', 'airwallex-online-payments-gateway' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
			echo '</h2>';
			echo wp_kses_post( wpautop( $this->customDescription ) );
		}
	}

	/**
	 * Display admin error messages.
	 */
	public function displayErrors() {
		$errors = $this->get_errors();
		if ( $errors ) {
			echo '<div id="woocommerce_errors" class="error notice inline is-dismissible">';
			foreach ( $errors as $error ) {
				echo '<p>' . wp_kses_post( $error ) . '</p>';
			}
			echo '</div>';
		}
	}

	public function getPrefix() {
		return $this->plugin_id . $this->id . '_';
	}

	public function generate_airwallex_button_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$data      = wp_parse_args(
			$data,
			array(
				'title'       => '',
				'class'       => '',
				'style'       => '',
				'desc' => '',
				'desc_tip'    => false,
				'id'          => 'wc-airwallex-button_' . $key,
				'disabled'    => false,
				'css'         => '',
			)
		);
		ob_start();

		include 'views/button.php';

		return ob_get_clean();
	}

	public function isConnected() {
		if (null === self::$connected) {
			if ( empty( Util::getApiKey() ) || empty( Util::getClientSecret() ) ) {
				self::$connected = false;
			} else {
				try {
					self::$connected = MainClient::getInstance()->testAuth();
				} catch (Exception $e) {
					LogService::getInstance()->error('Authentication failed.');
					self::$connected = false;
				}
			}
		}
		return self::$connected;
	}

	public function enqueueAdminSettingsScripts() {
		wp_enqueue_style(
			'airwallex-admin-css',
			AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout-admin.css',
			[],
			AIRWALLEX_VERSION
		);
		wp_enqueue_script(
			'airwallex-admin-settings',
			AIRWALLEX_PLUGIN_URL . '/assets/js/admin/airwallex-admin-settings.js',
			['jquery'],
			AIRWALLEX_VERSION,
			true
		);
	}
}
