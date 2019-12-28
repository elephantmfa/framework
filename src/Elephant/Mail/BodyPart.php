<?php

namespace Elephant\Mail;

use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property-read string $filename
 * @property-read string $disposition Should be either "attachment" or "body"
 * @property-read int    $size In Bytes
 * @property-read string $contentTransferEncoding
 * @property-read string $contentType
 * @method string __toString()
 */
class BodyPart implements Arrayable, Countable
{
    protected $raw;

    protected $filename;
    protected $disposition; // attachment | body
    protected $size;
    protected $contentTransferEncoding;
    protected $contentType;
    protected $body = '';

    public static function fromRaw(string $raw): self
    {
        $bp = new static();
        $bp->disposition = 'body';

        $lines = explode("\n", $raw);

        $readingBody = false;
        $currentLine = '';

        foreach ($lines as $line) {
            if ($readingBody) {
                $bp->body .= "$line\n";
            }

            if (Str::startsWith($line, '--')) {
                continue;
            }
            $bp->raw .= "$line\n";

            if (empty(trim($line))) {
                $readingBody = true;
                continue;
            }

            if (preg_match('/^\s+\S/', $line)) {
                $currentLine .= $line;
            } else {
                if (! empty(trim($currentLine))) {
                    static::parseData($bp, $currentLine);
                }
                $currentLine = $line;
            }
        }

        if (! isset($bp->size)) {
            $bp->size = strlen($bp->getBody());
        }

        return $bp;
    }

    /**
     * Get the raw format of the BodyPart.
     *
     * @return string
     */
    public function getRaw(): string
    {
        return $this->raw;
    }

    /**
     * Get the attachment's metadata as an array.
     *
     * @return void
     */
    public function toArray()
    {
        return [
            'name'                      => $this->filename,
            'disposition'               => $this->disposition,
            'size'                      => $this->size,
            'content_transfer_encoding' => $this->contentTransferEncoding,
            'content_type'              => $this->contentType,
        ];
    }

    /**
     * Get the body of the BodyPart.
     *
     * @return string
     */
    public function getBody(): string
    {
        if ($this->contentTransferEncoding == 'base64') {
            return base64_decode(preg_replace('/\r?\n?/', '', $this->body));
        }

        return rtrim($this->body, "\n");
    }

    /**
     * Get one of the protected parameters.
     *
     * @param mixed $val
     *
     * @return void
     */
    public function __get($val)
    {
        if (in_array($val, ['filename', 'disposition', 'size', 'contentTransferEncoding', 'contentType'])) {
            return $this->$val;
        }

        throw new InvalidArgumentException(
            "\$name must be in ['filename', 'disposition', 'size', 'contentTransferEncoding', 'contentType']."
        );
    }

    /**
     * Convert the BodyPart into a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getBody();
    }

    /**
     * Parse the body part data.
     *
     * @param BodyPart &$bp
     * @param string   $currentLine
     *
     * @return void
     */
    private static function parseData(&$bp, $currentLine)
    {
        if (Str::startsWith(strtolower($currentLine), 'content-transfer-encoding')) {
            [, $value] = explode(': ', $currentLine);

            $bp->contentTransferEncoding = $value;
        }
        if (Str::startsWith(strtolower($currentLine), 'content-type')) {
            [, $value] = explode(': ', $currentLine);

            $bp->contentType = $value;
        }
        if (Str::startsWith(strtolower($currentLine), 'content-disposition')) {
            [, $value] = explode(': ', $currentLine);

            $parts = explode('; ', $value);

            foreach ($parts as $part) {
                if ($part == 'attachment') {
                    $bp->disposition = $part;
                }

                if (Str::startsWith($part, 'filename=')) {
                    [, $value] = explode('=', $part, 2);
                    $value = trim($value, '\'"');
                    $bp->filename = $value;
                }

                if (Str::startsWith($part, 'size=')) {
                    [, $value] = explode('=', $part, 2);
                    $value = trim($value, '\'"');
                    $bp->size = (int) $value;
                }
            }
        }
    }

    /** {@inheritDoc} */
    public function count()
    {
        return $this->size;
    }
}
