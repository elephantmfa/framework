<?php

namespace Elephant\Mail;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class Envelope implements Arrayable
{
    protected $helo;
    protected $sender;
    protected $recipients;

    public function __construct()
    {
        $this->recipients = [];
    }

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
        if (in_array($name, ['helo', 'sender', 'recipients'])) {
            return $this->$name;
        }

        throw new InvalidArgumentException("\$name must be in ['helo', 'sender', 'recipients'].");
    }

    /**
     * Set the protected params. Will append for recipients.
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
        if (in_array($name, ['helo', 'sender', 'recipients'])) {
            if ($name == 'recipients') {
                $this->recipients[] = $value;

                return;
            }
            $this->$name = $value;

            return;
        }

        throw new InvalidArgumentException("\$name must be in ['helo', 'sender', 'recipients'].");
    }

    public function toArray()
    {
        return [
            'helo'       => $this->helo,
            'sender'     => $this->sender,
            'recipients' => $this->recipients,
        ];
    }
}
