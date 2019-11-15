<?php

namespace Elephant\Filtering\Exception;

use Exception;

class DeferException extends Exception
{
    /**
     * Create a new QuarantineException
     *
     * @param string $message
     * @param int $code
     * @return void
     */
    public function __construct(string $message = 'Server Configuration Problem', int $code = 450)
    {
        $this->message = $message;
        $this->code = $code;
    }

    /**
     * Convert the exception to a response string.
     *
     * @return string
     */
    public function __toString()
    {
        return "{$this->code} {$this->message}";
    }
}
