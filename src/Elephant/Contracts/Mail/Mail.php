<?php

namespace Elephant\Contracts\Mail;

use Elephant\Mail\BodyPart;

interface Mail
{
    /**
     * Add a header at the top of the mail message.
     *
     * @param string $header
     * @param string $value
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function appendHeader(string $header, string $value): Mail;
    
    /**
     * Add a header at the bottom of the headers of the mail message;
     *
     * @param string $header
     * @param string $value
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function prependHeader(string $header, string $value): Mail;

    /**
     * Change a header according to the  alteration callable.
     *
     * @param string $header
     * @param callable $alteration
     * @param int $which Which header to add in the event of multiple of the same header.
     *  If unset, alter the latest instance of the header.
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function alterHeader(string $header, callable $alteration, ?int $which = null): Mail;

    /**
     * Delete a header.
     *
     * @param string $header
     * @param integer $which Which header to add in the event of multiple of the same header.
     *  If unset, alter the latest instance of the header.
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function deleteHeader(string $header, ?int $which = null): Mail;

    /**
     * Attach a body section or attachment.
     *
     * @param \Elephant\Mail\BodyPart $body
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function attach(BodyPart $body): Mail;

    /**
     * Attach a body section or attachment.
     *
     * @param string $body
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function attachRaw(string $body): Mail;
    
    /**
     * Add an email address to BCC. This will not appear in the headers.
     *
     * @param string $recipient
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function bcc(string $recipient): Mail;
    
    /**
     * Add an email address to CC. This will appear in the headers.
     *
     * @param string $recipient
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function cc(string $recipient): Mail;

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
    public function setFinalDestination(string $destination):self;


    /**
     * Add a header to the headers array.
     *
     * @param string $header
     * @param string $value
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function addHeader(string $header, string $value): Mail;

    /**
     * Set a HELO message.
     *
     * @param string $helo
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setHelo(string $helo): Mail;

    /**
     * Set the sender IP of the message.
     *
     * @param string $ip
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setSenderIp(string $ip): Mail;

    /**
     * Set the sender name (PTR record of the IP) of the message.
     *
     * @param string $name
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setSenderName(string $name): Mail;
    
    /**
     * Set the protocol for the message. Will be either SMTP or ESMTP.
     *
     * @param string $protocol
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setProtocol(string $protocol): Mail;

    /**
     * Add a recipient to the recipient array.
     *
     * @param string $recipient
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function addRecipient(string $recipient): Mail;

    /**
     * Set the sender of the message.
     *
     * @param string $sender
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setSender(string $sender): Mail;
    /**
     * Set the queue ID of the message.
     *
     * @param string $queueId
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setQueueId(string $queueId): Mail;

    /**
     * Set the MIME boundary
     *
     * @param string $boundary
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function setMimeBoundary(string $boundary): Mail;

    /**
     * Get a header from the headers array
     *
     * @param string|null $header
     * @return array
     */
    public function getHeader(?string $header = null): array;

    /**
     * Get the HELO of the message.
     *
     * @return string|null
     */
    public function getHelo(): ?string;

    /**
     * Get the sender IP of the message.
     *
     * @return string
     */
    public function getSenderIp(): string;

    /**
     * Get the sender name of the message.
     *
     * @return string
     */
    public function getSenderName(): string;

    /**
     * Get the protocol of the message.
     *
     * @return string
     */
    public function getProtocol(): string;

    /**
     * Get the recipients of the message.
     *
     * @return array|null
     */
    public function getRecipients(): ?array;

    /**
     * Get the index of the newest instance of a header.
     *
     * @param string $header
     * @return integer
     */
    public function getNewestHeaderIndex(string $header): int;

    /**
     * Get the sender of the message.
     *
     * @return string|null
     */
    public function getSender(): ?string;
    
    /**
     * Get the queue ID of the message.
     *
     * @return string|null
     */
    public function getQueueId(): ?string;

    /**
     * Get the MIME boundary of the message.
     *
     * @return string|null
     */
    public function getMimeBoundary(): ?string;


    /**
     * Append to the end of the raw data.
     *
     * @param string $rawData
     * @return \Elephant\Contracts\Mail\Mail
     */
    public function appendToRaw(string $rawData): Mail;

    /**
     * Get the raw data of the mail.
     *
     * @return string|null
     */
    public function getRaw(): ?string;
}
