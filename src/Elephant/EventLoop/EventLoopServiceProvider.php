<?php

namespace Elephant\EventLoop;

use RuntimeException;
use React\Socket\Server;
use React\EventLoop\Factory;
use React\Socket\UnixServer;
use React\Socket\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Elephant\Contracts\EventLoop\ProcessManager;

class EventLoopServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            Elephant\Contracts\EventLoop\ProcessManager::class,
            Elephant\EventLoop\ProcessManager::class
        );

        $this->app->singleton('loop', function ($app) {
            return Factory::create();
        });

        $this->app->singleton(
            ProcessManager::class,
            \Elephant\EventLoop\ProcessManager::class
        );

        $this->app->singleton('stdin', function ($app) {
            return new ReadableResourceStream(STDIN, $app->loop);
        });
        $this->app->singleton('stdout', function ($app) {
            return new WritableResourceStream(STDOUT, $app->loop);
        });
        
        
        $this->app->bind('server', function ($app, $params) {
            $port = $params['port'];
            if (! isset($port)) {
                throw new RuntimeException('No port provided to listen on.');
            }
            $server = new Server($port, $app->loop);

            $server->on('connection', function (ConnectionInterface $connection) use ($app) {
                $pm = $app[ProcessManager::class];
                $process = null;
                if ($pm->getWaitingCount() < 1) {
                    if ($pm->getProcessCount() >= ($app->config['app.processes.max'] ?? 20)) {
                        $connection->end("450 Unable to accept more mail at this time.");

                        return;
                    }
                    
                    $process = $pm->createProcess();
                } else {
                    $pid = $pm->getNextWaitingPid();
                    $pm->markBusy($pid);
                    $process = $pm->getProcess($pid);
                }

                // Create a 2-way bridge between the input and output of the
                //     connection and subprocess.
                $connection->pipe($process->stdin);
                $process->stdout->pipe($connection);

                $connection->on('close', new EventLoopClose($app, $connection));
                $connection->on('end', new EventLoopTerminate($app, $connection));
                $connection->on('error', new EventLoopError($app, $connection));

                $process->stdin->write(
                    'CONNECT remote:' . $connection->getRemoteAddress() .
                        ' local:' . $connection->getLocalAddress()
                );
            });

            return $server;
        });

        $this->app->singleton('ipc', function ($app) {
            $server = new UnixServer(storage_path('app/run/elephant.sock'), $app->loop);
            $server->on('connection', function (ConnectionInterface $connection) use ($app) {
                $connection->on('data', new IPC\EventLoopData($app, $connection));
                $connection->on('error', function ($error) use ($connection) {
                    error("IPC Error: $error");
                    $connection->write("IPC Error: $error");
                });
            });

            return $server;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
