<?php

namespace Elephant\Contracts\EventLoop;

interface Kernel
{
    /**
     * Bootstrap the application for SMTP requests.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Runs the ReactPHP loop.
     *
     * @return void
     */
    public function handle();

    /**
     * Perform any final actions for when the loop closes.
     *
     * @return void
     */
    public function terminate();

    /**
     * Get the Elephant application instance.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public function getApplication();
}
