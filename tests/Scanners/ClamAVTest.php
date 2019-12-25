<?php

namespace Tests\Scanners;

use Elephant\Filtering\Scanners\ClamAV;
use Elephant\Helpers\Socket;
use Elephant\Mail\Mail;
use Exception;
use Mockery as m;
use Tests\TestCase;

class ClamAVTest extends TestCase
{
    /** @var \Elephant\Mail\Mail|null $mail */
    private static $mail = null;

    public function setUp(): void
    {
        parent::setUp();

        static::$mail = new Mail();
        $lines = @file(__DIR__ . '/../test.eml');
        if ($lines === false) {
            throw new Exception('Failed to read test.eml.');
        }

        foreach ($lines as $line) {
            static::$mail->processLine($line);
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

    /** @test */
    function it_returns_null_on_failure_to_connect_to_socket()
    {
        $clamav = app(ClamAV::class);
        $this->assertNull($clamav->scan(self::$mail));
    }

    /** @test */
    function it_returns_virus_data()
    {
        $this->instance(Socket::class, m::mock(Socket::class, function ($mock) {
            /** @var \Mockery\MockInterface $mock */
            $mock->shouldReceive('setDsn')->atLeast()->once()->andReturn($mock);
            $mock->shouldReceive('send')->atLeast()->once()->andReturn(1);
            $mock->shouldReceive('read')
                ->atLeast()->times(6)
                ->andReturn(
                    'body-part1: OK', '',
                    'body-part2.html: OK', '',
                    'eicar.txt: eicarTestVirus FOUND', ''
                );
            $mock->shouldReceive('close')->times(3);
        }));

        $clamav = app(ClamAV::class);

        $this->assertSame($clamav, $clamav->scan(self::$mail));
        $this->assertSame([
            'infected' => true,
            'error' => false,
            'viruses' => [
                'eicar.txt' => 'eicarTestVirus',
            ],
            'errors' => [],
        ], $clamav->getResults());
    }
}
