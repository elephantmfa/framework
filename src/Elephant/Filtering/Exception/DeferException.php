<?php

namespace Elephant\Filtering\Exception;

use Exception;

class DeferException extends Exception
{
    /**
     * Create a new QuarantineException.
     *
     * @param string $message
     * @param int    $code
     *
     * @return void
     */
    public function __construct(string $message = 'Try again later', int $code = 450)
    {
        $this->message = $message;
        if ($code < 400 || $code >= 500) {
            $code = 450;
        }
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
