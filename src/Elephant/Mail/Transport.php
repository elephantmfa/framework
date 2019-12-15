<?php

namespace Elephant\Mail;

use Elephant\Contracts\Mail\Mail;

class Transport
{
    /** @var Mail */
    private $mail;

    private function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    public static function send(Mail $mail)
    {
        (new static($mail))->deliver();
    }

    private function deliver()
    {
        $finalDestiny = $this->mail->getFinalDestination();

        $relayRegex = '/
            ^(?:relay:)?
            (  \[[A-Fa-f0-9:]+\]  |  \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}  )
            :(\d{1,5})
            /x';

        if (in_array($finalDestiny, ['drop', 'reject', 'defer'])) {
            // Final destiny deletes the mail.
            return;
        } elseif ($finalDestiny == 'quarantine') {
            // Final Destiny is quarantine
            app('filesystem')->put("quarantine/{$this->mail->getQueueId()}.eml", $this->mail->getRaw());
        } elseif (preg_match('/^.+@\w+\..+$/', $finalDestiny)) {
            // Final Destiny is an email address
            $this->mail->removeAllRecipients()
                ->addRecipient($finalDestiny);

            [$ip, $port] = explode(':', config('relay.default_relay'));
            $ip = trim($ip, '[]');
            $this->sendTo($ip, $port);
        } elseif (preg_match($relayRegex, $finalDestiny, $matches)) {
            // Final destiny is a destination ip:port
            /**
             * @var string $ip
             * @var int $port
             */
            [, $ip, $port] = $matches;
            $ip = trim($ip, '[]');
            $this->sendTo($ip, $port);
        } else {
            /**
             * @var string $ip
             * @var int $port
             */
            [$ip, $port] = explode(':', config('relay.default_relay'));
            $ip = trim($ip, '[]');
            $this->sendTo($ip, $port);
        }
    }

    private function sendTo(string $ip, int $port): void
    {
        //
    }
}
