<?php

namespace Elephant\Filtering\Scanners;

use Elephant\Filtering\Scanners\Scanner;
use Elephant\Contracts\Mail\Scanner as ScannerContract;
use Elephant\Contracts\Mail\Mail;

class ClamAV extends Scanner
{
    const BYTES_WRITE = 512;

    const FOUND_REGEX = '/^(.*):\s+(?!Infected Archive)(.*)\s+FOUND$/';
    const ERROR_REGEX = '/^(.*):\s+(.*)\s+ERROR$/';

    /** @var \Illuminate\Filesystem\FilesystemManager $filesystem */
    private $filesystem;

    /** @var resource|bool $socket */
    private $socket;

    /** @var array $dsnData */
    private $dsnData;

    /** {@inheritDoc} */
    public function __construct(Mail $mail)
    {
        parent::__construct($mail);

        $this->filesystem = app('filesystem');

        $this->results = [
            'infected' => false,
            'error' => false,
            'viruses' => [],
            'errors' => [],
        ];

        $this->socket = false;

        $this->dsnData = $this->breakDsn(config('scanners.clamav.socket'));
    }

    /** {@inheritdoc} */
    public function scan(): ?ScannerContract
    {
        $timeBegin = microtime(true);

        $this->connect();

        $scanOnDisk = config('scanners.clamav.on_disk', false);

        if ($scanOnDisk) {
            if (! $this->multiscan()) {
                $this->mail->timings['clamav'] = microtime(true) - $timeBegin;
                unset($this->socket);

                return null;
            }
        } else {
            if (! $this->instream()) {
                $this->mail->timings['clamav'] = microtime(true) - $timeBegin;
                unset($this->socket);

                return null;
            }
        }

        $this->mail->timings['clamav'] = microtime(true) - $timeBegin;
        unset($this->socket);

        return $this;
    }

    private function genFiles(): string
    {
        $queueId = $this->mail->getQueueId();
        $sendEmail = config('scanners.clamav.send_email.enabled', false);
        if ($sendEmail) {
            $maxEmailSize = config('scanners.clamav.send_email.max_size', 128 * 1000); // 128 Kb
            $emailContents = $this->mail->getRaw();
            if ($maxEmailSize != 0) {
                $emailContents = substr($emailContents, 0, $maxEmailSize);
            }
            $this->filesystem->disk('tmp')->put("clamav/{$queueId}/email.eml", $emailContents);
        }
        $maxSize = config('scanners.clamav.max_size', 64 * 1000); // 64 Kb

        foreach ($this->mail->getBodyParts() as $i => $bodyPart) {
            $name = "part{$i}";
            if (! is_null($bodyPart->filename)) {
                $name = $bodyPart->filename;
            }
            if ($bodyPart->size > $maxSize) {
                break;
            }
            $this->filesystem->disk('tmp')->put("clamav/{$queueId}/{$name}", $bodyPart->getBody());
        }

        return "clamav/{$queueId}/";
    }

    /**
     * This runs a multiscan against the disk-stored parts.
     *
     * @return bool
     */
    private function multiscan(): bool
    {
        $lpath = $this->genFiles();
        $fpath = $this->filesystem->disk('tmp')->path($lpath);

        if (! $this->socketWrite("MULTISCAN {$fpath}")) {
            $this->error = 'Unable to write to socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($this->socket));
        }

        $this->read();

        return $this->filesystem->disk('tmp')->deleteDirectory($lpath);
    }

    /**
     * This runs a a synchronous scan against the in-memory parts.
     *
     * @return bool
     */
    private function instream(): bool
    {
        $sendEmail = config('scanners.clamav.send_email.enabled', false);
        if ($sendEmail) {
            $maxEmailSize = config('scanners.clamav.send_email.max_size', 128 * 1000); // 128 Kb
            $emailContents = $this->mail->getRaw();
            if ($maxEmailSize != 0) {
                $emailContents = substr($emailContents, 0, $maxEmailSize);
            }
            if (! $this->instreamSend($emailContents, 'email.eml')) {
                return false;
            }
        }
        $maxSize = config('scanners.clamav.max_size', 64 * 1000); // 64 Kb

        foreach ($this->mail->getBodyParts() as $i => $bodyPart) {
            if ($bodyPart->size > $maxSize) {
                continue;
            }
            
            if (! $this->instreamSend($bodyPart->getBody(), $bodyPart->filename ?? "part{$i}")) {
                return false;
            }
        }

        return true;
    }

    private function instreamSend(string $body, string $filename)
    {
        if (! isset($this->socket) || ! is_resource($this->socket)) {
            if (! $this->connect()) {
                return false;
            }
        }

        if (! $this->sendCommand("INSTREAM")) {
            $this->error = 'Unable to write to socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($this->socket));

            return false;
        }
        $left = $body;
        while (strlen($left) > 0) {
            $chunk = substr($left, 0, self::BYTES_WRITE);
            $left = substr($left, self::BYTES_WRITE);
            $this->sendChunk($chunk);
        }

        $this->endStream();

        if (! $this->read($filename)) {
            return false;
        }
    }

    /**
     * Reads from socket.
     *
     * @param string $fileName = ''
     *
     * @return bool
     */
    private function read(string $fileName = ''): bool
    {
        while ($line = @socket_read($this->socket, 512, PHP_NORMAL_READ)) {
            $line = rtrim($line);
            if (! empty($fileName)) {
                $line = preg_replace('/.*:/', "$fileName:", $line, 1);
            }

            if (preg_match(self::FOUND_REGEX, $line, $matches)) {
                [,$fname, $vname] = $matches;

                $this->results['viruses'][basename($fname)] = $vname;
                $this->results['infected'] = true;
            }
            if (preg_match(self::ERROR_REGEX, $line, $matches)) {
                [,$fname, $vname] = $matches;

                $this->results['errors'][basename($fname)] = $vname;
                $this->results['error'] = true;
            }
        }
        unset($this->socket);

        return true;
    }

    private function sendChunk($chunk)
    {
        $size = pack('N', strlen($chunk));
        // size packet
        socket_send($this->socket, $size, strlen($size), 0);
        // data packet
        socket_send($this->socket, $chunk, strlen($chunk), 0);
    }

    private function send($val)
    {
        return socket_send($this->socket, $val, strlen($val), 0);
    }

    private function sendCommand($command)
    {
        return $this->send("n{$command}\n");
    }

    private function endStream()
    {
        $this->send(pack('N', 0));
    }

    private function connect()
    {
        [$path, $port, $proto, $type] = $this->dsnData;

        if (is_null($type)) {
            $this->error = "Invalid socket type: $type";

            return false;
        }

        $this->socket = socket_create($type, SOCK_STREAM, $proto);
        if (! $this->socket) {
            $this->error = 'Unable to create socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($this->socket));

            return false;
        }
        if (! @socket_connect($this->socket, $path, $port)) {
            $this->error = 'Unable to connect to socket!';
            $this->error .= ' ' . socket_strerror(socket_last_error($this->socket));

            return false;
        }

        return true;
    }
}
