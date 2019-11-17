<?php

namespace Elephant\Mail;

use Elephant\Contracts\Mail\Mail as MailContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class Mail implements MailContract, Jsonable, Arrayable
{
    public $envelope;
    public $connection;
    public $headers = [];
    public $bodyParts = [];
    public $raw = '';
    public $queue_id;

    public function __construct()
    {
        $this->connection = new Connection();
        $this->envelope = new Envelope();
    }

    public function toArray()
    {
        return [
            'queue_id' => $this->queue_id,
            'envelope' => $this->envelope->toArray(),
            'connection' => $this->connection->toArray(),
            'headers' => $this->headers,
            'body_parts' => $this->bodyParts,
        ];
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString()
    {
        return $this->toJson();
    }
}
