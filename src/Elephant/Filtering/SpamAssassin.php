<?php

namespace Elephant\Filtering;

use Elephant\Contracts\Mail\Mail;
use Elephant\Contracts\Mail\Scanner;

class SpamAssassin implements Scanner
{
    /**
     * The mail message to be scanning.
     *
     * @var Elephant\Contracts\Mail\Mail
     */
    protected $mail;

    /**
     * The user email to use when scanning.
     *
     * @var string
     */
    protected $user;

    /**
     * The spam tests that were returned by SpamAssassin.
     *
     * @var array
     */
    protected $results;

    /**
     * Length of the content to send to SpamAssassin in bytes.
     *
     * @var int
     */
    protected $contentLength;

    /**
     * Error reported during processing.
     *
     * @var string
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
        $this->contentLength = strlen(str_replace("\n", "\r\n", $mail->getRaw()));
        if ($this->contentLength > config('scanners.spamassasisn.max_size')) {
            $this->contentLength = config('scanners.spamassasisn.max_size');
        }
        $this->headers = [];
    }

    /** {@inheritDoc} */
    public function setUser(string $email): Scanner
    {
        return $this;
    }

    /** {@inheritDoc} */
    public function scan(): ?Scanner
    {
        $timeBegin = microtime(true);
        [$type, $path] = explode('://', config('scanners.spamassassin.socket'), 2);
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
        if (is_null($type)) {
            $this->error = "Invalid socket type: $type";

            return null;
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

        $socket = socket_create($type, SOCK_STREAM, $proto);
        if (! $socket) {
            $this->error = "Unable to create socket!";

            return null;
        }
        socket_set_option(
            $socket,
            SOL_SOCKET,
            SO_SNDTIMEO,
            ['sec' => config('scanners.spamassassin.connect_timeout')]
        );
        if (! @socket_connect($socket, $path, $port)) {
            $this->error = "Unable to connect to socket!";

            return null;
        }

        if (! $this->socketWrite($socket, "REPORT SPAMC/1.2")) {
            $this->error = "Unable to write command to socket!";

            return null;
        }
        if (! $this->socketWrite($socket, "Content-length: {$this->contentLength}")) {
            $this->error = "Unable to write content-length to socket!";

            return null;
        }
        if (isset($this->user) && ! empty($this->user)) {
            if (! $this->socketWrite($socket, "User: {$this->user}")) {
                $this->error = "Unable to write user to socket!";

                return null;
            }
        }
        if (! $this->socketWrite($socket, "")) {
            $this->error = "Unable to write to socket!";

            return null;
        }
        ; // Move on from headers to message.
        $lines = explode("\n", $this->mail->getRaw());
        $bytes = 0;
        foreach ($lines as $line) {
            $bytes += strlen("$line\r\n");
            if (!$this->socketWrite($socket, $line)) {
                $this->error = "Unable to write to socket!";

                return null;
            }
            if ($bytes >= $this->contentLength) {
                break;
            }
        }

        $currentLine = '';
        while ($line = @socket_read($socket, 512, PHP_NORMAL_READ)) {
            if (preg_match('/^\s+[a-z]+/i', $line)) {
                $currentLine .= " " . trim($line);
                continue;
            }
            $line = trim($line);
            if (preg_match('/^[a-z\-]+: /i', $line)) {
                $regex = '/^X-Spam-Status: ' .
                    '(?:(?:Yes|No), score=[0-9\.\-]+ required=[0-9\.\-]+ )?' .
                    'tests=(.*) autolearn=(yes|no)(?: autolearn_force=(yes|no))?' .
                    ' version=([0-9\.]+)$/';
                if (preg_match($regex, $currentLine, $matches)) {
                    [, $tests, $autolearn, $autolearn_force, $version] = $matches;
                    $tests = array_map(function ($test) {
                        $test = trim($test);
                        $score = 'undef';
                        if (strpos($test, '=') !== false) {
                            [$test, $score] = explode('=', $test);
                        }
                        return ['name' => $test, 'score' => $score];
                    }, explode(',', $tests));
                    $this->results = [
                        'tests' => $tests,
                        'autolearn' => strtolower($autolearn) === 'yes',
                        'autolearn_force' => strtolower($autolearn_force) === 'yes',
                        'version' => $version,
                    ];

                    continue;
                }
                $currentLine = $line;
            }
        }

        socket_close($socket);

        $this->mail->timings['spamassassin'] = microtime(true) - $timeBegin;

        return $this;
    }

    /** {@inheritDoc} */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Write to a socket cleanly.
     *
     * @param resource|bool $socket
     * @param string $in
     * @return boolean
     */
    private function socketWrite($socket, string $in): bool
    {
        if (! $socket) {
            return false;
        }
        $le = "\r\n";

        /* Send request */
        $sent = socket_write($socket, "{$in}{$le}");
        if ($sent != strlen("{$in}{$le}")) {
            return false;
        }

        return true;
    }
}
