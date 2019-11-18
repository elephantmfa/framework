<?php

namespace Elephant\Mail;

use Elephant\Contracts\Mail\Mail as MailContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;

class Mail implements MailContract, Jsonable, Arrayable
{
    protected $raw = '';
    protected $envelope;
    protected $connection;
    protected $headers = [];
    protected $bodyParts = [];
    protected $queueId;
    protected $finalDestination;
    protected $boundary;

    public function __construct()
    {
        $this->connection = new Connection();
        $this->envelope = new Envelope();
    }

    /**
     * Add a header at the top of the mail message.
     *
     * @param string $header
     * @param string $value
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function appendHeader(string $header, string $value): MailContract
    {
        $this->addHeader($header, $value);
        $headerValue = "$header: $value";

        if (strlen($headerValue) > 78) {
            $headerValue = fold_header($headerValue);
        }
        $this->raw = "$headerValue\n{$this->raw}";

        return $this;
    }

    /**
     * Add a header at the bottom of the headers of the mail message.
     *
     * @param string $header
     * @param string $value
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function prependHeader(string $header, string $value): MailContract
    {
        array_unshift($this->headers[$header], $value);
        $headerValue = "$header: $value";

        if (strlen($headerValue) > 78) {
            $headerValue = fold_header($headerValue);
        }
        $nRaw = '';
        $added = false;
        foreach (explode("\n", $this->raw) as $line) {
            if (!$added && empty(trim($line))) {
                $added = true;
                $nRaw .= "$headerValue\n";
            }
            $nRaw .= "$line\n";
        }
        $this->raw = $nRaw;

        return $this;
    }

    /**
     * Change a header according to the  alteration callable.
     *
     * @param string $header
     * @param callable $alteration
     * @param int $which Which header to add in the event of multiple of the same header.
     *  If unset, alter the latest instance of the header.
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function alterHeader(string $header, callable $alteration, ?int $which = null): MailContract
    {
        $which = $which ?? $this->getNewestHeaderIndex($header);
        if (! isset($this->headers[$header][$which])) {
            $this->appendHeader($header, '--');
        }
        $value = $this->headers[$header][$which] = $alteration($this->headers[$header][$which]);

        $headerValue = "$header: $value";

        if (strlen($headerValue) > 78) {
            $headerValue = fold_header($headerValue);
        }

        $nRaw = '';
        $updated = false;
        $seenCount = 0;
        foreach (explode("\n", $this->raw) as $line) {
            [$h, $v] = explode(': ', $line, 2);
            if ($h === $header) {
                $seenCount++;
            }
            if (!$updated && $seenCount === ($which + 1)) {
                $updated = true;
                $nRaw .= "$headerValue\n";
                continue;
            }
            $nRaw .= "$line\n";
        }
        $this->raw = $nRaw;

        return $this;
    }

    /**
     * Delete a header.
     *
     * @param string $header
     * @param integer $which Which header to add in the event of multiple of the same header.
     *  If unset, alter the latest instance of the header.
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function deleteHeader(string $header, ?int $which = null): MailContract
    {
        $which = $which ?? $this->getNewestHeaderIndex($header);
        unset($this->headers[$header][$which]);

        $nRaw = '';
        $removed = false;
        $seenCount = 0;
        foreach (explode("\n", $this->raw) as $line) {
            [$h, $v] = explode(': ', $line, 2);
            if ($h === $header) {
                $seenCount++;
            }
            if (!$removed && $seenCount === ($which + 1)) {
                $removed = true;
                continue;
            }
            $nRaw .= "$line\n";
        }
        $this->raw = $nRaw;

        return $this;
    }

    /**
     * Attach a body section or attachment.
     *
     * @param \Elephant\Mail\BodyPart $body
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function attach(BodyPart $body): MailContract
    {
        $this->bodyParts[] = $body;

        if (! isset($this->boundary)) {
            $this->createBoundary();
        }

        $this->raw .= "\n\n--{$this->boundary}\n{$body->getRaw()}";

        return $this;
    }

    /**
     * Attach a body section or attachment.
     *
     * @param string $body
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function attachRaw(string $body): MailContract
    {
        $this->bodyParts[] = BodyPart::fromRaw($body);

        if (! isset($this->boundary)) {
            $this->createBoundary();
        }

        $this->raw .= "\n\n--{$this->boundary}\n{$body}";

        return $this;
    }

    /**
     * Add an email address to BCC. This will not appear in the headers.
     *
     * @param string $recipient
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function bcc(string $recipient): MailContract
    {
        $this->envelope->recipient = $recipient;

        return $this;
    }

    /**
     * Add an email address to CC. This will appear in the headers.
     *
     * @param string $recipient
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function cc(string $recipient): MailContract
    {
        $this->envelope->recipient = $recipient;
        if (isset($this->headers['cc'])) {
            $this->alterHeader('cc', function ($header) use ($recipient) {
                return $header . ", <{$recipient}>";
            });
        } else {
            $this->prependHeader('cc', "<{$recipient}>");
        }

        return $this;
    }

    /**
     * Set the final destination of the mail message.
     *   $destination can be either an IP address, IP:port, or [IPv6],
     *   or [IPv6]:port or a keyword:
     *     * allow
     *     * reject
     *     * defer
     *     * quarantine
     *     * drop
     *
     * @param string $destination
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setFinalDestination(string $destination): MailContract
    {
        if (! in_array($destination, ['allow', 'reject', 'defer', 'quarantine', 'drop']) &&
            ! validate_ip($destination) &&
            ! validate_ip($destination, true)
        ) {
            throw new InvalidArgumentException(
                "\$destination must be an IP, IP:port or ['allow', 'reject', 'defer', 'quarantine', 'drop']"
            );
        }
        $this->finalDestination = $destination;

        return $this;
    }


    /**
     * Add a header to the headers array.
     *
     * @param string $header
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function addHeader(string $header, string $value): MailContract
    {
        $this->headers[$header][] = $value;

        return $this;
    }

    /**
     * Set a HELO message.
     *
     * @param string $helo
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setHelo(string $helo): MailContract
    {
        $this->envelope->helo = $helo;

        return $this;
    }

    /**
     * Set the sender IP of the message.
     *
     * @param string $ip
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setSenderIp(string $ip): MailContract
    {
        $this->connection->senderIp = $ip;

        return $this;
    }

    /**
     * Set the sender name (PTR record of the IP) of the message.
     *
     * @param string $name
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setSenderName(string $name): MailContract
    {
        $this->connection->senderName = $name;

        return $this;
    }

    /**
     * Set the protocol for the message. Will be either SMTP or ESMTP.
     *
     * @param string $protocol
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setProtocol(string $protocol): MailContract
    {
        $this->connection->protocol = $protocol;

        return $this;
    }

    /**
     * Add a recipient to the recipient array.
     *
     * @param string $recipient
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function addRecipient(string $recipient): MailContract
    {
        $this->envelope->recipients = $recipient;

        return $this;
    }

    /**
     * Set the sender of the message.
     *
     * @param string $sender
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setSender(string $sender): MailContract
    {
        return $this;
    }
    /**
     * Set the queue ID of the message.
     *
     * @param string $queueId
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setQueueId(string $queueId): MailContract
    {
        $this->queueId = $queueId;

        return $this;
    }

    /**
     * Set the MIME boundary
     *
     * @param string $boundary
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setMimeBoundary(string $boundary): MailContract
    {
        $this->boundary = $boundary;

        return $this;
    }


    /**
     * Get a header from the headers array
     *
     * @param string|null $header
     * @return array
     */
    public function getHeader(?string $header = null): array
    {
        if (! isset($header)) {
            return $this->headers;
        }
        return $this->headers[$header];
    }

