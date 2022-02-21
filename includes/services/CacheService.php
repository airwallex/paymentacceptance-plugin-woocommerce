<?php

namespace Airwallex\Services;

class CacheService
{
    private $dir = 'wp-content/cache/airwallex';
    private $isActive = false;
    /**
     * @var string
     */
    private $salt;

    public function __construct($salt = '')
    {
        $this->isActive = $this->prepareCacheDirectory();
        $this->salt = $salt;
    }

    public function set($key, $value)
    {
        if(!$this->isActive){
            return;
        }
        file_put_contents($this->getFilePath($key), serialize($value));
    }

    public function delete($key)
    {
        unlink($this->getFilePath($key));
    }

    public function get($key, $maxAge = 7200)
    {
        if (!$this->exists($key)) {
            return null;
        }
        return unserialize(file_get_contents($this->getFilePath($key)));
    }

    public function exists($key, $maxAge = 7200)
    {
        if(!$this->isActive){
            return false;
        }
        $path = $this->getFilePath($key);
        return (file_exists($path) || filemtime($path) > time() - $maxAge);
    }

    public function getFilePath($key)
    {
        $fileName = preg_replace('/[^0-9a-z]+/i', '_', $key) . '_' . md5($key . $this->salt) . '.cache';
        return ABSPATH . $this->dir . '/' . $fileName;
    }

    private function prepareCacheDirectory()
    {
        if (file_exists(ABSPATH . $this->dir)) {
            return true;
        }

        if (mkdir(ABSPATH . $this->dir, 0661, true)) {
            if (file_put_contents(ABSPATH . $this->dir . '/.htaccess', "Order deny,allow\nDeny from all")) {
                return true;
            }
        }

        return false;
    }

}
