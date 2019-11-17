<?php

namespace Elephant\Mail;

use Illuminate\Contracts\Support\Arrayable;

class Connection implements Arrayable
{
    public $received_port;
    public $protocol;
    public $sender_ip;
    public $sender_name;

    public function toArray()
    {
        return [
            'received_port' => $this->received_port,
            'protocol' => $this->protocol,
            'sender_ip' => $this->sender_ip,
            'sender_name' => $this->sender_name,
        ];
    }
}
