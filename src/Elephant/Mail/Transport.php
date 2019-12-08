<?php

namespace Elepahnt\Mail;

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
        $finalDestiny = $this->mail->getFinalDestiny();

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
        } elseif (preg_match($relayRegex, $finalDestiny, $matches)) {
            // Final destiny is a destination ip:port
            [, $ip, $port] = $matches;
            $ip = trim($ip, '[]');
        }
    }
}
