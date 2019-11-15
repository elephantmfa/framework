<?php

namespace Elephant\Mail;

use Elephant\Contracts\Mail\Mail as MailContract;

class Mail implements MailContract
{
    public $envelope;
    public $connection;
    public $headers;
    protected $bodyParts;

    public function __construct()
    {
        //
    }
}
