<?php

namespace Elephant\Foundation\EventLoop;

use Elephant\Foundation\Application;
use Elephant\Contracts\EventLoop\Kernel as KernelContract;
use Elephant\Contracts\EventLoop\ProcessManager;

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
     * The PID of the elephant process.
     *
     * @var int
     */
    protected $pid;

    protected $servers;

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
            foreach ($this->servers as $server) {
                $server->close();
            }

            foreach ($this->app[ProcessManager::class]->getProcesses() as $pid => $process) {
                $this->app[ProcessManager::class]->killProcess($pid);
            }
            
            $this->app->loop->stop();
            $this->removePID();
        });

        $this->app->loop->addSignal(SIGINT, function (int $signal) {
            $this->terminate();
            die("\nClosing " . $this->app->config['app.name'] . " [{$this->pid}].\n");
        });
    }

    public function handle()
    {
        if ($this->PIDExists()) {
            die($this->app->config['app.name'] . " is already running.\n");
        }

        for ($i=0; $i < $this->app->config['app.processes.min']; $i++) {
            // @todo make base subprocesses.
            // `php elephant subprocess:start --id="md5-Id"`
        }

        foreach ($this->app->config['relay.ports'] as $port) {
            $this->servers[] = $this->app->make('server', [
                'port' => $port,
                'filters' => $this->filters,
            ]);
        }

        $this->servers[] = $this->app->make('rpc');

        $this->writePID();

        $this->app->loop->run();
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

    /**
     * Writes the PID out to a PID file.
     *
     * @return void
     */
    public function writePID()
    {
        $this->app['filesystem']->put('run/elephant.pid', $this->pid);
    }

    /**
     * Writes the PID out to a PID file.
     *
     * @return void
     */
    public function removePID()
    {
        $this->app['filesystem']->delete('run/elephant.pid');
    }

    /**
     * Gets the PID from the PID file
     *
     * @return int
     */
    public function getPID(): int
    {
        return (int) $this->app['filesystem']->get('run/elephant.pid');
    }

    /**
     * Gets the PID from the PID file
     *
     * @return bool
     */
    public function PIDExists(): bool
    {
        return $this->app['filesystem']->exists('run/elephant.pid');
    }
}
