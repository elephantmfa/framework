<?php

namespace Tests\Scanners;

use Elephant\Filtering\Scanners\SpamAssassin;
use Elephant\Helpers\Socket;
use Elephant\Mail\Mail;
use Exception;
use Mockery as m;
use Tests\TestCase;

class SpamAssassinTest extends TestCase
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
        $sa = app(SpamAssassin::class);
        $this->assertNull($sa->scan(self::$mail));
    }

    /** @test */
    function it_returns_spam_tests()
    {
        $this->instance(Socket::class, m::mock(Socket::class, function ($mock) {
            /** @var \Mockery\MockInterface $mock */
            $mock->shouldReceive('setDsn')->once()->andReturn($mock);
            $mock->shouldReceive('setOption')->once();
            $mock->shouldReceive('send')->atLeast()->times(3);
            $mock->shouldReceive('read')
                ->atLeast()->once()
                ->andReturn('X-Spam-Status: tests=TEST_1=1,EICAR=1000 autolearn=no autolearn_force=no version=3.1.4');
            $mock->shouldReceive('close')->once();
        }));

        $sa = app(SpamAssassin::class);

        $this->assertSame($sa, $sa->scan(self::$mail));
        $this->assertSame([
            'total_score' => (float) 1001,
            'tests' => [
                [
                    'name' => 'TEST_1',
                    'score' => (float) 1,
                ],
                [
                    'name' => 'EICAR',
                    'score' => (float) 1000,
                ]
            ],
            'autolearn' => false,
            'autolearn_force' => false,
            'version' => '3.1.4',
        ], $sa->getResults());
    }
}
