<?php

namespace Elephant\EventLoop;

use Illuminate\Support\Str;
use Elephant\Contracts\Mail\Mail;
use Illuminate\Pipeline\Pipeline;
use React\Socket\ConnectionInterface;
use Illuminate\Contracts\Container\Container;
use Elephant\EventLoop\Traits\CommunicateTrait;
use Illuminate\Support\Carbon;

class EventLoopData
{
    use CommunicateTrait;

    /**
     * The service container.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * The filters to be able to apply to each step.
     *
     * @var array
     */
    protected $filters;

    /**
     * The mail interface to be working with.
     *
     * @var \Elephant\Contracts\Mail\Mail
     */
    protected $mail;

    /**
     * Whether or not the data loop is in read mode or not.
     *
     * @var bool
     */
    protected $readMode = false;

    /**
     * The current line being read in.
     *
     * @var string
     */
    protected $currentLine = '';

    /**
     * Whether or not we are reading a body.
     *
     * @var bool
     */
    protected $readingBody = false;

    /**
     * Builds the event loop data invokable class.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     * @param \React\Socket\ConnectionInterface $connection
     * @param \Elephant\Contracts\Mail\Mail $mail
     * @param array $filters
     * @return void
     */
    public function __construct(Container $app, ConnectionInterface $connection, Mail &$mail, array $filters)
    {
        $this->app = $app;
        $this->connection = $connection;
        $this->filters = $filters;
        $this->mail = $mail;
        $this->readMode = false;
    }

    /**
     * Run the event loop step.
     *
     * @param string $data
     * @return void
     */
    public function __invoke($data)
    {
        if (substr_count($data, "\r\n") > 1) {
            $chunks = explode("\r\n", $data);
            foreach ($chunks as $chunk) {
                $this->__invoke("$chunk\r\n");
            }

            return;
        }
        if ($this->readMode) {
            $data = str_replace("\r\n", "\n", $data);
            $this->handleData($data);
            if ($this->app->config['app.debug']) {
                dump($data, $this->mail);
            }

            return;
        }
        $data = trim($data);
        $lcData = strtolower($data);
        if (Str::startsWith($lcData, ['helo', 'ehlo'])) {
            $this->handleHelo($data);
        } elseif (Str::startsWith($lcData, ['mail from:'])) {
            $this->handleMailFrom($data);
        } elseif (Str::startsWith($lcData, ['rcpt to:'])) {
            $this->handleRcptTo($data);
        } elseif (Str::startsWith($lcData, ['xforward'])) {
            $this->handleXForward($data);
        } elseif (Str::startsWith($lcData, ['data'])) {
            if (count($this->mail->envelope->recipients) < 1) {
                $this->say('503 5.5.1 Error: need RCPT command');

                return;
            }
            $this->say('354 End data with <CR><LF>.<CR><LF>');
            $this->readMode = true;
        } elseif (Str::startsWith($lcData, ['quit'])) {
            $this->handleQuit($data);
        } elseif (Str::startsWith($lcData, ['rset'])) {
            $this->handleReset($data);
        } elseif (Str::startsWith($lcData, ['noop'])) {
            $this->handleNoop($data);
        } elseif (Str::startsWith($lcData, ['vrfy'])) {
            $this->handleVerify($data);
        } elseif (!empty($data)) {
            $this->handleUnknownCommand($data);
        }
        if ($this->app->config['app.debug']) {
            dump($data, $this->mail);
        }
    }

    /**
     * Handle the `QUIT` SMTP command.
     *
     * @param string $data
     * @return void
     */
    protected function handleUnknownCommand(string $data)
    {
        $this->say('502 5.5.2 Error: command not recognized');
    }

    /**
     * Handle the `QUIT` SMTP command.
     *
     * @param string $data
     * @return void
     */
    protected function handleQuit(string $data)
    {
        $this->close('221 2.0.0 Goodbye');
    }

    /**
     * Handle the `NOOP` SMTP command.
     *
     * @param string $data
     * @return void
     */
    protected function handleNoop(string $data)
    {
        $this->say('250 2.0.0 Ok');
    }

