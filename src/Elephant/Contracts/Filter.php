<?php

namespace Elephant\Contracts;

use Elephant\Contracts\Mail\Mail;

interface Filter
{
    /**
     * Determine how the filter will affect the mail.
     *
     * @param Mail $email
     * @param Closure $next
     * @return void
     */
    public function filter(Mail $email, $next);
}
