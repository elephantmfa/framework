<?php

namespace Elephant\Foundation\Console\Commands;

use Illuminate\Console\Command;

class SubprocessStartCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'subprocess:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a subprocess. Note: Should not be used manually.';

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'subprocess:start {--id= : The process ID to start with.}';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        config(['app.process_id' => $this->option('id')]);

        $kernel = app()->make(\Elephant\Contracts\Mail\Kernel::class);

        $kernel->bootstrap();

        $kernel->handle();
    }
}
