<?php

namespace Airwallex;

use Exception;

class LoggingClient extends AbstractClient {

	const LOG_SEVERITY_INFO    = 'info';
	const LOG_SEVERITY_WARNING = 'warn';
	const LOG_SEVERITY_ERROR   = 'error';

	private $isActive = false;

	public function __construct( $clientId, $apiKey, $isSandbox ) {
		$this->clientId  = $clientId;
		$this->apiKey    = $apiKey;
		$this->isSandbox = $isSandbox;
		$this->isActive  = self::isActive();
	}

	protected function getSessionId() {
		if ( ! session_id() ) {
			session_start();
		}
		return session_id();
	}

	public function log( $severity, $eventName, $message, $details = array(), $type = 'unknown' ) {
		if ( ! $this->isActive ) {
			return;
		}

		$data = array(
			'commonData' => array(
				'appName'    => 'pa_plugin',
				'source'     => 'woo_commerce',
				'deviceId'   => 'unknown',
				'sessionId'  => $this->getSessionId(),
				'appVersion' => AIRWALLEX_VERSION,
				'platform'   => $this->getClientPlatform(),
				'env'        => $this->isSandbox ? 'demo' : 'prod',
			),
			'data'       => array(
				array(
					'severity'  => $severity,
					'eventName' => $eventName,
					'message'   => $message,
					'type'      => $type,
					'details'   => wp_json_encode( $details ),
					'trace'     => wp_json_encode( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) ), // phpcs:ignore
				),
			),
		);
		try {
			$client = $this->getHttpClient();
			$client->call(
				'POST',
				$this->getLogUrl( 'papluginlogs/logs' ),
				wp_json_encode( $data ),
				array(
					'Authorization' => 'Bearer ' . $this->getToken(),
				),
				null,
				true
			);
		} catch ( Exception $e ) {
			//silent
			wc_get_logger()->error( 'An error occurred while attempting to send logs to Airwallex. ' . $e->getMessage() );
		}
	}
	protected function getClientPlatform() {
		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
		if ( strpos( $userAgent, 'Linux' ) !== false ) {
			return 'linux';
		} elseif ( strpos( $userAgent, 'Android' ) !== false ) {
			return 'android';
		} elseif ( strpos( $userAgent, 'Windows' ) !== false ) {
			return 'windows';
		} elseif ( strpos( $userAgent, 'iPhone' ) !== false || strpos( $userAgent, 'iPad' ) !== false ) {
			return 'ios';
		} elseif ( strpos( $userAgent, 'Macintosh' ) !== false || strpos( $userAgent, 'Mac OS X' ) !== false ) {
			return 'macos';
		} else {
			return 'other';
		}
	}

	public static function isActive() {
		return in_array( get_option( 'airwallex_do_remote_logging' ), array( 'yes', 1, true, '1' ), true );
	}
}
