<?php

namespace Elephant\EventLoop;

use Exception;
use Illuminate\Contracts\Container\Container;
use React\Socket\ConnectionInterface;

class EventLoopError
{
    /** @var Container $app */
    protected $app;
    /** @var ConnectionInterface $connection */
    protected $connection;

    public function __construct(Container $app, ConnectionInterface $connection)
    {
        $this->app = $app;
        $this->connection = $connection;
    }

    public function __invoke(Exception $error)
    {
        error($error->getMessage());
        if ($this->app->config['app.debug']?? false) {
            $this->connection->write("550 5.7.1 SCE: $error");
        } else {
            $this->connection->write('550 5.7.1 Server Configuration Error');
        }
    }
}
