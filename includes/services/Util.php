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
}