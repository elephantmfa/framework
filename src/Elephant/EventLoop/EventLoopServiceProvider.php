<?php

namespace Elephant\EventLoop;

use React\EventLoop\Factory;
use Illuminate\Support\ServiceProvider;
use React\Socket\ConnectionInterface;
use React\Socket\Server;
use RuntimeException;

class EventLoopServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('loop', function ($app) {
            return Factory::create();
        });

        $this->app->bind('server', function ($app, $params) {
            $port = $params['port'];
            if (! isset($port)) {
                throw new RuntimeException('No port provided to listen on.');
            }
            $filters = $params['filters'] ?? [];
            $server = new Server($port, $app['loop']);

            $server->on('connection', function (ConnectionInterface $connection) use ($app, $filters) {
                $cb = new EventLoopConnect($app, $filters);
                $mail = $cb($connection);
                $connection->on('data', new EventLoopData($app, $connection, $mail, $filters));
                $connection->on('error', new EventLoopError($app, $connection, $mail));
                $connection->on('end', new EventLoopClose($app, $connection));
                $connection->on('close', new EventLoopTerminate($app, $connection));
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
