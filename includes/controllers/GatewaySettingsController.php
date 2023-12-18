<?php

namespace Airwallex\Controllers;

if (!defined('ABSPATH')) {
	exit;
}

use Airwallex\Services\LogService;
use Airwallex\Client\CardClient;
use Exception;

class GatewaySettingsController {
	const CONFIGURATION_ERROR = 'configuration_error';

	protected $cardClient;

	public function __construct(CardClient $cardClient) {
		$this->cardClient = $cardClient;
	}

	// we don't have an open API to register the domain, this action just tries to add the domain association file to the root directory
	public function registerDomain() {
		check_ajax_referer('wc-airwallex-admin-settings-register-apple-pay-domain', 'security');

		$origin     = isset($_POST['origin']) ? wc_clean(wp_unslash($_POST['origin'])) : '';
		$serverName = ! empty( $origin ) ? $origin : ( isset($_SERVER['SERVER_NAME']) ? wc_clean(wp_unslash($_SERVER['SERVER_NAME'])) : '' );

		LogService::getInstance()->debug(__METHOD__ . " - Add domain registration file for {$serverName}.");
		try {
			$success = false;
			// try to add domain association file.
			if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
				$path = wc_clean(wp_unslash($_SERVER['DOCUMENT_ROOT'])) . DIRECTORY_SEPARATOR . '.well-known';
				$file = $path . DIRECTORY_SEPARATOR . 'apple-developer-merchantid-domain-association';
				require_once( ABSPATH . '/wp-admin/includes/file.php' );
				if ( function_exists( 'WP_Filesystem' ) && ( WP_Filesystem() ) ) {
					/**
					 * WP_Filesystem_Base
					 *
					 * @var WP_Filesystem_Base $wp_filesystem
					 */
					global $wp_filesystem;
					if ( ! $wp_filesystem->is_dir( $path ) ) {
						$wp_filesystem->mkdir( $path );
					}
					$contents = $wp_filesystem->get_contents( AIRWALLEX_PLUGIN_PATH . 'apple-developer-merchantid-domain-association' );
					$wp_filesystem->put_contents( $file, $contents, 0755 );
					if (sha1_file(AIRWALLEX_PLUGIN_PATH . 'apple-developer-merchantid-domain-association') !== sha1_file($file)) {
						throw new Exception(__('Failed to move the file.', 'airwallex-online-payments-gateway'));
					} else {
						$success = true;
					}
				}
			}

			$response = [
				'success' => $success,
			];
			if ($success) {
				$response['message'] = __('Successfully added the domain registration file.', 'airwallex-online-payments-gateway');
			} else {
				$response['error']['message'] = __('Failed to add the domain registration file.', 'airwallex-online-payments-gateway');
			}

			wp_send_json($response);
		} catch (Exception $e) {
			LogService::getInstance()->error(__METHOD__ . ' - Failed to add the domain registration file .', $e->getMessage());
			wp_send_json([
				'success' => false,
				'error' => [
					'message' => sprintf(
						/* translators: Placeholder 1: Error message */
						__('Failed to add the domain registration file. %s', 'airwallex-online-payments-gateway'),
						$e->getMessage()
					) 
				],
			]);
		}
	}
}
