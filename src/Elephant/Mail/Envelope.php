<?php

namespace Elephant\Mail;

use Illuminate\Contracts\Support\Arrayable;

class Envelope implements Arrayable
{
    public $helo;
    public $sender;
    public $recipients;

    public function __construct()
    {
        $this->recipients = [];
    }

    public function toArray()
    {
        return [
            'helo' => $this->helo,
            'sender' => $this->sender,
            'recipients' => $this->recipients,
        ];
    }
}
