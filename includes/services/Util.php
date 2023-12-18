<?php

namespace Airwallex\Services;

use Exception;

class Util {

	public static function getLocale() {
		$locale = strtolower( get_bloginfo( 'language' ) );
		$locale = str_replace( '_', '-', $locale );
		if ( substr_count( $locale, '-' ) > 1 ) {
			$parts  = explode( '-', $locale );
			$locale = $parts[0] . '-' . $parts[1];
		}
		if ( strpos( $locale, '-' ) !== false ) {
			$parts = explode( '-', $locale );
			if ( 'zh' === $parts[0] && in_array( $parts[1], array( 'tw', 'hk' ), true ) ) {
				$locale = 'zh-HK';
			} else {
				$locale = $parts[0];
			}
		}
		return $locale;
	}

	/**
	 * Truncate string to given length
	 *
	 * @param string $str    Original string.
	 * @param int    $len    [optional] Maximum number of characters of the returned string excluding the suffix.
	 * @param string $suffix [optional] Suffix to be attached to the end of the string.
	 * @return string
	 */
	public static function truncateString( $str, $len = 128, $suffix = '' ) {
		if ( mb_strlen( $str ) <= $len ) {
			return $str;
		}

		return mb_substr( $str, 0, $len ) . $suffix;
	}

	/**
	 * Rounds a value to a specified precision using a specified rounding mode.
	 *
	 * @param mixed $val The value to be rounded. Can be numeric or a string representation of a number.
	 * @param int $precision [optional] The number of decimal places to round to.
	 * @param int $mode [optional] The rounding mode to be used (defaults to PHP_ROUND_HALF_UP).
	 * @return float The rounded value.
	 */
	public static function round( $val, $precision = 0, $mode = PHP_ROUND_HALF_UP ) {
		if ( ! is_numeric( $val ) ) {
			$val = floatval( $val );
		}
		return round( $val, $precision, $mode );
	}

	/**
	 * Get the current environment setting
	 * 
	 * @return string The current environment
	 */
	public static function getEnvironment() {
		return in_array( get_option( 'airwallex_enable_sandbox' ), array( true, 'yes' ), true ) ? 'demo' : 'prod';
	}

	/**
	 * Get the api kay
	 * 
	 * @return string API Key
	 */
	public static function getApiKey() {
		return get_option( 'airwallex_api_key' );
	}

	/**
	 * Get the client id
	 * 
	 * @return string Client id
	 */
	public static function getClientSecret() {
		return get_option( 'airwallex_client_id' );
	}

	/**
	 * Get merchant info from JWT token
	 * 
	 * @param string $clientSecret
	 * @return array Merchant info
	 */
	public static function getMerchantInfoFromJwtToken($clientSecret) {
		try {
			// decode JWT token
			$merchantInfo = [];
			$base64Codes  = explode('.', $clientSecret);
			if (!empty($base64Codes[1])) {
				$base64 = str_replace('_', '/', str_replace('-', '+', $base64Codes[1]));
				
				$decoded = json_decode(urldecode(base64_decode($base64)), true);
			}

			if (isset($decoded['account_id'])) {
				$merchantInfo = [
					'accountId' => $decoded['account_id'],
				];
			}

			return $merchantInfo;
		} catch (Exception $ex) {
			LogService::getInstance()->error(__METHOD__, $ex->getTrace());
			return null;
		}
	}

	/**
	 * Get currency format
	 * 
	 * @return array Currency format
	 */
	public static function getCurrencyFormat() {
		$position = get_option( 'woocommerce_currency_pos' );
		$symbol   = html_entity_decode( get_woocommerce_currency_symbol() );
		$prefix   = '';
		$suffix   = '';

		switch ( $position ) {
			case 'left_space':
				$prefix = $symbol . ' ';
				break;
			case 'left':
				$prefix = $symbol;
				break;
			case 'right_space':
				$suffix = ' ' . $symbol;
				break;
			case 'right':
				$suffix = $symbol;
				break;
		}
		
		return [
			'currencyCode'              => get_woocommerce_currency(),
			'currencySymbol'            => $symbol,
			'currencyMinorUnit'         => wc_get_price_decimals(),
			'currencyDecimalSeparator'  => wc_get_price_decimal_separator(),
			'currencyThousandSeparator' => wc_get_price_thousand_separator(),
			'currencyPrefix'            => $prefix,
			'currencySuffix'            => $suffix,
		];
	}
}
