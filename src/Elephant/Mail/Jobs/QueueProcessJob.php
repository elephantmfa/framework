<?php

namespace Elephant\Mail\Jobs;

use Elepahnt\Mail\Transport;
use Elephant\Contracts\Mail\Mail;
use Elephant\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;

class QueueProcessJob
{
    use Dispatchable, Queueable;

    protected $mail;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Mail $mail, array $filters)
    {
        $this->mail = $mail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->handleWrapper(function () {
            $this->mail = (new Pipeline($this->app))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['queued'] ?? [])
                ->thenReturn();
        });

        Transport::send($this->mail);
    }

    /**
     * A wrapper for handle methods to DRY up each handle method.
     * This will catch RejectException, DeferException, QuarantineException
     * and DropException throws and will act upon them as necessary.
     *
     * @param callable $handleMethod
     *
     * @return void
     */
    private function handleWrapper(callable $handleMethod): void
    {
        try {
            $handleMethod();
        } catch (RejectException $reject) {
            $this->mail->setFinalDestination('reject');
        } catch (DeferException $defer) {
            $this->mail->setFinalDestination('defer');
        } catch (QuarantineException $quarantine) {
            $this->mail->setFinalDestination('quarantine');
        } catch (DropException $drop) {
            $this->mail->setFinalDestination('drop');
        }
    }
}
