<?php

namespace Elephant\Foundation\EventLoop;

use Elephant\Contracts\EventLoop\Kernel as KernelContract;
use Elephant\Contracts\EventLoop\ProcessManager;
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
     * The PID of the elephant process.
     *
     * @var int
     */
    protected $pid;

    protected $servers;

    protected $spamd;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->pid = getmypid();
    }

    /** {@inheritdoc} */
    public function bootstrap()
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->addTerminatingCallback(function () {
            if (isset($this->smtpd)) {
                $this->smtpd->close();
            }

            foreach ($this->servers as $server) {
                $server->close();
            }
            if (!@unlink(storage_path('app/run/elephant.sock'))) {
                error('Failed to remove socket: '.storage_path('app/run/elephant.sock'));
            }

            foreach ($this->app[ProcessManager::class]->getProcesses() as $pid => $process) {
                $this->app[ProcessManager::class]->killProcess($pid);
            }

            $this->app->loop->stop();
            $this->removePID();
            info('Closing '.$this->app->config['app.name']." [{$this->pid}].");
            echo 'Closing '.$this->app->config['app.name']." [{$this->pid}].\n";
        });

        $this->app->loop->addSignal(SIGINT, function (int $signal) {
            $this->terminate();
        });
    }

    /** {@inheritdoc} */
    public function handle()
    {
        if ($this->PIDExists()) {
            die($this->app->config['app.name']." is already running.\n");
        }

        if ($this->app->config['scanners.spamassassin.spamd.manage'] ?? false) {
            $opts = $this->app->config['scanners.spamassassin.spamd.parameters'] ?? [];
            $opts = implode(' ', $opts);
            $this->spamd = new React\ChildProcess\Process("spamd $opts");
            $this->spamd->stdout->on('data', function ($data) {
                info($data);
            });
            $this->spamd->stderr->on('data', function ($data) {
                error($data);
            });
        }

        for ($i = 0; $i < $this->app->config['app.processes.min']; $i++) {
            $this->app[ProcessManager::class]->createProcess();
        }

        foreach ($this->app->config['relay.ports'] as $port) {
            $this->servers[] = $this->app->make('server', [
                'port' => $port,
            ]);
        }

        $this->servers[] = $this->app->make('ipc');

        $this->writePID();

        $this->app->loop->run();
    }

    /** {@inheritdoc} */
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
     * Gets the PID from the PID file.
     *
     * @return int
     */
    public function getPID(): int
    {
        return (int) $this->app['filesystem']->get('run/elephant.pid');
    }

    /**
     * Gets the PID from the PID file.
     *
     * @return bool
     */
    public function PIDExists(): bool
    {
        return $this->app['filesystem']->exists('run/elephant.pid');
    }
}
