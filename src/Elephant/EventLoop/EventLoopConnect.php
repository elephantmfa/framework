<?php

namespace Elephant\EventLoop;

use Elephant\Contracts\Mail;
use Elephant\Filtering\Exception\DeferException;
use Elephant\Filtering\Exception\DropException;
use Elephant\Filtering\Exception\QuarantineException;
use Elephant\Filtering\Exception\RejectException;
use Illuminate\Pipeline\Pipeline;
use React\Socket\ConnectionInterface;
use Illuminate\Contracts\Container\Container;

class EventLoopConnect
{
    protected $app;
    protected $filters;
    
    public function __construct(Container $app, array $filters)
    {
        $this->app = $app;
        $this->filters = $filters;
    }

    public function __invoke(ConnectionInterface $connection)
    {
        $mail = $this->app->make(Mail::class);
        $port = explode(':', $connection->getLocalAddress())[2];
        $mail->connection->received_port = $port;
        try {
            $mail = (new Pipeline($this->app))
                ->send($mail)
                ->via('filter')
                ->through($this->filters['connect'] ?? [])
                ->thenReturn();
            $connection->write('220 ' . $this->app->config['app.greeting_banner'] . "\r\n");
        } catch (RejectException $reject) {
            $mail->setFinalDestination('reject');
            $connection->write((string) $reject . "\r\n");
        } catch (DeferException $defer) {
            $mail->setFinalDestination('defer');
            $connection->write((string) $defer . "\r\n");
        } catch (QuarantineException $quarantine) {
            $mail->setFinalDestination('quarantine');
            $connection->write('220 ' . $this->app->config['app.greeting_banner'] . "\r\n");
        } catch (DropException $drop) {
            $connection->write((string) $drop . "\r\n");
            $connection->close();
            return null;
        }
        return $mail;
    }
}
