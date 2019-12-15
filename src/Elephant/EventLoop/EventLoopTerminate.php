<?php

namespace Elephant\EventLoop;

use Illuminate\Contracts\Container\Container;
use React\Socket\ConnectionInterface;

class EventLoopTerminate
{
    protected $app;
    protected $connection;

    public function __construct(Container $app, ConnectionInterface $connection)
    {
        $this->app = $app;
        $this->connection = $connection;
    }

    public function __invoke()
    {
        //
    }
}
