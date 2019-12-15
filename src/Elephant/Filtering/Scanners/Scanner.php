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
    public function setUser(string $email): Scanner
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

    /**
     * Break a DSN (ex. ipv4://127.0.0.1:10024) into the parts.
     *
     * @param string $dsn
     * @return array [$path, $port, $proto, $type]
     */
    protected function breakDsn(string $dsn): array
    {
        [$type, $path] = explode('://', $dsn, 2);
        if (! isset($path) || empty($path) || ! isset($type) || empty($type)) {
            $this->error = "Invalid socket: {$type}{$path}.";

            return null;
        }

        $type = strtolower($type);
        if ($type === 'ipv4') {
            $type = AF_INET;
        } elseif ($type === 'ipv6') {
            $type = AF_INET6;
        } elseif ($type === 'unix') {
            $type = AF_UNIX;
        } else {
            $type = null;
        }

        $proto = SOL_TCP;
        if ($type == AF_INET6) {
            [$path, $port] = explode(']:', $path, 2);
            $path = trim($path, '[]');
        } elseif ($type == AF_INET) {
            [$path, $port] = explode(':', $path, 2);
        } else {
            $port = 0;
            $proto = 0;
        }

        return [$path, $port, $proto, $type];
    }
}
