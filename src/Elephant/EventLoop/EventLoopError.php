<?php

namespace Elephant\EventLoop;

use Exception;
use Elephant\Contracts\Mail;
use React\Socket\ConnectionInterface;
use Illuminate\Contracts\Container\Container;

class EventLoopError
{
    protected $app;
    protected $connection;
    protected $mail;

    public function __construct(Container $app, ConnectionInterface $connection, Mail $mail)
    {
        $this->app = $app;
        $this->connection = $connection;
        $this->mail = $mail;
    }

    public function __invoke(Exception $error)
    {
        if ($this->app->config('app.debug')) {
            $this->connection->write("550 5.7.1 SCE: $error");
        } else {
            $this->connection->write('550 5.7.1 Server Configuration Error');
        }
    }
}
