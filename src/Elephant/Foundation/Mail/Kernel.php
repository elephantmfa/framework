<?php

namespace Elephant\Foundation\Mail;

use Elephant\Foundation\Application;
use Elephant\Contracts\Mail\Kernel as KernelContract;

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
        // \Elephant\Foundation\Bootstrap\HandleExceptions::class,
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

        /**
         * A list of filters to apply upon connection.
         *
         * @var array
         */
        'connect' => [ ],

        /**
         * A list of filters to apply when HELO/EHLO command is called.
         *
         * @var array
         */
        'helo' => [ ],

        /**
         * A list of filters to call when the MAIL FROM command is called.
         *
         * @var array
         */
        'mail_from' => [ ],

        /**
         * A list of filters to call upon each call of RCPT TO command.
         *
         * @var array
         */
        'rcpt_to' => [ ],

        /**
         * A list of filters to apply at the end of the DATA command.
         *
         * @var array
         */
        'data' => [ ],

        /**
         * A list of filters to apply to queued mail.
         *
         * @var array
         */
        'queued' => [ ],
    ];

    /**
     * The PID of the elephant process.
     *
     * @var int
     */
    protected $pid;

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
    }

    public function handle()
    {
        //
    }

    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
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
     * Report the exception to the exception handler.
     *
     * @param  \Exception  $e
     * @return void
     */
    protected function reportException(Exception $e)
    {
        $this->app[ExceptionHandler::class]->report($e);
    }

    /**
     * Render the exception to a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderException($request, Exception $e)
    {
        return $this->app[ExceptionHandler::class]->render($request, $e);
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