    /**
     * Get the index of the newest instance of a header.
     *
     * @param string $header
     * @return integer
     */
    public function getNewestHeaderIndex(string $header): int
    {
        $c = count($this->headers[$header]) - 1;
        if ($c < 0) {
            $c = 0;
        }

        return $c;
    }

    /**
     * Get the HELO of the message.
     *
     * @return string|null
     */
    public function getHelo(): ?string
    {
        return $this->envelope->helo;
    }

    /**
     * Get the sender IP of the message.
     *
     * @return string
     */
    public function getSenderIp(): string
    {
        return $this->connection->sender_ip;
    }

    /**
     * Get the sender name of the message.
     *
     * @return string
     */
    public function getSenderName(): string
    {
        return $this->connection->sender_name;
    }

    /**
     * Get the protocol of the message.
     *
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->connection->protocol;
    }

    /**
     * Get the recipients of the message.
     *
     * @return array|null
     */
    public function getRecipients(): ?array
    {
        return $this->envelope->recipients;
    }

    /**
     * Get the sender of the message.
     *
     * @return string|null
     */
    public function getSender(): ?string
    {
        return $this->envelope->sender;
    }

    /**
     * Get the queue ID of the message.
     *
     * @return string|null
     */
    public function getQueueId(): ?string
    {
        return $this->queueId;
    }

    /**
     * Get the MIME boundary of the message.
     *
     * @return string|null
     */
    public function getMimeBoundary(): ?string
    {
        return $this->boundary;
    }


    /**
     * Append to the end of the raw data.
     *
     * @param string $rawData
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function appendToRaw(string $rawData): MailContract
    {
        $this->raw .= $rawData;

        return $this;
    }

    /**
     * Get the raw data of the mail.
     *
     * @return string|null
     */
    public function getRaw(): ?string
    {
        return $this->raw;
    }

    /**
     * Set the connection object.
     *
     * @param \Elephant\Mail\Connection $connection
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setConnection(Connection $connection): MailContract
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get the connection object.
     *
     * @return \Elephant\Mail\Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Create an array representation of the mail.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'queue_id' => $this->queueId,
            'envelope' => $this->envelope->toArray(),
            'connection' => $this->connection->toArray(),
            'headers' => $this->headers,
            'body_parts' => $this->bodyParts,
        ];
    }

    /**
     * Create a json representation of the mail.
     *
     * @param integer $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get a string representation of the mail. This returns the raw version.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->raw;
    }


    /**
     * Create a new MIME boundary for the message.
     *
     * @return void
     */
    protected function createBoundary(): void
    {
        $boundary = '=' . Str::random(16) . '=';
        $this->setMimeBoundary($boundary);
        $this->alterHeader('content-type', function ($header) use ($boundary) {
            return "multipart/mixed; boundary=\"$boundary\"";
        });
    }
}
