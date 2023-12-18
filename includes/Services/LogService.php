<?php

namespace Airwallex\Services;

use Airwallex\Client\LoggingClient;

class LogService {

	const CARD_ELEMENT_TYPE            = 'cardElement';
	const DROP_IN_ELEMENT_TYPE         = 'dropInElement';
	const WECHAT_ELEMENT_TYPE          = 'wechatElement';
	const GOOGLE_EXPRESS_CHECKOUT_TYPE = 'googleExpressCheckout';
	const APPLE_EXPRESS_CHECKOUT_TYPE  = 'appleExpressCheckout';

	private $logDir;
	private $loggingClient;
	private static $instance;

	public static function getInstance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		if ( defined( 'WC_LOG_DIR' ) ) {
			$this->logDir = WC_LOG_DIR;
		} else {
			$this->logDir = ABSPATH . 'wc-content/uploads/';
		}
	}

	private function getLogFile( $level ) {
		return $this->logDir . 'airwallex-' . $level . '-' . gmdate( 'Y-m-d' ) . '_' . md5( get_option( 'airwallex_api_key' ) ) . '.log';
	}

	public function log( $message, $level = 'debug', $data = null ) {
		file_put_contents( $this->getLogFile( $level ), '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . ' | ' . wp_json_encode( $data ) . "\n", 8 ); // @codingStandardsIgnoreLine.
	}

	public function debug( $message, $data = null, $type = 'unknown' ) {
		$this->log( $message, 'debug', $data );
		$this->getLoggingClient()->log( LoggingClient::LOG_SEVERITY_INFO, 'wp_info', $message, $data, $type );
	}

	public function warning( $message, $data = null, $type = 'unknown' ) {
		$this->log( '⚠ ' . $message, 'debug', $data );
		$this->log( $message, 'warning', $data );
		$this->getLoggingClient()->log( LoggingClient::LOG_SEVERITY_WARNING, 'wp_warning', $message, $data, $type );
	}

	public function error( $message, $data = null, $type = 'unknown' ) {
		$this->log( '💣 ' . $message, 'debug', $data );
		$this->log( $message, 'error', $data );
		$this->getLoggingClient()->log( LoggingClient::LOG_SEVERITY_ERROR, 'wp_error', $message, $data, $type );
	}

	protected function getLoggingClient() {
		if ( ! isset( $this->loggingClient ) ) {
			$this->loggingClient = new LoggingClient( get_option( 'airwallex_client_id' ), get_option( 'airwallex_api_key' ), in_array( get_option( 'airwallex_enable_sandbox' ), array( true, 'yes' ), true ) );
		}
		return $this->loggingClient;
	}
}
