<?php

namespace Elephant\Helpers\Exceptions;

use Exception;

class NoSpfException extends Exception
{
    public function __construct(string $domain)
    {
        parent::__construct("{$domain} has no SPF record.");
    }
}