    /**
     * Handle the `RSET` SMTP command.
     *
     * @param string $data
     * @return void
     */
    protected function handleReset(string $data)
    {
        $nmail = $this->app->make(Mail::class);
        $nmail->connection = $this->mail->connection;
        $nmail->envelope->helo = $this->mail->envelope->helo;
        $this->mail = $nmail;
        $this->say('250 2.0.0 Ok');
    }

    /**
     * Handle the `VRFY` SMTP command.
     *
     * @param string $data
     * @return void
     */
    protected function handleVerify(string $data)
    {
        $this->say('502 5.5.1 VRFY command is disabled');
    }

    /**
     * Handles the `HELO` and `EHLO` commands.
     *
     * @param string $helo
     * @return void
     */
    protected function handleHelo(string $helo)
    {
        if (! empty($this->mail->envelope->sender)) {
            $nmail = $this->app[Mail::class];
            $nmail->connection = $this->mail->connection;
            $nmail->envelope->helo = $this->mail->envelope->helo;
            $this->mail = $nmail;
        }
        $helo_parts = explode(' ', $helo, 2);
        $this->mail->envelope->helo = $helo_parts[1] ?? '';
        $this->handleWrapper(function () use ($helo) {
            $localAddr = $this->connection->getLocalAddress();
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['helo'] ?? [])
                ->thenReturn();
            if (Str::startsWith(strtolower($helo), 'helo')) {
                $this->say('250 ' . config('app.name', $localAddr));
            } else {
                $this->say('250-' . config('app.name', $localAddr))
                    ->say("250-ENHANCEDSTATUSCODES")
                    ->say("250-PIPELINING")
                    ->say("250 XFORWARD");
            }
        });
    }

    /**
     * Handles the `MAIL` command.
     *
     * @param string $envelope_from
     * @return void
     */
    protected function handleMailFrom(string $envelope_from)
    {
        if (empty($this->mail->envelope->helo)) {
            $this->say('503 5.5.1 Error: send HELO/EHLO first');

            return;
        }
        $from_parts = explode(': ', $envelope_from, 2);
        $this->mail->envelope->sender = trim($from_parts[1] ?? '', '<>');
        $this->handleWrapper(function () {
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['mail_from'] ?? [])
                ->thenReturn();
            $this->say('250 2.1.0 Ok');
        });
    }

    /**
     * Handles the `MAIL` command.
     *
     * @param string $envelope_to
     * @return void
     */
    protected function handleRcptTo(string $envelope_to)
    {
        if (empty($this->mail->envelope->sender)) {
            $this->say('503 5.5.1 Error: need MAIL command');

            return;
        }
        $to_parts = explode(': ', $envelope_to, 2);
        $this->mail->envelope->recipients[] = trim($to_parts[1] ?? '', '<>');
        $this->handleWrapper(function () {
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['rcpt_to'] ?? [])
                ->thenReturn();
            $this->say('250 2.1.5 Ok');
        });
    }

    protected function handleXForward(string $xforward)
    {
        if (! empty($this->mail->envelope->recipient)) {
            $this->say('503 Mail transaction in progress');

            return;
        }
        $xforward = preg_replace('/XFORWARD/i', '', $xforward);
        $commands = explode(' ', $xforward);
        $commands = array_map('trim', $commands);
        $attrs = [];
        foreach ($commands as $command) {
            [$attr, $val] = explode('=', $command);
            $attr = strtolower(trim($attr));
            $attrs[] = $attr;
            $val = trim($val);
            switch ($attr) {
                case 'helo':
                    $this->mail->envelope->helo = $val;
                    break;
                case 'addr':
                    $this->mail->connection->sender_ip = $val;
                    break;
                case 'proto':
                    $this->mail->connection->protocol = $val;
                    break;
                case 'name':
                    $this->mail->connection->sender_name = $val;
                    break;
                case 'ident':
                case 'source':
                case 'port':
                    break;
                default:
                    $this->say('501 Unrecognized parameter');

                    return;
            }
        }

        $returnOk = false;
        if (in_array(['proto', 'addr', 'name'], $attrs)) {
            $this->handleWrapper(function () use (&$returnOk) {
                $this->mail = (new Pipeline($this->app))
                    ->send($this->mail)
                    ->via('filter')
                    ->through($this->filters['connection'] ?? [])
                    ->thenReturn();
                $returnOk = true;
            });
            if (! $returnOk) {
                return;
            }
        }
        if (in_array('helo', $attrs)) {
            $this->handleWrapper(function () use (&$returnOk) {
                $this->mail = (new Pipeline($this->app))
                    ->send($this->mail)
                    ->via('filter')
                    ->through($this->filters['helo'] ?? [])
                    ->thenReturn();
                $returnOk = true;
            });
        }

        if ($returnOk) {
            $this->say('250 Ok');
        }
    }

    /**
     * Handle filling the data from the `DATA` command.
     *
     * @param string $data
     * @return void
     */
    protected function handleData(string $data)
    {
        $this->mail->raw .= $data;
        if (! $this->readingBody) { // reading headers
            if (empty(trim($data))) {
                if (! empty($this->currentLine)) {
                    $this->addHeader();
                }
                $this->readingBody = true;
                $this->currentLine = '';

                return;
            }
            if (preg_match('/^\s+\S/', $data)) {
                if ($this->app->config['relay.unfold_headers']) {
                    $this->currentLine .= "\n$data";
                } else {
                    $this->currentLine .= trim($data);
                }
            } else {
                $this->addHeader();
                $this->currentLine = trim($data);
            }

            return;
        }
        if (empty(trim($data))) {
            if (substr_count($this->currentLine, '.') > 0) {
                $this->readingBody = false;
                $this->readMode = false;
                $this->currentLine = '';
                $this->handleWrapper(function () {
                    $this->mail = (new Pipeline($this->app))
                        ->send($this->mail)
                        ->via('filter')
                        ->through($this->filters['data'] ?? [])
                        ->thenReturn();
                    $queueId = $this->sendToQueue();
                    $this->say("250 2.0.0 Ok: queued as $queueId");
                });
            }
            if (! empty($this->currentLine)) {
                $this->mail->bodyParts[] = $this->currentLine;
            }
            $this->currentLine = '';

            return;
        }

        $this->currentLine .= $data;
    }

    /**
     * Send the mail to the queue.
     *
     * @return string
     */
    protected function sendToQueue()
    {
        $queueId = md5(
            strtoupper(Str::random()) .
            Carbon::now()->toDateTime() .
            $this->mail->envelope->helo .
            $this->mail->connection->source_ip .
            $this->mail->envelope->sender
        );

        $this->mail->queue_id = $queueId;

        // First, let's check if queuing is disabled, and handle that upfront.
        if ($this->app->config['relay.queue_processor'] == 'none') {
            return $queueId;
        }

        // If queueing is enabled, we need to store the mail in the queue for
        //   later processing.
        $this->app->filesystem->put("queue/$queueId", $this->mail->raw);
        // @todo Add to queue using queue driver so that queues can be processed.

        return $queueId;
    }

    /**
     * A wrapper for handle methods to DRY up each handle method.
     * This will catch RejectException, DeferException, QuarantineException
     * and DropException throws and will act upon them as necessary.
     *
     * @param callable $handleMethod
     * @return void
     */
    private function handleWrapper(callable $handleMethod): void
    {
        try {
            $handleMethod();
        } catch (RejectException $reject) {
            $this->mail->setFinalDestination('reject');
            $this->say((string) $reject);
        } catch (DeferException $defer) {
            $this->mail->setFinalDestination('defer');
            $this->say((string) $defer);
        } catch (QuarantineException $quarantine) {
            $this->mail->setFinalDestination('quarantine');
            $this->say((string) $quarantine);
        } catch (DropException $drop) {
            if ($drop->getCode() >= 500) {
                $this->close((string) $drop);

                $this->mail = null;
            }
            $this->say((string) $drop);
            $this->mail->setFinalDestination('drop');
        }
    }

    /**
     * Add a header to the mail object. This is to DRY up some functionality.
     *
     * @return void
     */
    private function addHeader(): void
    {
        if (preg_match('/^(.+): (.+)$/', $this->currentLine, $matches)) {
            [, $header, $value] = $matches;
            $this->mail->headers[strtolower($header)][] = $value;
        }
    }
}
