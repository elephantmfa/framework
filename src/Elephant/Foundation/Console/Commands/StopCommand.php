<?php

namespace Elephant\Foundation\Console\Commands;

use Illuminate\Console\Command;

class StopCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'stop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stops the application from running.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        @socket_connect($socket, storage_path('app/run/elephant.sock'));
        @socket_write($socket, "QUIT\r\n");
        @socket_close($socket);
    }
}
