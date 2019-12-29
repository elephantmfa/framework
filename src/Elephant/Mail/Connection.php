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
    /** @var int $receivedPort */
    protected $receivedPort = 0;
    /** @var string $protocol */
    protected $protocol = 'SMTP';
    /** @var string $senderIp */
    protected $senderIp = '';
    /** @var string $senderName */
    protected $senderName = '';

    /**
     * Get the protected params.
     *
     * @param string $get
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
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
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __set(string $name, $value): void
    {
        if (in_array($name, ['receivedPort', 'protocol', 'senderIp', 'senderName'])) {
            $this->$name = $value;

            return;
        }

        throw new InvalidArgumentException("\$name must be in ['receivedPort', 'protocol', 'senderIp', 'senderName'].");
    }

    /** {@inheritDoc} */
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
