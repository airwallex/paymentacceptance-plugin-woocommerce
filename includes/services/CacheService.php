<?php

namespace Airwallex\Services;

class CacheService
{
    const PREFIX = 'awx_';

    /**
     * @var string
     */
    private $prefix;

    /**
     * @param string $salt
     */
    public function __construct($salt = '')
    {
        $this->prefix = self::PREFIX . ($salt ? md5($salt) : '') . '_';
    }

    /**
     * @param string $key
     * @param $value
     * @param int $maxAge
     * @return bool
     */
    public function set($key, $value, $maxAge = 7200)
    {
        return set_transient($this->prefix . $key, $value, $maxAge);
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function get($key)
    {
        $return = get_transient($this->prefix . $key);
        return $return === false ? null : $return;
    }
}
