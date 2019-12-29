<?php

namespace Elephant\Helpers\Exceptions;

use Exception;

class NoDmarcException extends Exception
{
    public function __construct(string $domain)
    {
        parent::__construct("{$domain} has no DMARC record.");
    }
}
