<?php

namespace Elephant\Filtering\Scanners;

use Elephant\Contracts\Mail\Mail;
use Elephant\Contracts\Mail\Scanner as ScannerContract;

abstract class Scanner implements ScannerContract
{
    /**
     * The mail message to be scanning.
     *
     * @var \Elephant\Contracts\Mail\Mail $mail
     */
    protected $mail;

    /**
     * The user email to use when scanning.
     *
     * @var string $user
     */
    protected $user;

    /**
     * The spam tests that were returned by SpamAssassin.
     *
     * @var array $results
     */
    protected $results;

    /**
     * Error reported during processing.
     *
     * @var string $error
     */
    public $error;

    /**
     * Construct a new SpamAssassin Scanner instance.
     *
     * @param Mail $mail The mail to be scanned.
     */
    public function __construct(Mail $mail)
    {
        $this->mail = $mail;
        $this->results = [];
        $this->error = '';
        $this->user = '';
    }

    /** {@inheritdoc} */
    public function setUser(string $email): ScannerContract
    {
        return $this;
    }

    /** {@inheritdoc} */
    abstract public function scan(): ?ScannerContract;

    /** {@inheritdoc} */
    public function getResults()
    {
        return $this->results;
    }
}
