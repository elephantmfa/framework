<?php

namespace Elephant\Mail\Jobs;

use Illuminate\Bus\Queueable;
use Elephant\Foundation\Bus\Dispatchable;

class QueueJob
{
    use Dispatchable, Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
