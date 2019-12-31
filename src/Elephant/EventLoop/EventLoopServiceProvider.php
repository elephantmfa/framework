<?php

namespace Elephant\EventLoop;

use RuntimeException;
use React\Socket\Server;
use React\EventLoop\Factory;
use React\Socket\UnixServer;
use React\Socket\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use Elephant\Contracts\EventLoop\ProcessManager;
use Illuminate\Contracts\Foundation\Application;

/**
 * @property \Illuminate\Contracts\Foundation\Application&\ArrayAccess $app
 */
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
            \Elephant\Contracts\EventLoop\ProcessManager::class,
            \Elephant\EventLoop\ProcessManager::class
        );

        $this->app->singleton('loop', function (Application $app) {
            return Factory::create();
        });

        $this->app->singleton('stdin', function (Application $app) {
            /** @var Application&\ArrayAccess $app */
            return new ReadableResourceStream(STDIN, $app['loop']);
        });
        $this->app->singleton('stdout', function (Application $app) {
            /** @var Application&\ArrayAccess $app */
            return new WritableResourceStream(STDOUT, $app['loop']);
        });

        $this->app->bind('server', function (Application $app, array $params) {
            /** @var Application&\ArrayAccess $app */
            $port = $params['port'];
            if (! isset($port)) {
                throw new RuntimeException('No port provided to listen on.');
            }
            $server = new Server($port, $app['loop']);

            $server->on('connection', function (ConnectionInterface $connection) use ($app) {
                $pm = $app[ProcessManager::class];
                $process = $pid = null;
                if ($pm->getWaitingCount() < 1) {
                    if ($pm->getProcessCount() >= ($app->config['app.processes.max'] ?? 20)) {
                        $connection->end("450 Unable to accept more mail at this time.\r\n");

                        return;
                    }

                    $pid = $pm->createProcess();
                    $pm->markBusy($pid);
                    $process = $pm->getProcess($pid);
                } else {
                    $pid = $pm->getNextWaitingPid();
                    if (empty($pid)) {
                        $connection->end("450 Unable to accept more mail at this time.\r\n");

                        return;
                    }
                    $pm->markBusy($pid);
                    $process = $pm->getProcess($pid);
                }

                $pm->processHandled[$pid]++;

                // Create a 2-way bridge between the input and output of the
                //     connection and subprocess.
                $connection->on('data', function (string $data) use ($process) {
                    $process->stdin->write($data);
                });

                $process->stdout->on('data', function (string $data) use ($connection, $pid) {
                    $connection->write($data);
                    if (strpos($data, 'Goodbye') !== false) {
                        $this->app[ProcessManager::class]->markWaiting($pid);

                        $connection->end();

                        /** @var int $maxRequests */
                        $maxRequests = $this->app->config['app.processes.max_requests'] ?? 1000;
                        if ($this->app[ProcessManager::class]->processHandled > $maxRequests) {
                            $this->app[ProcessManager::class]->killProcess($pid);
                        }
                    }
                });
                $process->stderr->on('data', function (string $error) use ($connection, $pid) {
                    $connection->write($error);
                    $connection->end();
                    $this->app[ProcessManager::class]->killProcess($pid);
                });

                $connection->on('end', new EventLoopClose($app, $connection));
                $connection->on('close', new EventLoopTerminate($app, $connection));
                $connection->on('error', new EventLoopError($app, $connection));

                $process->stdin->write(
                    'CONNECT remote:' . (string) $connection->getRemoteAddress() .
                        ' local:' . (string) $connection->getLocalAddress() . "\r\n"
                );
            });

            return $server;
        });

        $this->app->singleton('ipc', function (Application $app) {
            /** @var Application&\ArrayAccess $app */
            $server = new UnixServer(storage_path('app/run/elephant.sock'), $app['loop']);
            $server->on('connection', function (ConnectionInterface $connection) use ($app) {
                $connection->on('data', new IPC\EventLoopData($app, $connection));
                $connection->on('error', function (string $error) use ($connection) {
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
