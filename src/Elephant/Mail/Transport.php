<?php

namespace Elephant\Mail;

use Elephant\Contracts\Mail\Mail;
use Elephant\Helpers\Socket;
use Elephant\Mail\Exceptions\TransportException;

class Transport
{
    const RELAY_REGEX = '/
        ^(?:relay:)?
        (  \[[A-Fa-f0-9:]+\]  |  \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}  )
        :(\d{1,5})
        /x';

    /** @var Mail $mail */
    private $mail;

    /** @var string $destination */
    private $destination;

    private function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    public static function send(Mail $mail)
    {
        (new static($mail))
            ->route();
    }

    public static function sendTo(Mail $mail, string $destination)
    {
        (new static($mail))
            ->setDestination($destination)
            ->route($destination);
    }

    private function setDestination(string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    private function route()
    {
        if (empty($this->destination)) {
            $finalDestiny = $this->mail->getFinalDestination();
        } else {
            $finalDestiny = $this->destination;
        }

        if (in_array($finalDestiny, ['reject', 'defer'])) {
            // Final destiny deletes the mail.
            return;
        } elseif (in_array($finalDestiny, ['quarantine', 'drop'])) {
            // Final Destiny is quarantine
            $queueId = $this->mail->getQueueId();
            $folderName = substr($queueId, 0, 2);
            app('filesystem')->put("quarantine/{$folderName}/{$queueId}.eml", $this->mail->getRaw());
        } elseif (preg_match('/^.+@\w+\..+$/', $finalDestiny)) {
            // Final Destiny is an email address
            $this->mail->removeAllRecipients()
                ->addRecipient($finalDestiny);

            [$ip, $port] = explode(':', config('relay.default_relay'));
            $ip = trim($ip, '[]');
            $port = intval($port);
            $this->deliver($ip, $port);
        } elseif (preg_match(self::RELAY_REGEX, $finalDestiny, $matches)) {
            // Final destiny is a destination ip:port
            /**
             * @var string $ip
             * @var int $port
             */
            [, $ip, $port] = $matches;
            $ip = trim($ip, '[]');
            $port = intval($port);
            $this->deliver($ip, $port);
        } else {
            /**
             * @var string $ip
             * @var int $port
             */
            [$ip, $port] = explode(':', config('relay.default_relay'));
            $ip = trim($ip, '[]');
            $port = intval($port);
            $this->deliver($ip, $port);
        }
    }

    private function deliver(string $ip, int $port): void
    {
        $proto = 'ipv4://';
        if (strpos($proto, ':') !== false) {
            $proto = 'ipv6://';
        }
        $socket = new Socket("{$proto}{$ip}:{$port}");
        $socket->send('EHLO ' . config('app.name') . "\r\n");
        $helo = $socket->read(8192, PHP_BINARY_READ);
        $this->processResp($helo, 'EHLO');
        if (strpos($helo, 'XFORWARD') !== false) {
            //@todo: send xforward
        }
        $socket->send("MAIL FROM: {$this->mail->envelope->sender}\r\n");
        $this->processResp($socket->read(), 'MAIL FROM');
        foreach ($this->mail->envelope->recipients as $recipient) {
            $socket->send("RCPT TO: {$recipient}\r\n");
            $this->processResp($socket->read(), 'RCPT TO');
        }

        $socket->send("DATA\r\n");
        $this->processResp($socket->read(), 'DATA');
        foreach (explode("\n", $this->mail->getRaw()) as $line) {
            if (strpos($line, '.') === 0) {
                $socket->send('.');
            }
            $socket->send("$line\r\n");
        }
        $socket->send("\r\n.\r\n");
        $this->processResp($socket->read(), 'DATA');
        $socket->close();
    }

    private function processResp(string $resp, string $stage): void
    {
        [$code, $message] = explode(' ', $resp, 2);
        $code = intval($code);
        if ($code >= 400) {
            throw new TransportException($code, $stage, $message);
        }
    }
}
