<?php

namespace Elephant\Mail\Exceptions;

use Exception;

class TransportException extends Exception
{
    public function __construct(int $code, string $stage, string $response)
    {
        parent::__construct("Error in transport during {$stage}: {$response}", $code);
    }
}
