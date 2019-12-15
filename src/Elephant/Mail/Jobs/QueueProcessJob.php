<?php

namespace Elephant\Mail\Jobs;

use Elephant\Mail\Transport;
use Illuminate\Bus\Queueable;
use Elephant\Contracts\Mail\Mail;
use Illuminate\Pipeline\Pipeline;
use Elephant\Foundation\Bus\Dispatchable;

class QueueProcessJob
{
    use Dispatchable, Queueable;

    /** @var \Elephant\Contracts\Mail\Mail $mail */
    protected $mail;

    /** @var array $filters */
    protected $filers;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Mail $mail, array $filters)
    {
        $this->mail = $mail;
        $this->filters = $filters;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->handleWrapper(function () {
            $this->mail = (new Pipeline(app()))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['queued'] ?? [])
                ->thenReturn();
        });

        Transport::send($this->mail);

        app('filesystem')->disk('tmp')->delete("queue/{$this->mail->getQueueId()}");
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
