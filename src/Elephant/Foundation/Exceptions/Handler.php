<?php

namespace Elephant\Foundation\Exceptions;

use Exception;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Elephant\Helpers\Matchers\Regex;
use React\Stream\WritableStreamInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\Console\Application as ConsoleApplication;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;

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

        return ! is_null(Arr::first($dontReport, function ($type) use ($e) {
            return $e instanceof $type;
        }));
    }

    /** {@inheritDoc} */
    public function render($request, Exception $e)
    {
        // Not implemented
    }

    /** {@inheritdoc} */
    public function renderForConsole($output, Exception $e)
    {
        try {
            if ($this->container->mailcontext) {
                $this->renderForMail($this->container->stdout, $e);

                return;
            }
        } catch (Exception $e2) {
            // Fall down to ConsoleApplication render.
        }

        (new ConsoleApplication)->renderThrowable($e, $output);
    }

    /**
     * Render an exception to the console.
     *
     * @param \React\Stream\WritableStreamInterface $connection
     * @param \Exception                            $e
     * @return void
     */
    public function renderForMail(WritableStreamInterface $connection, Exception $e): void
    {
        $code = $e->getCode();
        if ($code < 400 || $code > 599) {
            $code = $this->container->config['relay.defer_on_exception'] ?? true ? 450 : 550;
        }

        $advancedCode = '4.0.0 ';
        if ($code >= 500) {
            $advancedCode = '5.0.0 ';
        }


        $response = "{$code} {$advancedCode}Server Configuration Error";
        if ($this->container->config['app.debug'] ?? false) {
            if (Regex::match('/^\d\.\d\.\d\s+/', $e->getMessage())) {
                $advancedCode = '';
            }

            $exceptionMessage = $e->getMessage();

            $response = "{$code} {$advancedCode}Server Configuration Error: {$exceptionMessage}";
        }

        error("Responded with: <$response> due to exception.");
        fwrite(STDERR, "$response\r\n");
    }
}
