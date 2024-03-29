<?php

namespace Airwallex\Services;

defined( 'ABSPATH' ) || exit();

class ServiceFactory {
    private static $cacheService;
    private static $logService;
    private static $orderService;
    private static $webHookService;

    /**
     * @param string $salt
     * @return CacheService
     */
    public static function createCacheService($salt = '') {
        if (self::$cacheService) {
            return self::$cacheService;
        }
        self::$cacheService = new CacheService(Util::getClientSecret($salt));
        return self::$cacheService;
    }

    /**
     * @param CacheService $cacheService
     */
    public static function setCacheService($cacheService) {
        self::$cacheService = $cacheService;
    }

    /**
     * @return LogService
     */
    public static function createLogService() {
        if (self::$logService) {
            return self::$logService;
        }
        self::$logService = new LogService();
        return self::$logService;
    }

    /**
     * @param LogService $logService
     */
    public static function setLogService($logService) {
        self::$logService = $logService;
    }

    /**
     * @return OrderService
     */
    public static function createOrderService() {
        if (self::$orderService) {
            return self::$orderService;
        }
        self::$orderService = new OrderService();
        return self::$orderService;
    }

    /**
     * @param OrderService $orderService
     */
    public static function setOrderService($orderService) {
        self::$orderService = $orderService;
    }

    /**
     * @return WebHookService
     */
    public static function createWebHookService() {
        if (self::$webHookService) {
            return self::$webHookService;
        }
        self::$webHookService = new WebHookService();
        return self::$webHookService;
    }

    /**
     * @param WebHookService $webHookService
     */
    public static function setWebHookService($webHookService) {
        self::$webHookService = $webHookService;
    }
}