<?php

namespace Elephant\Filtering\Scanners;

use Elephant\Filtering\Scanners\Scanner;
use Elephant\Contracts\Mail\Scanner as ScannerContract;
use Elephant\Contracts\Mail\Mail;
use Elephant\Mail\Mail as M;
use Elephant\Helpers\Exceptions\SocketException;
use Elephant\Helpers\Socket;

class ClamAV extends Scanner
{
    const BYTES_RW = 512;

    const FOUND_REGEX = '/^(.*):\s+(?!Infected Archive)(.*)\s+FOUND$/';
    const ERROR_REGEX = '/^(.*):\s+(.*)\s+ERROR$/';

    /** @var \Illuminate\Contracts\Filesystem\Filesystem $filesystem */
    private $filesystem;

    /** @var Socket|null $socket */
    private $socket = null;

    /**
     * The mail message to be scanning.
     *
     * @var \Elephant\Contracts\Mail\Mail|null $mail
     */
    protected $mail = null;

    /** {@inheritDoc} */
    public function __construct()
    {
        parent::__construct();

        /** @var \Illuminate\Filesystem\FilesystemManager $filesystem */
        $filesystem = app('filesystem');
        $this->filesystem = $filesystem->disk('tmp');

        $this->results = [
            'infected' => false,
            'error' => false,
            'viruses' => [],
            'errors' => [],
        ];
    }

    /** {@inheritdoc} */
    public function scan(Mail $mail): ?ScannerContract
    {
        $this->mail = $mail;

        $timeBegin = microtime(true);

        if (! $this->reconnect()) {
            return null;
        }

        $scanOnDisk = config('scanners.clamav.on_disk', false);

        if ($scanOnDisk) {
            if (! $this->multiscan()) {
                $this->mail->addExtraData(M::TIMINGS, 'clamav', microtime(true) - $timeBegin);
                unset($this->socket);

                return null;
            }
        } else {
            if (! $this->instream()) {
                $this->mail->addExtraData(M::TIMINGS, 'clamav', microtime(true) - $timeBegin);
                unset($this->socket);

                return null;
            }
        }

        $this->mail->addExtraData(M::TIMINGS, 'clamav', microtime(true) - $timeBegin);
        unset($this->socket);

        return $this;
    }

