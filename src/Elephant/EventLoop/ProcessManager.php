<?php

namespace Elephant\EventLoop;

use ArrayAccess;
use Elephant\Contracts\EventLoop\ProcessManager as PMContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use React\ChildProcess\Process;

class ProcessManager implements PMContract, ArrayAccess
{
    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;
    /**
     * A collection of processes.
     *
     * @var array
     */
    protected $processes;

    /**
     * Processes that are marked as waiting.
     *
     * @var array
     */
    protected $waitingProcesses;

    /**
     * Array mapping `pid => handled count`.
     *
     * @var array
     */
    public $processHandled = [];

    /**
     * Construct a new Process Manager.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app The application instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->processes = [];
        $this->waitingProcesses = [];
    }

    /** {@inheritdoc} */
    public function getProcesses(): array
    {
        return $this->processes;
    }

    /** {@inheritdoc} */
    public function getProcessCount(): int
    {
        return count($this->processes);
    }

    /** {@inheritdoc} */
    public function getWaiting(): array
    {
        return array_filter($this->processes, function (Process $process, string $pid) {
            return in_array($pid, $this->waitingProcesses);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /** {@inheritdoc} */
    public function getNextWaitingPid(): string
    {
        return array_pop($this->waitingProcesses) ?? '';
    }

    /** {@inheritdoc} */
    public function getWaitingCount(): int
    {
        return count($this->waitingProcesses);
    }

    /** {@inheritdoc} */
    public function getBusy(): array
    {
        return array_filter($this->processes, function (Process $process, string $pid) {
            return ! in_array($pid, $this->waitingProcesses);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /** {@inheritdoc} */
    public function markWaiting(string $pid): void
    {
        $this->waitingProcesses[] = $pid;
    }

    /** {@inheritdoc} */
    public function markBusy(string $pid): void
    {
        foreach ($this->waitingProcesses as $index => $process_id) {
            if ($process_id == $pid) {
                unset($this->waitingProcesses[$index]);

                return;
            }
        }
    }

    /** {@inheritdoc} */
    public function getProcess(string $pid): ?Process
    {
        foreach ($this->processes as $process_id => $process) {
            if ($pid === $process_id) {
                return $process;
            }
        }

        return null;
    }

    /** {@inheritdoc} */
    public function createProcess(): string
    {
        /** @var string $elephantPath */
        $elephantPath = config('app.command_path', base_path('elephant'));
        $pid = sha1(Str::random() . Carbon::now()->toString());
        $command = "php {$elephantPath} subprocess:start --id=\"$pid\"";
        $this->processes[$pid] = new Process($command);

        /** @var \Elephant\Foundation\Application $app */
        $app = $this->app;
        $this->processes[$pid]->start($app->loop);

        $this->waitingProcesses[] = $pid;
        $this->processHandled[$pid] = 0;

        return $pid;
    }

    /** {@inheritdoc} */
    public function killProcess(string $pid): bool
    {
        $this->markBusy($pid);
        foreach ($this->processes[$pid]->pipes as $pipe) {
            $pipe->close();
        }

        info("[$pid] Closing process...");

        $return = $this->processes[$pid]->terminate();

        unset($this->processes[$pid]);

        if (count($this->processes) < ($this->app->config['app.processes.min'] ?? 5)) {
            $this->createProcess();
        }

        return $return;
    }

    /** {@inheritdoc} */
    public function offsetExists($offset) : bool
    {
        return isset($this->processes[$offset]);
    }

    /** {@inheritdoc} */
    public function offsetGet($offset)
    {
        return $this->processes[$offset];
    }

    /** {@inheritdoc} */
    public function offsetSet($offset, $value): void
    {
        throw new \Exception('Unable to set a new process. Please use ProcessManager::create() instead.');
    }

    /** {@inheritdoc} */
    public function offsetUnset($offset): void
    {
        if ($this->offsetExists($offset)) {
            $this->killProcess($offset);
        }

        throw new \Exception("$offset process does not exist.");
    }
}
