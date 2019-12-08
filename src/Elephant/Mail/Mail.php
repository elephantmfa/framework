<?php

namespace Elephant\Mail;

use Elephant\Contracts\Mail\Mail as MailContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;

class Mail implements MailContract, Jsonable, Arrayable
{
    public $supplementalData;
    public $timings = [];

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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function prependHeader(string $header, string $value): MailContract
    {
        if (! isset($this->headers[$header])) {
            $this->headers[$header] = [];
        }
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
     * {@inheritDoc}
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
            if (empty(trim($line))) {
                break;
            }
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function attach(BodyPart $body): MailContract
    {
        $this->bodyParts[] = $body;

        if (! isset($this->boundary)) {
            $this->createBoundary();
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function attachRaw(string $body): MailContract
    {
        $this->bodyParts[] = BodyPart::fromRaw($body);

        if (! isset($this->boundary)) {
            $this->createBoundary();
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function bcc(string $recipient): MailContract
    {
        $this->envelope->recipient = $recipient;

        return $this;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function addHeader(string $header, string $value): MailContract
    {
        $this->headers[strtolower($header)][] = $value;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setHelo(string $helo): MailContract
    {
        $this->envelope->helo = $helo;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setSenderIp(string $ip): MailContract
    {
        $this->connection->senderIp = $ip;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setSenderName(string $name): MailContract
    {
        $this->connection->senderName = $name;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setProtocol(string $protocol): MailContract
    {
        $this->connection->protocol = $protocol;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addRecipient(string $recipient): MailContract
    {
        $this->envelope->recipients = $recipient;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setSender(string $sender): MailContract
    {
        $this->envelope->sender = $sender;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setQueueId(string $queueId): MailContract
    {
        $this->queueId = $queueId;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setMimeBoundary(string $boundary): MailContract
    {
        $this->boundary = $boundary;

        return $this;
    }


    /**
     * {@inheritDoc}
     */
    public function getHeader(?string $header = null): array
    {
        if (! isset($header)) {
            return $this->headers;
        }
        return $this->headers[$header] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getNewestHeaderIndex(string $header): int
    {
        if (! isset($this->headers[$header])) {
            return 0;
        }
        $c = count($this->headers[$header]) - 1;

        return $c;
    }

    /**
     * {@inheritDoc}
     */
    public function getHelo(): ?string
    {
        return $this->envelope->helo;
    }

    /**
     * {@inheritDoc}
     */
    public function getSenderIp(): string
    {
        return $this->connection->senderIp;
    }

    /**
     * {@inheritDoc}
     */
    public function getSenderName(): string
    {
        return $this->connection->senderName;
    }

    /**
     * {@inheritDoc}
     */
    public function getProtocol(): string
    {
        return $this->connection->protocol;
    }

    /**
     * {@inheritDoc}
     */
    public function getRecipients(): ?array
    {
        return $this->envelope->recipients;
    }

    /**
     * {@inheritDoc}
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
        if (! isset($this->queueId)) {
            $queueId = sha1(
                strtoupper(Str::random()) .
                    Carbon::now()->toString() .
                    $this->getHelo() .
                    $this->getSenderIp() .
                    $this->getSender()
            );

            $this->setQueueId($queueId);
        }

        return $this->queueId;
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeBoundary(): ?string
    {
        return $this->boundary;
    }


    /**
     * {@inheritDoc}
     */
    public function appendToRaw(string $rawData): MailContract
    {
        $this->raw .= $rawData;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getRaw(): ?string
    {
        return $this->raw;
    }

    /**
     * {@inheritDoc}
     */
    public function setConnection(Connection $connection): MailContract
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /** {@inheritDoc} */
    public function removeAllRecipients(): MailContract
    {
        foreach ($this->getRecipients() as $recipient) {
            $this->removeRecipient($recipient);
        }

        return $this;
    }

    /** {@inheritDoc} */
    public function removeRecipient(string $recipient): MailContract
    {
        $this->envelope->removeRecipient($recipient);

        return $this;
    }

    /** {@inheritDoc} */
    public function toArray()
    {
        return [
            'queue_id' => $this->queueId,
            'envelope' => $this->envelope->toArray(),
            'connection' => $this->connection->toArray(),
            'headers' => $this->headers,
            'body_parts' => collect($this->bodyParts)->toArray(),
        ];
    }

    /**
     * {@inheritDoc}
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
