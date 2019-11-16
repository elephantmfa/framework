<?php

namespace Elephant\EventLoop;

use Elephant\Contracts\Mail\Mail;
use React\Socket\ConnectionInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

class EventLoopData
{
    protected $app;
    protected $connection;
    protected $filters;
    protected $email;

    public function __construct(Container $app, ConnectionInterface $connection, Mail &$mail, array $filters)
    {
        $this->app = $app;
        $this->connection = $connection;
        $this->filters = $filters;
        $this->mail = $mail;
    }

    public function __invoke($data)
    {
        $lcData = strtolower($data);
        if (Str::startsWith($lcData, ['helo', 'ehlo'])) {
            // $this->handleHelo($data);
        } elseif (Str::startsWith($lcData, ['mail from'])) {
            // $this->handleMailFrom($data);
        } elseif (Str::startsWith($lcData, ['rcpt to'])) {
            // $this->handleRcptTo($data);
        } elseif (Str::startsWith($lcData, ['xforward'])) {
            // $this->handleXforward($data);
        } elseif (Str::startsWith($lcData, ['data'])) {
            // $this->handleData($data);
        } elseif (Str::startsWith($lcData, ['quit'])) {
            $this->handleQuit($data);
        }
    }

    protected function handleQuit($data)
    {
        $this->connection->close('221 2.0.0 Bye');
    }

    protected function handleHelo($helo)
    {
        if (Str::startsWith(strtolower($helo), 'HELO')) {
            $this->connection->write('250 ' . config('app.name') . "\r\n");
        } else {
            $this->connection->write('250-' . config('app.name') . "\r\n");
            $this->connection->write("250-ENHANCEDSTATUSCODES\r\n");
            $this->connection->write("250 XFORWARD\r\n");
        }
    }
}
