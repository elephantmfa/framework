<?php

namespace Elephant\Mail;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

class BodyPart implements Arrayable
{
    protected $raw;

    protected $filename;
    protected $disposition; // attachment | body
    protected $size;
    protected $contentTransferEncoding;
    protected $contentType;
    protected $body = '';

    public static function fromRaw(string $raw): BodyPart
    {
        $bp = new static;
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

        return $bp;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }
    
    public function toArray()
    {
        return [
            'name' => $this->name,
            'disposition' => $this->disposition,
            'size' => $this->size,
            'content_transfer_encoding' => $this->contentTransferEncoding,
            'content_type' => $this->contentType,
        ];
    }

    public function __toString()
    {
        return $this->raw;
    }

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
}
