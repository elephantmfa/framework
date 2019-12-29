<?php

namespace Elephant\Contracts\EventLoop;

use React\ChildProcess\Process;

interface ProcessManager
{
    /**
     * Get all processes.
     *
     * @return array
     */
    public function getProcesses(): array;

    /**
     * Get the count of all processes.
     *
     * @return int
     */
    public function getProcessCount(): int;

    /**
     * Get all waiting processes.
     *
     * @return array
     */
    public function getWaiting(): array;

    /**
     * Get the count of the waiting processes.
     *
     * @return int
     */
    public function getWaitingCount(): int;

    /**
     * Gets the next waiting process PID.
     *
     * @return string
     */
    public function getNextWaitingPid(): string;

    /**
     * Get all the busy processes.
     *
     * @return array
     */
    public function getBusy(): array;

    /**
     * Mark a process as waiting.
     *
     * @param string $pid
     *
     * @return void
     */
    public function markWaiting(string $pid): void;

    /**
     * Mark a process as busy.
     *
     * @param string $pid
     *
     * @return void
     */
    public function markBusy(string $pid): void;

    /**
     * Get a specific process from ID.
     *
     * @param string $pid The process ID to get the process of.
     *
     * @return Process|null
     */
    public function getProcess(string $pid): ?Process;

    /**
     * Create a new waiting process.
     *
     * @return string
     */
    public function createProcess(): string;

    /**
     * Kill an existing process.
     *
     * @param string $pid the id of the process to kill.
     *
     * @return bool
     */
    public function killProcess(string $pid): bool;
}
