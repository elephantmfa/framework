<?php

namespace Elephant\Filtering\Exception;

use Exception;

class QuarantineException extends Exception
{
    /**
     * Create a new QuarantineException
     *
     * @param string $message
     * @param int $code
     * @return void
     */
    public function __construct(string $message = 'Ok', int $code = 250)
    {
        $this->message = $message;
        if ($code >= 300 && $code < 400) {
            $code = 250;
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
