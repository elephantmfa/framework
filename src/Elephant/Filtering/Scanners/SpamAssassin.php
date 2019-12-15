<?php

namespace Elephant\Filtering\Scanners;

use Elephant\Contracts\Mail\Mail;
use Elephant\Filtering\Scanners\Scanner;
use Elephant\Contracts\Mail\Scanner as ScannerContract;

class SpamAssassin extends Scanner
{
    /**
     * Length of the content to send to SpamAssassin in bytes.
     *
     * @var int $contentLength
     */
    protected $contentLength;

    /**
     * Construct a new SpamAssassin Scanner instance.
     *
     * @param Mail $mail The mail to be scanned.
     */
    public function __construct(Mail $mail)
    {
        parent::__construct($mail);
        $this->contentLength = strlen(str_replace("\n", "\r\n", $mail->getRaw()));
        $maxSize = config('scanners.spamassassin.max_size', 0);
        if ($maxSize > 0 && $this->contentLength > $maxSize) {
            $this->contentLength = $maxSize;
        }
        $this->results = [
            'total_score' => 0,
            'tests' => [],
            'autolearn' => false,
            'autolearn_force' => false,
            'version' => 'v0.0.0',
        ];
    }

    /** {@inheritdoc} */
    public function scan(): ?ScannerContract
    {
        $timeBegin = microtime(true);

        [$path, $port, $proto, $type] = $this->breakDsn(config('scanners.spamassassin.socket'));

        if (is_null($type)) {
            $this->error = "Invalid socket type: $type";

            return null;
        }

        $socket = socket_create($type, SOCK_STREAM, $proto);
        if (! $socket) {
            $this->error = 'Unable to create socket!';

            return null;
        }
        socket_set_option(
            $socket,
            SOL_SOCKET,
            SO_SNDTIMEO,
            ['sec' => config('scanners.spamassassin.timeout', 10), 'usec' => 0]
        );
        if (! @socket_connect($socket, $path, $port)) {
            $this->error = 'Unable to connect to socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($socket));

            return null;
        }

        if (! $this->socketWrite($socket, 'HEADERS SPAMC/1.2')) {
            $this->error = 'Unable to write command to socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($socket));

            return null;
        }
        if (! $this->socketWrite($socket, "Content-length: {$this->contentLength}")) {
            $this->error = 'Unable to write content-length to socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($socket));

            return null;
        }
        if (isset($this->user) && ! empty($this->user)) {
            if (!$this->socketWrite($socket, "User: {$this->user}")) {
                $this->error = 'Unable to write user to socket!';
                $this->error .= ' ' . socket_strerror(socket_last_error($socket));

                return null;
            }
        }
        if (! $this->socketWrite($socket, '')) {
            $this->error = 'Unable to write to socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($socket));

            return null;
        }
        // Move on from headers to message.
        $lines = explode("\n", $this->mail->getRaw());
        $bytes = 0;
        foreach ($lines as $line) {
            $bytes += strlen("$line\r\n");
            if (! $this->socketWrite($socket, $line)) {
                $this->error = 'Unable to write to socket!';
                $this->error .= ' ' . socket_strerror(socket_last_error($socket));

                return null;
            }
            if ($bytes >= $this->contentLength) {
                break;
            }
        }

        $currentLine = '';
        while ($line = @socket_read($socket, 512, PHP_NORMAL_READ)) {
            if (preg_match('/^\s+[a-z]+/i', $line)) {
                $currentLine .= ' ' . trim($line);

                continue;
            }
            $line = trim($line);
            if (preg_match('/^Spam:\s+(?:False|True)\s+;\s+(\d+)\s+\/\s+\d+/i', $line, $matches)) {
                $this->results['total_score'] = $matches[1];

                continue;
            }
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

                        return ['name' => $test, 'score' => (float) $score];
                    }, explode(',', $tests));
                    $this->results['tests'] = $tests;
                    $this->results['autolearn'] = strtolower($autolearn) === 'yes';
                    $this->results['autolearn_force'] = strtolower($autolearn_force) === 'yes';
                    $this->results['version'] = $version;

                    continue;
                }
                $currentLine = $line;
            }
        }

        socket_close($socket);

        $this->mail->timings['spamassassin'] = microtime(true) - $timeBegin;

        return $this;
    }

    /**
     * Write to a socket cleanly.
     *
     * @param resource|bool $socket
     * @param string        $in
     *
     * @return bool
     */
    private function socketWrite($socket, string $in): bool
    {
        if (!$socket) {
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
