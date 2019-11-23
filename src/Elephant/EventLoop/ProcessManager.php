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


    /** {@inheritDoc} */
    public function getProcesses(): array
    {
        return $this->processes;
    }

    /** {@inheritDoc} */
    public function getProcessCount(): int
    {
        return sizeof($this->processes);
    }

    /** {@inheritDoc} */
    public function getWaiting(): array
    {
        return array_filter($this->processes, function ($process, $pid) {
            return in_array($pid, $this->waitingProcesses);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /** {@inheritDoc} */
    public function getNextWaitingPid(): string
    {
        return $this->waitingProcesses[0];
    }

    /** {@inheritDoc} */
    public function getWaitingCount(): int
    {
        return sizeof($this->waitingProcesses);
    }

    /** {@inheritDoc} */
    public function getBusy(): array
    {
        return array_filter($this->processes, function ($process, $pid) {
            return ! in_array($pid, $this->waitingProcesses);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /** {@inheritDoc} */
    public function markWaiting(string $pid): void
    {
        $this->waitingProcesses[] = $pid;
    }

    /** {@inheritDoc} */
    public function markBusy(string $pid): void
    {
        foreach ($this->waitingProcesses as $index => $process_id) {
            if ($process_id == $pid) {
                unset($this->waitingProcesses[$index]);

                return;
            }
        }
    }

    /** {@inheritDoc} */
    public function getProcess(string $pid): Process
    {
        foreach ($this->processes as $process_id => $process) {
            if ($pid === $process_id) {
                return $process;
            }
        }
    }

    /** {@inheritDoc} */
    public function createProcess(): Process
    {
        $elephantPath = config('app.command_path', base_path('elephant'));
        $pid = sha1(Str::random() . Carbon::now()->toString());
        $command = "php {$elephantPath} subprocess:start --id=\"$pid\"";

        $this->processes[$pid] = new Process($command);
        $this->processes[$pid]->start($this->app->loop);

        $this->waitingProcesses[] = $pid;

        return $this->processes[$pid];
    }

    /** {@inheritDoc} */
    public function killProcess(string $pid): bool
    {
        $this->markBusy($pid);
        foreach ($this->processes[$pid]->pipes as $pipe) {
            $pipe->close();
        }

        return $this->processes[$pid]->terminate();
    }

    /** {@inheritDoc} */
    public function offsetExists($offset) : bool
    {
        return isset($this->processes[$offset]);
    }

    /** {@inheritDoc} */
    public function offsetGet($offset)
    {
        return $this->processes[$offset];
    }

    /** {@inheritDoc} */
    public function offsetSet($offset, $value): void
    {
        throw new \Exception('Unable to set a new process. Please use ProcessManager::create() instead.');
    }

    /** {@inheritDoc} */
    public function offsetUnset($offset): void
    {
        if ($this->offsetExists($offset)) {
            $this->killProcess($offset);
        }

        throw new \Exception("$offset process does not exist.");
    }
}
