<?php

namespace Airwallex\Services;

class Util
{
    public static function getLocale()
    {
        $locale = strtolower(get_bloginfo('language'));
        $locale = str_replace('_', '-', $locale);
        if (substr_count($locale, '-') > 1) {
            $parts = explode('-', $locale);
            $locale = $parts[0] . '-' . $parts[1];
        }
        if (strpos($locale, '-') !== false) {
            $parts = explode('-', $locale);
            if ($parts[0] === 'zh' && in_array($parts[1], ['tw', 'hk'])) {
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
     * @param int    $len    [optional] Maximum number of characters of the returned string exclude the length of the surfix(if applicable).
     * @param string $surfix [optional] Surfix to be attached to the end of the string.
     * @return string
     */
    public static function truncateString($str, $len = 128, $surfix = '') {
        if (mb_strlen($str) <= $len) return $str;

        return mb_substr($str, 0, $len) . $surfix;
    }
}