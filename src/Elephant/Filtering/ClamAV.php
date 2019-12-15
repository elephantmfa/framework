<?php

namespace Elephant\Filtering;

use Elephant\Filtering\Scanner;
use Elephant\Contracts\Mail\Mail;

class ClamAV implements Scanner
{
    /** {@inheritDoc} */
    public function __construct(Mail $mail)
    {
        parent::__construct($mail);
        $this->results = [
            'infected' => false,
            'viruses' => [],
        ];
    }

    /** {@inheritdoc} */
    public function scan(): ?Scanner
    {
        $timeBegin = microtime(true);

        [$path, $port, $proto, $type] = $this->breakDsn(config('scanners.spamassassin.socket'));

        if (is_null($type)) {
            $this->error = "Invalid socket type: $type";

            return null;
        }

        $path = $this->genFiles();

        $socket = socket_create($type, SOCK_STREAM, $proto);
        if (! $socket) {
            $this->error = 'Unable to create socket!';

            return null;
        }
        if (! @socket_connect($socket, $path, $port)) {
            $this->error = 'Unable to connect to socket!';

            return null;
        }

        $this->socketWrite($socket, "MULTISCAN {$path}");

        $foundRegex = '/: (?!Infected Archive)(.*) FOUND/';
        while ($line = @socket_read($socket, 512, PHP_NORMAL_READ)) {
            if (preg_match($foundRegex, $line, $matches)) {
                [,$name] = $matches;

                $this->results['viruses'][] = $name;
                $this->results['infected'] = true;
            }
        }

        $this->app->filesystem->deleteDirectory($path);

        $this->mail->timings['clamav'] = microtime(true) - $timeBegin;

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
            $this->app->filesystem->disk('tmp')->put("clamav/{$queueId}/email.eml", $emailContents);
        }
        $maxSize = config('scanners.clamav.max_size', 64 * 1000); // 64 Kb

        foreach ($this->mail->bodyParts as $i => $bodyPart) {
            $name = "p{$i}";
            if (isset($bodyPart->filename)) {
                $name = $bodyPart->filename;
            }
            if ($bodyPart->size > $maxSize) {
                break;
            }
            $this->app->filesystem->disk('tmp')->put("clamav/{$queueId}/{$name}", $bodyPart->getBody());
        }

        return $this->app->filesystem->disk('tmp')->path("clamav/{$queueId}/");
    }
}
