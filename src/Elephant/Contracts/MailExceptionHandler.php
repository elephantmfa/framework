<?php

namespace Elephant\Contracts;

use React\Socket\ConnectionInterface;
use Throwable;

interface MailExceptionHandler
{
    /**
     * Render an exception to the console.
     *
     * @param \React\Socket\ConnectionInterface $connection
     * @param \Throwable                        $e
     * @return void
     */
    public function renderForMail(ConnectionInterface $connection, Throwable $e): void;
}
