<?php

namespace Elephant\Mail;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * @property       string        $helo
 * @property       string        $sender
 * @property       array<string> $recipients
 */
class Envelope implements Arrayable
{
    /** @var string $helo */
    protected $helo;
    /** @var string $sender */
    protected $sender;
    /** @var array<string> $recipients */
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
                $this->addRecipient($value);

                return;
            }
            $this->$name = $value;

            return;
        }

        throw new InvalidArgumentException("\$name must be in ['helo', 'sender', 'recipients'].");
    }

    public function addRecipient(string $recipient): void
    {
        $this->recipients[] = $recipient;
    }

    public function removeRecipient(string $recipient): void
    {
        /** @var string $value */
        foreach ($this->recipients as $key => $value) {
            if ($value === $recipient) {
                unset($this->recipients[$key]);
            }
        }
    }

    /** {@inheritDoc} */
    public function toArray()
    {
        return [
            'helo'       => $this->helo,
            'sender'     => $this->sender,
            'recipients' => $this->recipients,
        ];
    }
}
