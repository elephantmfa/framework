<?php

namespace Elephant\EventLoop;

use Elephant\Contracts\Mail;
use Elephant\EventLoop\Traits\CommunicateTrait;
use Elephant\Filtering\Exception\DeferException;
use Elephant\Filtering\Exception\DropException;
use Elephant\Filtering\Exception\QuarantineException;
use Elephant\Filtering\Exception\RejectException;
use Illuminate\Pipeline\Pipeline;
use React\Socket\ConnectionInterface;
use Illuminate\Contracts\Container\Container;

class EventLoopConnect
{
    use CommunicateTrait;
    
    protected $app;
    protected $filters;
    
    public function __construct(Container $app, array $filters)
    {
        $this->app = $app;
        $this->filters = $filters;
    }

    public function __invoke(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $mail = $this->app->make(Mail::class);
        $port = explode(':', $connection->getLocalAddress())[2];
        if (preg_match(';^tcp://(.+):\d+$;', $connection->getRemoteAddress(), $matches)) {
            $mail->connection->sender_ip = $matches[1];
        }
        $mail->connection->received_port = $port;
        $mail->connection->protocol = 'SMTP';
        $mail->connection->sender_name = '[UNKNOWN]';
        try {
            $mail = (new Pipeline($this->app))
                ->send($mail)
                ->via('filter')
                ->through($this->filters['connect'] ?? [])
                ->thenReturn();
            $this->say('220 ' . $this->app->config['relay.greeting_banner']);
        } catch (RejectException $reject) {
            $mail->setFinalDestination('reject');
            $this->say((string) $reject);
        } catch (DeferException $defer) {
            $mail->setFinalDestination('defer');
            $this->say((string) $defer);
        } catch (QuarantineException $quarantine) {
            $mail->setFinalDestination('quarantine');
            $this->say((string) $quarantine);
        } catch (DropException $drop) {
            if ($drop->getCode() >= 500) {
                $this->close((string) $drop);
                return null;
            }
            $this->say((string) $drop);
            $mail->setFinalDestination('drop');
        }
        $this->app->loop->addTimer($this->app->config['relay.timeout'], function () {
            $this->close('421 4.4.2 ' . $this->app->config['app.name'] . ' Error: timeout exceeded');
        });
        return $mail;
    }
}
