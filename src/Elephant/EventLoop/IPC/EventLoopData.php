<?php

namespace Elephant\EventLoop\IPC;

use Elephant\Contracts\EventLoop\ProcessManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use React\Socket\ConnectionInterface;

class EventLoopData
{
    /** @var Application&\ArrayAccess $app */
    protected $app;

    /** @var ConnectionInterface $connection */
    protected $connection;

    /**
     * @param Application&\ArrayAccess $app
     * @param ConnectionInterface $connection
     */
    public function __construct(Application $app, ConnectionInterface $connection)
    {
        $this->app = $app;
        $this->connection = $connection;
    }

    public function __invoke(string $data)
    {
        $data = trim($data);
        info("IPC Command: $data");
        $this->connection->write(strtolower($data));
        if (in_array(strtolower($data), ['kill', 'quit'])) {
            $this->app->terminate();
        } elseif (Str::startsWith(strtolower($data), 'waiting')) {
            [, $pid] = explode(' ', $data);
            $this->app[ProcessManager::class]->markWaiting($pid);
        }
    }
}
