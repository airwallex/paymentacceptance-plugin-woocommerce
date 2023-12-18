<?php

namespace Airwallex\Services;

class CacheService {

	const PREFIX = 'awx_';

	/**
	 * Prefix of the cache key
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Set the prefix according to the salt provided
	 *
	 * @param string $salt
	 */
	public function __construct( $salt = '' ) {
		$this->prefix = self::PREFIX . ( $salt ? md5( $salt ) : '' ) . '_';
	}

	/**
	 * Set/update the value of a cache key
	 *
	 * @param string $key
	 * @param $value
	 * @param int $maxAge
	 * @return bool
	 */
	public function set( $key, $value, $maxAge = 7200 ) {
		return set_transient( $this->prefix . $key, $value, $maxAge );
	}

	/**
	 * Get cache value according to cache key
	 *
	 * @param string $key
	 * @return mixed|null
	 */
	public function get( $key ) {
		$return = get_transient( $this->prefix . $key );
		return false === $return ? null : $return;
	}
}
