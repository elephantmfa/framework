<?php

namespace Elephant\Foundation\Exceptions;

use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

class Handler implements ExceptionHandlerContract
{
    /**
     * The container implementation.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [];

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var array
     */
    protected $internalDontReport = [
        ModelNotFoundException::class,
        SuspiciousOperationException::class,
    ];

    /**
     * Create a new exception handler instance.
     *
     * @param \Illuminate\Contracts\Container\Container $container
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /** {@inheritdoc} */
    public function report(Exception $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (is_callable($reportCallable = [$e, 'report'])) {
            return $this->container->call($reportCallable);
        }

        try {
            $logger = $this->container->make(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $e;
        }

        $logger->error(
            $e->getMessage(),
            ['exception' => $e]
        );
    }

    /** {@inheritdoc} */
    public function shouldReport(Exception $e)
    {
        return !$this->shouldntReport($e);
    }

    /** {@inheritdoc} */
    protected function shouldntReport(Exception $e)
    {
        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        return !is_null(Arr::first($dontReport, function ($type) use ($e) {
            return $e instanceof $type;
        }));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \React\Socket\ConnectionInterface $connection
     * @param \Exception                        $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($connection, Exception $e)
    {
        $connection->write($e->getMessage()."\r\n");
    }

    /** {@inheritdoc} */
    public function renderForConsole($output, Exception $e)
    {
        echo $e->getMessage()."\r\n";
        // (new ConsoleApplication)->renderException($e, $output);
    }
}
