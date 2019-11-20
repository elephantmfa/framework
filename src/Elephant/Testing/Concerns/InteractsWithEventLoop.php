<?php

namespace Elephant\Testing\Concerns;

use Clue\React\Block;
use Illuminate\Support\Str;

trait InteractsWithEventLoop
{
    protected $server;
    protected $client;
    protected $lastResponse;

    /**
     * Sets up the event loop, so that it may be used in testing.
     *
     * @return void
     */
    protected function setUpEventLoop()
    {
        $this->server = $this->app->make('server', [
            'port' => 0,
            'filters' => [],
        ]);
        $this->connect();
    }

    protected function connect()
    {
        $this->client = stream_socket_client($this->server->getAddress());
        $this->tick()->lastResponse = trim(fread($this->client, 4096), "\r\n");
        return $this;
    }

    /**
     * Tears down the event loop, so as to preventany stray services running
     * or memory leaks
     *
     * @return void
     */
    protected function tearDownEventLoop()
    {
        stream_socket_shutdown($this->client, STREAM_SHUT_WR);
        $this->server->close();
        $this->app->loop->stop();
    }

    protected function speak($message, $read = true)
    {
        fwrite($this->client, "$message\r\n");
        if ($read) {
            $this->tick();
            $parts = explode("\r\n", trim(fread($this->client, 4096), "\r\n"));
            dump($parts);
            $this->lastResponse = $parts[count($parts) - 1];
        }
        
        return $this;
    }

    public function assertResponse($response)
    {
        $this->assertEquals($this->lastResponse, $response);
        return $this;
    }

    public function assert2xxResponse()
    {
        $this->assertTrue(preg_match('/^2\d\d/', $this->lastResponse) != false);
        return $this;
    }

    public function assert3xxResponse()
    {
        $this->assertTrue(preg_match('/^3\d\d/', $this->lastResponse) != false);
        return $this;
    }

    public function assert4xxResponse()
    {
        $this->assertTrue(preg_match('/^4\d\d/', $this->lastResponse) != false);
        return $this;
    }

    public function assert5xxResponse()
    {
        $this->assertTrue(preg_match('/^5\d\d/', $this->lastResponse) != false);
        return $this;
    }

    protected function tick()
    {
        Block\sleep(1, $this->app->loop);
        return $this;
    }
}
