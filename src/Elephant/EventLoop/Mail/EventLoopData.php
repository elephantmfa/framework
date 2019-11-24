<?php

namespace Elephant\EventLoop\Mail;

use Illuminate\Support\Str;
use Elephant\Contracts\Mail\Mail;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Contracts\Container\Container;
use Elephant\EventLoop\Traits\CommunicateTrait;
use Elephant\Mail\Jobs\QueueJob;
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
     * Whether or not the mail messages is likely ending.
     *
     * @var boolean
     */
    protected $endingMail = false;

    /**
     * Builds the event loop data invokable class.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     * @param \React\Socket\ConnectionInterface $connection
     * @param \Elephant\Contracts\Mail\Mail $mail
     * @param array $filters
     * @return void
     */
    public function __construct(Container $app, array $filters)
    {
        $this->app = $app;
        $this->filters = $filters;
        $this->mail = null;
        $this->readMode = false;
        $this->connection = $this->app->stdout;
    }

    /**
     * Run the event loop step.
     *
     * @param string $data
     * @return void
     */
    public function __invoke($data)
    {
        if (substr_count($data, "\n") > 1) {
            $chunks = explode("\n", $data);

            $this->mail->supplementalData['pipelining_in_use'] = true;

            foreach ($chunks as $chunk) {
                $chunk = rtrim($chunk, "\r");
                $this->handle("$chunk\r\n");
            }

            return;
        }

        $this->handle($data);
    }

    /**
     * Handle the processing.
     *
     * @param string $data
     * @return void
     */
    protected function handle(string $data)
    {
        if ($this->readMode) {
            $data = str_replace("\r\n", "\n", $data);
            $this->handleData($data);

            return;
        }
        $data = trim($data);
        $lcData = strtolower($data);
        if (Str::startsWith($lcData, 'connect')) {
            $this->handleConnect($data);
        } elseif (Str::startsWith($lcData, ['helo', 'ehlo'])) {
            $this->handleHelo($data);
        } elseif (Str::startsWith($lcData, 'mail from')) {
            $this->handleMailFrom($data);
        } elseif (Str::startsWith($lcData, 'rcpt to')) {
            $this->handleRcptTo($data);
        } elseif (Str::startsWith($lcData, 'xforward')) {
            $this->handleXForward($data);
        } elseif (Str::startsWith($lcData, 'data')) {
            if ($lcData !== 'data') {
                $this->say('501 5.5.4 Syntax: DATA');

                return;
            }
            if (count($this->mail->getRecipients()) < 1) {
                $this->say('503 5.5.1 Error: need RCPT command');

                return;
            }
            $this->say('354 End data with <CR><LF>.<CR><LF>');
            $this->readMode = true;
        } elseif (Str::startsWith($lcData, 'quit')) {
            $this->handleQuit($data);
        } elseif (Str::startsWith($lcData, 'rset')) {
            $this->handleReset($data);
        } elseif (Str::startsWith($lcData, 'noop')) {
            $this->handleNoop($data);
        } elseif (Str::startsWith($lcData, 'vrfy')) {
            $this->handleVerify($data);
        } elseif (Str::startsWith($lcData, 'starttls')) {
            $this->handleTls($data);
        } elseif (!empty($data)) {
            $this->handleUnknownCommand($data);
        }
    }

    /**
     * Handle the connection of an SMTP client.
     *
     * @param string $data
     * @return void
     */
    protected function handleConnect(string $data)
    {
        $this->mail = $this->app->make(Mail::class);
        if (preg_match(';CONNECT remote:\w+://(.+):\d+ local:\w+://.+(.+);', $data, $matches)) {
            [, $remoteIp, $localPort] = $matches;
            $this->mail->setSenderIp($remoteIp);
            $this->mail->getConnection()->receivedPort = $localPort;
            $this->mail->setProtocol('ESMTP');
            $this->mail->setSenderName(gethostbyaddr($remoteIp) ?: '[UNKNOWN]');
        }

        $this->handleWrapper(function () {
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['connect'] ?? [])
                ->thenReturn();
            $this->say('220 ' . $this->app->config['relay.greeting_banner'] ?? 'Welcome to ElephantMFA ESMTP');
        });

        $this->app->loop->addTimer($this->app->config['relay.timeout'], function () {
            $this->close('421 4.4.2 ' . $this->app->config['app.name'] . ' Error: timeout exceeded');
        });
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
     * Handle the `STARTTLS` SMTP command.
     *
     * @param string $data
     * @return void
     */
    protected function handleTls(string $data)
    {
        $this->say('502 5.5.1 STARTTLS not yet implemented');
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
        $nmail->setConnection($this->mail->getConnection());
        $nmail->setHelo($this->mail->getHelo() ?? '');
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
        $time = microtime(true);
        if (! empty($this->mail->getSender())) {
            $nmail = $this->app[Mail::class];
            $nmail->setConnection($this->mail->getConnection());
            $nmail->setHelo($this->mail->getHelo() ?? '');
            $this->mail = $nmail;
        }
        $helo_parts = explode(' ', $helo, 2);
        $this->mail->setHelo($helo_parts[1] ?? '');
        $this->handleWrapper(function () use ($helo) {
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['helo'] ?? [])
                ->thenReturn();
            if (Str::startsWith(strtolower($helo), 'helo')) {
                $this->say('250 ' . config('app.name', 'ElephantMFA ESMTP'));
            } else {
                $this->say('250-' . config('app.name', 'ElephantMFA ESMTP'))
                    ->say("250-ENHANCEDSTATUSCODES")
                    ->say("250-PIPELINING")
                    ->say('250-SMTPUTF8')
                    ->say('250-8BITMIME')
                    ->say("250 XFORWARD");
            }
        });
        $this->mail->timings['helo'] = microtime(true) - $time;
    }

    /**
     * Handles the `MAIL` command.
     *
     * @param string $envelope_from
     * @return void
     */
    protected function handleMailFrom(string $envelope_from)
    {
        $time = microtime(true);
        if (empty($this->mail->getHelo())) {
            $this->say('503 5.5.1 Error: send HELO/EHLO first');

            return;
        }

        if (strpos($envelope_from, ':') === false) {
            $this->say('501 5.5.4 Syntax: MAIL FROM:<address>');

            return;
        }

        $from_parts = explode(': ', $envelope_from, 2);
        $this->mail->setSender($from_parts[1] ?? '');
        $this->handleWrapper(function () {
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['mail_from'] ?? [])
                ->thenReturn();
            $this->say('250 2.1.0 Ok');
        });
        $this->mail->timings['mail_from'] = microtime(true) - $time;
    }

    /**
     * Handles the `MAIL` command.
     *
     * @param string $envelope_to
     * @return void
     */
    protected function handleRcptTo(string $envelope_to)
    {
        $time = microtime(true);
        if (empty($this->mail->getSender())) {
            $this->say('503 5.5.1 Error: need MAIL command');

            return;
        }
        
        if (strpos($envelope_to, ':') === false) {
            $this->say('501 5.5.4 Syntax: RCPT TO:<address>');

            return;
        }

        $to_parts = explode(': ', $envelope_to, 2);
        $this->mail->addRecipient($to_parts[1] ?? '');
        $this->handleWrapper(function () {
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['rcpt_to'] ?? [])
                ->thenReturn();
            $this->say('250 2.1.5 Ok');
        });
        $this->mail->timings['rcpt_to'] = microtime(true) - $time;
    }

    protected function handleXForward(string $xforward)
    {
        $time = microtime(true);
        if (count($this->mail->getRecipients()) < 1) {
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
                    $this->mail->setHelo($val);
                    break;
                case 'addr':
                    $this->mail->setSenderIp($val);
                    break;
                case 'proto':
                    $this->mail->setProtocol($val);
                    break;
                case 'name':
                    $this->mail->setSenderName($val);
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
                $this->mail->timings['xforward'] = microtime(true) - $time;

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
        $this->mail->timings['xforward'] = microtime(true) - $time;
    }

    /**
     * Handle filling the data from the `DATA` command.
     *
     * @param string $data
     * @return void
     */
    protected function handleData(string $data)
    {
        $this->mail->appendToRaw($data);
        if (! $this->readingBody) {
            if (Str::startsWith($data, '--' . $this->mail->getMimeBoundary()) ||
                empty(trim($data))
            ) {
                if (!empty($this->currentLine)) {
                    $this->addHeader();
                }
                $this->readingBody = true;
                $this->currentLine = '';

                return;
            }

            if (preg_match('/^\s+\S/', $data)) {
                if (! $this->app->config['relay.unfold_headers']) {
                    $this->currentLine .= "\n" . trim($data, "\r\n");
                } else {
                    if (Str::endsWith($this->currentLine, ';')) {
                        $this->currentLine .= ' ';
                    }
                    $this->currentLine .= trim($data);
                }
            } else {
                if (! empty($this->currentLine)) {
                    $this->addHeader();
                }
                $this->currentLine = trim($data);
            }

            return;
        }

        if (Str::startsWith($data, '--' . $this->mail->getMimeBoundary()) && ! Str::endsWith($data, '--')) {
            $this->mail->attachRaw($this->currentLine);
            $this->currentLine = '';
        }

        if (empty($this->mail->getMimeBoundary()) && substr_count($this->currentLine, '.') > 0) {
            $this->endingMail = true;
        } elseif (trim($data) == "--{$this->mail->getMimeBoundary()}--") {
            $this->endingMail = true;
        }
        if ($this->endingMail && empty(trim($data))) {
            if (substr_count($this->currentLine, "\n.\n") > 0) {
                $time = microtime(true);
                $this->readingBody = false;
                $this->readMode = false;
                $this->endingMail = false;
                $this->currentLine = '';
                $this->handleWrapper(function () {
                    $this->mail = (new Pipeline($this->app))
                        ->send($this->mail)
                        ->via('filter')
                        ->through($this->filters['data'] ?? [])
                        ->thenReturn();
                    $queueId = $this->generateQueueId();
                    $this->say("250 2.0.0 Ok: queued as $queueId");
                });
                $this->mail->timings['data'] = microtime(true) - $time;
                info($this->mail->toJson());

                $queueProcess = $this->app->config['relay.queue_processor'] ?? 'process';
                if ($queueProcess == 'process') {
                    QueueJob::dispatchNow($this->mail);
                } else {
                    QueueJob::dispatch($this->mail);
                }

                return;
            }
            if (!empty($this->currentLine)) {
                $this->mail->attachRaw($this->currentLine);
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
    protected function generateQueueId()
    {
        $queueId = sha1(
            strtoupper(Str::random()) .
            Carbon::now()->toString() .
            $this->mail->getHelo() .
            $this->mail->getSenderIp() .
            $this->mail->getSender()
        );

        $this->mail->setQueueId($queueId);

        // First, let's check if queuing is disabled, and handle that upfront.
        if ($this->app->config['relay.queue_processor'] == 'none') {
            return $queueId;
        }

        // If queueing is enabled, we need to store the mail in the queue for
        //   later processing.
        $this->app->filesystem->disk('tmp')->put("queue/$queueId", $this->mail->getRaw());

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
        if (preg_match('/^(.+): (.+)$/s', $this->currentLine, $matches)) {
            [, $header, $value] = $matches;
            if (strtolower($header) == 'content-type') {
                if (preg_match('/boundary=["\'](.*)["\']/', $value, $matches)) {
                    $this->mail->setMimeBoundary($matches[1]);
                }
            }

            $this->mail->addHeader(strtolower($header), $value);
        }
    }
}
