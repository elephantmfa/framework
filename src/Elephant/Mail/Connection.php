<?php

namespace Elephant\Mail;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * @property int    $receivedPort
 * @property string $protocol SMTP or ESMTP
 * @property string $senderIp
 * @property string $senderName The reverse DNS record of senderIp
 */
class Connection implements Arrayable
{
    protected $receivedPort;
    protected $protocol;
    protected $senderIp;
    protected $senderName;

    /**
     * Get the protected params.
     *
     * @param string $get
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (in_array($name, ['receivedPort', 'protocol', 'senderIp', 'senderName'])) {
            return $this->$name;
        }

        throw new InvalidArgumentException("\$name must be in ['receivedPort', 'protocol', 'senderIp', 'senderName'].");
    }

    /**
     * Set the protected params.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if (in_array($name, ['receivedPort', 'protocol', 'senderIp', 'senderName'])) {
            $this->$name = $value;

            return;
        }

        throw new InvalidArgumentException("\$name must be in ['receivedPort', 'protocol', 'senderIp', 'senderName'].");
    }

    public function toArray()
    {
        return [
            'received_port' => $this->receivedPort,
            'protocol'      => $this->protocol,
            'sender_ip'     => $this->senderIp,
            'sender_name'   => $this->senderName,
        ];
    }
}
