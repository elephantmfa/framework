<?php

namespace Elephant\Contracts\Mail;

interface Scanner
{
    /**
     * Scan a mail using the scanner. Returns the scanner if success, null on failure.
     *
     * @param Mail $mail The mail to be scanned.
     * @return Scanner|null
     */
    public function scan(Mail $mail): ?self;

    /**
     * Set the user to use when scanning the mail.
     *
     * @return Scanner
     */
    public function setUser(string $email): self;

    /**
     * Get the results of the scan.
     *
     * @return mixed
     */
    public function getResults();
}
