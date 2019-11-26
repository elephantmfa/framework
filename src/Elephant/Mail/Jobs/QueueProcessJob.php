<?php

namespace Elephant\Mail\Jobs;

use Elephant\Contracts\Mail\Mail;
use Illuminate\Bus\Queueable;
use Elephant\Foundation\Bus\Dispatchable;

class QueueProcessJob
{
    use Dispatchable, Queueable;

    protected $mail;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
    }
}
