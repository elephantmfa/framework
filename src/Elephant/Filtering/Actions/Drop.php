<?php

namespace Elephant\Filtering\Actions;

use Exception;

class Drop extends Exception
{
    /**
     * Create a new QuarantineException.
     *
     * @param string $message
     * @param int    $code
     *
     * @return void
     */
    public function __construct(string $message = 'Not ok', int $code = 550)
    {
        $this->message = $message;
        if ($code < 500 && $code >= 300) {
            $code = 550;
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
