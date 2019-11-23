<?php

namespace Elephant\EventLoop\IPC;

use Illuminate\Contracts\Foundation\Application;
use React\Socket\ConnectionInterface;

class EventLoopData
{
    public function __construct(Application $app, ConnectionInterface $connection)
    {
        $this->$app = $app;
        $this->connection = $connection;
    }

    public function __invoke(string $data)
    {
        if (strtolower($data) === 'quit') {
            $this->app->terminate();
        }
    }
}
