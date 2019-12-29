<?php

namespace Elephant\EventLoop;

use Elephant\EventLoop\Traits\CommunicateTrait;
use Illuminate\Contracts\Container\Container;
use React\Socket\ConnectionInterface;

class EventLoopClose
{
    use CommunicateTrait;

    /** @var Container $app */
    protected $app;
    /** @var ConnectionInterface $connection */
    protected $connection;

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
