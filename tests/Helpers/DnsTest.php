<?php

namespace Tests\Helpers;

use Elephant\Helpers\Dns;
use Elephant\Helpers\Matchers\Wildcard;
use Tests\TestCase;

class DnsTest extends TestCase
{
    // 2019-12-24: php.net = 185.85.0.29
    const PHP_NET = '185.85.0.29';

    const SURRIEL_TEST = '127.0.0.2';

    const ST_CLARDY = '34.254.71.35';
    const PTR_ST_CLARDY = 'st.clardy.eu';

    /** @test */
    function it_gets_the_correct_value_for_one_dns_record()
    {
        $this->assertEquals([self::PHP_NET], Dns::lookup('php.net'));
    }

    /** @test */
    function it_gets_the_correct_value_for_one_PTR_record()
    {
        $this->assertEquals([self::PTR_ST_CLARDY], Dns::lookup(self::ST_CLARDY, Dns::PTR));
    }

    /** @test */
    function it_gets_the_correct_value_for_basic_dns_funcs()
    {
        $this->assertEquals(self::PHP_NET, Dns::a('php.net'));

        $result = Dns::mx('php.net');
        $this->assertTrue(Wildcard::match('php-smtp*php.net', $result['target']));
        $this->assertEquals(0, $result['priority']);

        $this->assertSame(self::PTR_ST_CLARDY, Dns::ptr(self::ST_CLARDY));

        $this->assertSame('v=spf1 a mx -all', Dns::txt(self::PTR_ST_CLARDY));

        $this->assertSame(self::PHP_NET, Dns::cname('www.php.net'));

        $this->assertSame([
            'p'     => 'quarantine',
            'rua'   => 'mailto:dmarc@clardy.eu',
            'adkim' => 's',
            'aspf'  => 's',
        ], Dns::dmarc('clardy.eu'));

        $this->assertSame([
            'p'     => 'quarantine',
            'rua'   => 'mailto:dmarc@clardy.eu',
            'adkim' => 's',
            'aspf'  => 's',
        ], Dns::dmarc('st.clardy.eu'));

        $this->assertSame('v=spf1 a mx -all', Dns::spf(self::PTR_ST_CLARDY));
    }

    /** @test */
    function it_gets_the_correct_value_for_multiple_A_records()
    {
        $t = microtime(true);
        $result = Dns::multiLookup([
            'php.net',
            '2.0.0.127.psbl.surriel.com',
            'thisshouldneveractuallyexist1234.co'
        ]);
        $taken = microtime(true) - $t;

        $this->assertEquals([
            'php.net'                             => [self::PHP_NET],
            '2.0.0.127.psbl.surriel.com'          => [self::SURRIEL_TEST],
            'thisshouldneveractuallyexist1234.co' => ['nxdomain'],
        ], $result);

        $this->assertLessThan(9, $taken, 'DNS Requests timed out.');
    }
}
