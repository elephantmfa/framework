<?php

namespace Elephant\EventLoop;

use Elephant\EventLoop\Traits\CommunicateTrait;
use React\Socket\ConnectionInterface;
use Illuminate\Contracts\Container\Container;

class EventLoopClose
{
    use CommunicateTrait;
    
    protected $app;

    public function __construct(Container $app, ConnectionInterface $connection)
    {
        $this->app = $app;
        $this->connection = $connection;
    }

    public function __invoke()
    {
        $this->say('221 2.0.0 Goodbye');
    }
}
