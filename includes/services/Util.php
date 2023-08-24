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
     * @param int    $len    [optional] Maximum number of characters of the returned string excluding the suffix.
     * @param string $suffix [optional] Suffix to be attached to the end of the string.
     * @return string
     */
    public static function truncateString($str, $len = 128, $suffix = '') {
        if (mb_strlen($str) <= $len) return $str;

        return mb_substr($str, 0, $len) . $suffix;
    }

    /**
     * Rounds a value to a specified precision using a specified rounding mode.
     *
     * @param mixed $val The value to be rounded. Can be numeric or a string representation of a number.
     * @param int $precision [optional] The number of decimal places to round to.
     * @param int $mode [optional] The rounding mode to be used (defaults to PHP_ROUND_HALF_UP).
     * @return float The rounded value.
     */
    public static function round($val, $precision = 0, $mode = PHP_ROUND_HALF_UP) {
        if (! is_numeric($val)) {
            $val = floatval($val);
        }
        return round($val, $precision, $mode);
    }
}