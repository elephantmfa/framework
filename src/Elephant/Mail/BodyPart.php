<?php

namespace Elephant\Mail;

use Illuminate\Contracts\Support\Arrayable;

class BodyPart implements Arrayable
{
    protected $raw;

    protected $name;
    protected $disposition; // attachment | body
    protected $size;
    protected $contentTransferEncoding;
    protected $contentType;

    public static function fromRaw(string $raw): BodyPart
    {
        $bp = new static;
        $bp->raw = $raw;

        $lines = explode("\n", $raw);

        foreach ($lines as $line) {
            //
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
}
