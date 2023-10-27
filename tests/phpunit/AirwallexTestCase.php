<?php

namespace Airwallex\Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Brain\Monkey\Functions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\when;
use Mockery;


class AirwallexTestCase extends PhpUnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();

        when('sanitize_text_field')->returnArg();
        when('wp_kses_post')->returnArg();
        when('wp_unslash')->returnArg();
        when('wc_print_r')->alias(function ($value, bool $return = false) {
            return print_r($value, $return);
        });
        when('wc_string_to_bool')->alias(function ($string) {
            return is_bool($string) ? $string : ('yes' === strtolower($string) || 1 === $string || 'true' === strtolower($string) || '1' === $string);
        });
        when('get_plugin_data')->justReturn(['Version' => '1.0']);
        when('plugin_basename')->justReturn('airwallex-online-payments-gateway/airwallex-online-payments-gateway.php');
        when('wc_clean')->returnArg();
        when('get_transient')->returnArg();
        when('delete_transient')->returnArg();
        when('wcs_get_subscription')->returnArg();

        setUp();
    }

    public function tearDown(): void
    {
        tearDown();
        Mockery::close();
        parent::tearDown();
    }
}
