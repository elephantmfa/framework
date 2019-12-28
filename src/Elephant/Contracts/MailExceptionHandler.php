<?php

namespace Elephant\Contracts;

use Exception;
use React\Stream\WritableStreamInterface;

interface MailExceptionHandler
{
    /**
     * Render an exception to the console.
     *
     * @param \React\Stream\WritableStreamInterface $connection
     * @param \Exception                            $e
     * @return void
     */
    public function renderForMail(WritableStreamInterface $connection, Exception $e): void;
}
