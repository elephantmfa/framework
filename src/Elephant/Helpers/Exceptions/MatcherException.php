<?php

namespace Elephant\Helpers\Exceptions;

use Exception;

class MatcherException extends Exception
{
    /**
     * @param string $pattern
     * @param iterable<string|iterable>|string $against
     */
    public function __construct(string $pattern, $against)
    {
        parent::__construct(sprintf(
            'Pattern [%s] doesn\'t match against %s.',
            $pattern,
            is_iterable($against)
                ? json_encode($against, JSON_PARTIAL_OUTPUT_ON_ERROR)
                : $against
        ), 500);
    }
}
