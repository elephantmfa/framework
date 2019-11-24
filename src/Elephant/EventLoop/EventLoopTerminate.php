<?php

namespace Elephant\EventLoop;

use React\Socket\ConnectionInterface;
use Illuminate\Contracts\Container\Container;

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
