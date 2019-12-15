<?php

namespace Elephant\Foundation\Mail;

use Elephant\Contracts\Mail\Kernel as KernelContract;
use Elephant\EventLoop\Mail\EventLoopData;
use Elephant\Foundation\Application;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The bootstrap classes for the application.
     *
     * @var array
     */
    protected $bootstrappers = [
        \Elephant\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \Elephant\Foundation\Bootstrap\LoadConfiguration::class,
        \Elephant\Foundation\Bootstrap\HandleExceptions::class,
        \Elephant\Foundation\Bootstrap\RegisterFacades::class,
        \Elephant\Foundation\Bootstrap\RegisterProviders::class,
        \Elephant\Foundation\Bootstrap\BootProviders::class,
    ];

    /**
     * The filters to apply to different steps in the mail process.
     *
     * @var array
     */
    protected $filters = [

        /*
         * A list of filters to apply upon connection.
         *
         * @var array
         */
        'connect' => [],

        /*
         * A list of filters to apply when HELO/EHLO command is called.
         *
         * @var array
         */
        'helo' => [],

        /*
         * A list of filters to call when the MAIL FROM command is called.
         *
         * @var array
         */
        'mail_from' => [],

        /*
         * A list of filters to call upon each call of RCPT TO command.
         *
         * @var array
         */
        'rcpt_to' => [],

        /*
         * A list of filters to apply at the end of the DATA command.
         *
         * @var array
         */
        'data' => [],

        /*
         * A list of filters to apply to queued mail.
         *
         * @var array
         */
        'queued' => [],
    ];

    /**
     * The timeout ticker of the process.
     *
     * @var TimerInterface
     */
    protected $timeout;

    /**
     * The mail instance for the application.
     *
     * @var \Elephant\Contracts\Mail\Mail
     */
    protected $mail;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->pid = getmypid();
    }

    public function bootstrap()
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->addTerminatingCallback(function () {
            $this->app->stdin->close();
            $this->app->stdout->close();

            $this->app->loop->stop();
        });
    }

    public function handle()
    {
        $cb = new EventLoopData($this->app, $this->filters);
        $this->app->stdin->on('data', function ($data) use ($cb) {
            $this->app->loop->cancelTimer($this->timeout);
            $this->timeout = $this->app->loop->addTimer(config('app.processes.timeout'), function () {
                $this->terminate();
            });

            $cb($data);
        });

        info('Process started.');

        $this->timeout = $this->app->loop->addTimer(config('app.processes.timeout'), function () {
            $this->terminate();
        });
        $this->app->loop->run();
    }

    /**
     * Call the terminate method on any terminable middleware.
     *
     * @return void
     */
    public function terminate()
    {
        $this->app->terminate();
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    /**
     * Get the Laravel application instance.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public function getApplication()
    {
        return $this->app;
    }
}
