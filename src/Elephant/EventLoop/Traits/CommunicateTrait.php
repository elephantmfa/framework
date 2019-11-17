<?php

namespace Elephant\EventLoop\Traits;

trait CommunicateTrait
{
    /**
     * The connection interface to speak on.
     *
     * @var \React\Socket\ConnectionInterface
     */
    protected $connection;
    /**
     * Write something on the connection.
     *
     * @param string $message
     * @return self
     */
    protected function say(string $message): self
    {
        $this->connection->write("$message\r\n");
        return $this;
    }

    /**
     * Alias for `say`.
     *
     * @param string $message
     * @return self
     */
    protected function write(string $message): self
    {
        return $this->say($message);
    }

    /**
     * Ends a connection and sends a message.
     *
     * @param string $message
     * @return self
     */
    protected function close(string $message): self
    {
        $this->connection->end("$message\r\n");
        return $this;
    }
}
