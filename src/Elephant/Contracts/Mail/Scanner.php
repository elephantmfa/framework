<?php

namespace Elephant\Contracts\Mail;

interface Scanner
{
    /**
     * Scan a mail using the scanner. Returns the scanner if success, null on failure.
     *
     * @return Scanner|null
     */
    public function scan(): ?Scanner;

    /**
     * Set the user to use when scanning the mail.
     *
     * @return Scanner
     */
    public function setUser(string $email): Scanner;

    /**
     * Get the results of the scan.
     *
     * @return mixed
     */
    public function getResults();
}
