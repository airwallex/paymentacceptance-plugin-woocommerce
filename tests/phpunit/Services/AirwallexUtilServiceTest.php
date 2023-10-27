<?php

namespace Airwallex\Tests\Services;

use Airwallex\Services\Util;
use Airwallex\Tests\AirwallexTestCase;
use function Brain\Monkey\Functions\when;

class AirwallexUtilServiceTest extends AirwallexTestCase
{

    public function testGetLocale(): void
    {
        when('get_bloginfo')->justReturn('zh-TW');
        $this->assertEquals('zh-HK', Util::getLocale());
        when('get_bloginfo')->justReturn('zh_TW');
        $this->assertEquals('zh-HK', Util::getLocale());

        when('get_bloginfo')->justReturn('zh-HK');
        $this->assertEquals('zh-HK', Util::getLocale());
        when('get_bloginfo')->justReturn('zh_HK');
        $this->assertEquals('zh-HK', Util::getLocale());

        when('get_bloginfo')->justReturn('zh-CN');
        $this->assertEquals('zh', Util::getLocale());
        when('get_bloginfo')->justReturn('zh_CN');
        $this->assertEquals('zh', Util::getLocale());

        when('get_bloginfo')->justReturn(('de_DE_formal'));
        $this->assertEquals('de', Util::getLocale());
    }

    public function testTruncateString(): void
    {
        $this->assertEquals('', Util::truncateString(''));
        $this->assertEquals('Hello, World!', Util::truncateString('Hello, World!'));
        $this->assertEquals('Hello, World!', Util::truncateString('Hello, World!', 16));
        $this->assertEquals('Hello, World!', Util::truncateString('Hello, World!', 16, '...'));
        $this->assertEquals('Hello, Wor', Util::truncateString('Hello, World!', 10));
        $this->assertEquals('Hello, Wor...', Util::truncateString('Hello, World!', 10, '...'));
    }

    public function testRound(): void
    {
        $this->assertEquals(122, Util::round('122.34343test'));
        $this->assertEquals(0, Util::round('test122.34343'));
        $this->assertEquals(4, Util::round(4.25));
        $this->assertEquals(-4, Util::round(-3.75));
        $this->assertEquals(2.35, Util::round(2.3456, 2));
        $this->assertEquals(-1.23, Util::round(-1.2345, 2));
        $this->assertEquals(4.5, Util::round(4.45, 1, PHP_ROUND_HALF_UP));
        $this->assertEquals(4.4, Util::round(4.45, 1, PHP_ROUND_HALF_DOWN));
        $this->assertEquals(4.4, Util::round(4.45, 1, PHP_ROUND_HALF_EVEN));
        $this->assertEquals(4.5, Util::round(4.45, 1, PHP_ROUND_HALF_ODD));
    }
}