    private function genFiles(): string
    {
        if (! isset($this->mail)) {
            return '';
        }

        $queueId = $this->mail->getQueueId();
        $sendEmail = config('scanners.clamav.send_email.enabled', false);
        if ($sendEmail) {
            $maxEmailSize = config('scanners.clamav.send_email.max_size', 128 * 1000); // 128 Kb
            $emailContents = $this->mail->getRaw();
            if ($maxEmailSize != 0) {
                $emailContents = substr($emailContents, 0, $maxEmailSize);
            }
            $this->filesystem->put("clamav/{$queueId}/full-email.eml", $emailContents);
        }
        $maxSize = config('scanners.clamav.max_size', 64 * 1000); // 64 Kb

        foreach ($this->mail->getBodyParts() as $i => $bodyPart) {
            $name = "body-part{$i}";

            if ($bodyPart->disposition == 'body' && preg_match(';application/html;', $bodyPart->contentType)) {
                $name .= '.html';
            }

            if (! empty($bodyPart->filename)) {
                $name = $bodyPart->filename;
            }
            if ($bodyPart->size > $maxSize) {
                break;
            }
            $this->filesystem->put("clamav/{$queueId}/{$name}", $bodyPart->getBody());
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
        if (! isset($this->socket)) {
            return false;
        }

        $lpath = $this->genFiles();
        /** @var \Illuminate\Filesystem\Filesystem $fs */
        $fs = $this->filesystem;
        $fpath = $fs->path($lpath);

        if (! $this->sendCommand("MULTISCAN {$fpath}")) {
            $this->error = 'Unable to write to socket!';
            if (config('app.debug', false)) {
                $this->error .= ' ' . $this->socket->getLastError();
            }
        }

        $this->read();

        return $this->filesystem->deleteDirectory($lpath);
    }

    /**
     * This runs a a synchronous scan against the in-memory parts.
     *
     * @return bool
     */
    private function instream(): bool
    {
        if (is_null($this->mail)) {
            return false;
        }
        $sendEmail = config('scanners.clamav.send_email.enabled', false);
        if ($sendEmail) {
            $maxEmailSize = config('scanners.clamav.send_email.max_size', 128 * 1000); // 128 Kb
            $emailContents = $this->mail->getRaw();
            if ($maxEmailSize != 0) {
                $emailContents = substr($emailContents, 0, $maxEmailSize);
            }

            if (! $this->instreamSend($emailContents, 'full-email.eml')) {
                return false;
            }
        }
        $maxSize = config('scanners.clamav.max_size', 64 * 1000); // 64 Kb

        foreach ($this->mail->getBodyParts() as $i => $bodyPart) {
            if ($bodyPart->size > $maxSize) {
                continue;
            }

            $defName = "body-part{$i}";

            if ($bodyPart->disposition == 'body' && preg_match(';application/html;', $bodyPart->contentType)) {
                $defName .= '.html';
            }

            if (! $this->instreamSend($bodyPart->getBody(), $bodyPart->filename ?: $defName)) {
                return false;
            }
        }

        return true;
    }

    private function instreamSend(string $body, string $filename): bool
    {
        if (is_null($this->socket)) {
            return false;
        }

        debug("clamav: sending $filename");
        $this->reconnect();

        if (! $this->sendCommand("INSTREAM")) {
            $this->error = 'Unable to write to socket!';
            if (config('app.debug', false) && ! is_null($this->socket)) {
                $this->error .= ' ' . $this->socket->getLastError();
            }

            return false;
        }
        $left = $body;
        while (strlen($left) > 0) {
            $chunk = substr($left, 0, self::BYTES_RW);
            $left = substr($left, self::BYTES_RW);
            if (! $this->sendChunk($chunk)) {
                return false;
            }
        }

        if (! $this->endStream()) {
            $this->error = "Unable to end stream.";

            return false;
        }

        if (! $this->read($filename)) {
            return false;
        }

        return true;
    }

    /**
     * Reads from socket.
     *
     * @param string $fileName = ""
     *
     * @return bool
     */
    private function read(string $fileName = ''): bool
    {
        if (is_null($this->socket)) {
            return false;
        }

        while (! empty($line = $this->socket->read(self::BYTES_RW))) {
            if (! empty($fileName)) {
                $line = preg_replace('/.*:/', "$fileName:", $line, 1);
            }

            debug("clamav: response: <$line>");

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
        $this->socket->close();

        return true;
    }

    private function sendChunk(string $chunk): bool
    {
        if (is_null($this->socket)) {
            return false;
        }

        try {
            $size = pack('N', strlen($chunk));

            $this->socket->send($size);
            $this->socket->send($chunk);
        } catch (SocketException $e) {
            $this->error = $e->getMessage();

            return false;
        }

        return true;
    }

    private function sendCommand(string $command): bool
    {
        if (is_null($this->socket)) {
            return false;
        }

        return $this->socket->send("n{$command}\n") > 0;
    }

    private function endStream(): bool
    {
        if (is_null($this->socket)) {
            return false;
        }

        return $this->socket->send(pack('N', 0)) > 0;
    }

    private function reconnect(): bool
    {
        if (! isset($this->socket) || ! ($this->socket instanceof Socket)) {
            try {
                /** @var Socket $socket */
                $socket = app(Socket::class);
                $this->socket = $socket->setDsn(config('scanners.clamav.socket', 'ipv4://127.0.0.1:3310'));
            } catch (SocketException $e) {
                unset($this->socket);

                return false;
            }
        }

        return true;
    }
}
