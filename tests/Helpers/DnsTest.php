<?php

namespace Tests\Helpers;

use Elephant\Helpers\Dns;
use Tests\TestCase;

class DnsTest extends TestCase
{
    // 2019-12-24: php.net = 185.85.0.29
    const PHP_NET = '185.85.0.29';

    const SURRIEL_TEST = '127.0.0.2';

    /** @test */
    function it_gets_the_correct_value_for_one_A_record()
    {
        $result = Dns::lookup('php.net');

        $this->assertSame(self::PHP_NET, $result);
    }

    /** @test */
    function it_gets_the_correct_value_for_multiple_A_records()
    {
        $t = microtime(true);
        $result = Dns::multiLookup(['php.net', '2.0.0.127.psbl.surriel.com']);
        $taken = microtime(true) - $t;

        $this->assertSame([
            'php.net'                    => self::PHP_NET,
            '2.0.0.127.psbl.surriel.com' => self::SURRIEL_TEST,
        ], $result);

        $this->assertLessThan(9, $taken, 'DNS Requests timed out.');
    }
}
