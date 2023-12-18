<?php

namespace Airwallex\Gateways\Settings;

use Airwallex\Gateways\Settings\AirwallexSettingsTrait;
use WC_Settings_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractAirwallexSettings extends WC_Settings_API {
	use AirwallexSettingsTrait;

	public function __construct() {
		$this->plugin_id = AIRWALLEX_PLUGIN_NAME;
		$this->init_form_fields();
		$this->init_settings();
		$this->hooks();
	}

	public function hooks() {
		
	}
}
