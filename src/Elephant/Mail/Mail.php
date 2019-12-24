<?php

namespace Elephant\Mail;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Elephant\Contracts\Mail\Mail as MailContract;

class Mail implements MailContract, Jsonable, Arrayable
{
    public $supplementalData;
    public $timings = [];

    protected $raw = '';
    protected $envelope;
    protected $connection;
    protected $headers = [];
    protected $bodyParts = [];
    protected $queueId = '';
    protected $finalDestination = '';
    protected $boundary = '';

    /**
     * The current line being read in.
     *
     * @var string $currentLine
     */
    private $currentLine = '';

    /**
     * Whether or not we are reading a body.
     *
     * @var bool $readingBody
     */
    private $readingBody = false;

    /**
     * Whether or not the mail messages is likely ending.
     *
     * @var bool $endingMail
     */
    private $endingMail = false;

    public function __construct()
    {
        $this->connection = new Connection();
        $this->envelope = new Envelope();
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function prependHeader(string $header, string $value): MailContract
    {
        if (!isset($this->headers[$header])) {
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
     * {@inheritdoc}
     */
    public function alterHeader(string $header, callable $alteration, ?int $which = null): MailContract
    {
        $which = $which ?? $this->getNewestHeaderIndex($header);
        if (!isset($this->headers[$header][$which])) {
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function attach(BodyPart $body): MailContract
    {
        $this->bodyParts[] = $body;

        if (empty($this->boundary)) {
            $this->createBoundary();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function attachRaw(string $body): MailContract
    {
        $this->bodyParts[] = BodyPart::fromRaw($body);

        if (empty($this->boundary)) {
            $this->createBoundary();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function bcc(string $recipient): MailContract
    {
        $this->envelope->recipient = $recipient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function cc(string $recipient): MailContract
    {
        $this->envelope->recipient = $recipient;
        if (isset($this->headers['cc'])) {
            $this->alterHeader('cc', function ($header) use ($recipient) {
                return $header.", <{$recipient}>";
            });
        } else {
            $this->prependHeader('cc', "<{$recipient}>");
        }

        return $this;
    }

    /**
     * {@inheritdoc}
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

    /** {@inheritDoc} */
    public function getFinalDestination(): string
    {
        return $this->finalDestination;
    }

    /**
     * {@inheritdoc}
     */
    public function addHeader(string $header, string $value): MailContract
    {
        $this->headers[strtolower($header)][] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHelo(string $helo): MailContract
    {
        $this->envelope->helo = $helo;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setSenderIp(string $ip): MailContract
    {
        $this->connection->senderIp = $ip;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setSenderName(string $name): MailContract
    {
        $this->connection->senderName = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setProtocol(string $protocol): MailContract
    {
        $this->connection->protocol = $protocol;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addRecipient(string $recipient): MailContract
    {
        $this->envelope->recipients = $recipient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setSender(string $sender): MailContract
    {
        $this->envelope->sender = $sender;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setQueueId(string $queueId): MailContract
    {
        $this->queueId = $queueId;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMimeBoundary(string $boundary): MailContract
    {
        $this->boundary = $boundary;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(?string $header = null): array
    {
        if (!isset($header)) {
            return $this->headers;
        }

        return $this->headers[$header] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getNewestHeaderIndex(string $header): int
    {
        if (!isset($this->headers[$header])) {
            return 0;
        }
        $c = count($this->headers[$header]) - 1;

        return $c;
    }

    /**
     * {@inheritdoc}
     */
    public function getHelo(): ?string
    {
        return $this->envelope->helo;
    }

    /**
     * {@inheritdoc}
     */
    public function getSenderIp(): string
    {
        return $this->connection->senderIp;
    }

    /**
     * {@inheritdoc}
     */
    public function getSenderName(): string
    {
        return $this->connection->senderName;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocol(): string
    {
        return $this->connection->protocol;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecipients(): ?array
    {
        return $this->envelope->recipients;
    }

    /**
     * {@inheritdoc}
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
        if (empty($this->queueId)) {
            $queueId = sha1(
                strtoupper(Str::random()).
                    Carbon::now()->toString().
                    $this->getHelo().
                    $this->getSenderIp().
                    $this->getSender()
            );

            $this->setQueueId($queueId);
        }

        return $this->queueId;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeBoundary(): ?string
    {
        return $this->boundary;
    }

    /**
     * {@inheritdoc}
     */
    public function appendToRaw(string $rawData): MailContract
    {
        $this->raw .= $rawData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): ?string
    {
        return $this->raw;
    }

    /**
     * {@inheritDoc}
     */
    public function getBodyParts(): array
    {
        return $this->bodyParts;
    }

    /**
     * {@inheritdoc}
     */
    public function setConnection(Connection $connection): MailContract
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * {@inheritdoc}
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
    public function processLine(string $data): bool
    {
        $data = ltrim($data, '.');
        $this->appendToRaw($data);
        if (! $this->readingBody) {
            if (Str::startsWith($data, '--' . $this->getMimeBoundary()) ||
                empty(trim($data))
            ) {
                if (! empty($this->currentLine)) {
                    $this->processHeader();
                }
                $this->readingBody = true;
                $this->currentLine = '';

                return false;
            }

            if (preg_match('/^\s+\S/', $data)) {
                if (! config('relay.unfold_headers')) {
                    $this->currentLine .= "\n".trim($data, "\r\n");
                } else {
                    $this->currentLine .= ' '.trim($data);
                }
            } else {
                if (! empty($this->currentLine)) {
                    $this->processHeader();
                }
                $this->currentLine = trim($data);
            }

            return false;
        }

        if (Str::startsWith($data, '--'.$this->getMimeBoundary()) && !Str::endsWith($data, '--')) {
            if (! empty(trim($this->currentLine))) {
                $this->attachRaw($this->currentLine);
            }
            $this->currentLine = '';
        }

        if (empty($this->getMimeBoundary()) && substr_count($this->currentLine, '.') > 0) {
            $this->endingMail = true;
        }
        if (trim($data) == "--{$this->getMimeBoundary()}--") {
            if (! empty(trim($this->currentLine))) {
                $this->attachRaw($this->currentLine);
            }
            $this->endingMail = true;
        }
        if ($this->endingMail && empty(trim($data))) {
            if (substr_count($this->currentLine, "\n.\n") > 0) {
                return true;
            }
            if (! empty(trim($this->currentLine))) {
                $this->attachRaw($this->currentLine);
            }
            $this->currentLine = '';

            return false;
        }

        $this->currentLine .= $data;

        return false;
    }

    /** {@inheritDoc} */
    public function toArray()
    {
        return [
            'queue_id'   => $this->queueId,
            'envelope'   => $this->envelope->toArray(),
            'connection' => $this->connection->toArray(),
            'headers'    => $this->headers,
            'body_parts' => collect($this->bodyParts)->toArray(),
        ];
    }

    /**
     * {@inheritdoc}
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
        $boundary = '='.Str::random(16).'=';
        $this->setMimeBoundary($boundary);
        $this->alterHeader('content-type', function ($header) use ($boundary) {
            return "multipart/mixed; boundary=\"$boundary\"";
        });
    }

    /**
     * Add a header to the mail object. This is to DRY up some functionality.
     *
     * @return void
     */
    private function processHeader(): void
    {
        if (preg_match('/^(.+): (.+)$/s', $this->currentLine, $matches)) {
            [, $header, $value] = $matches;
            if (strtolower($header) == 'content-type') {
                if (preg_match('/boundary=["\'](.*)["\']/', $value, $matches)) {
                    $this->setMimeBoundary($matches[1]);
                }
            }

            $this->addHeader(strtolower($header), $value);
        }
    }
}
