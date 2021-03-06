<?php

namespace Elephant\Mail\Jobs;

use Elephant\Mail\Mail as M;
use Elephant\Mail\Transport;
use Illuminate\Bus\Queueable;
use Elephant\Contracts\Mail\Mail;
use Illuminate\Pipeline\Pipeline;
use Elephant\Filtering\Actions\Drop;
use Elephant\Filtering\Actions\Defer;
use Elephant\Filtering\Actions\Reject;
use Elephant\Foundation\Bus\Dispatchable;
use Elephant\Filtering\Actions\Quarantine;

class QueueProcessJob
{
    use Dispatchable, Queueable;

    /** @var \Elephant\Contracts\Mail\Mail $mail */
    protected $mail;

    /** @var array<string,array> $filters */
    protected $filters;

    /**
     * Create a new job instance.
     *
     * @param \Elephant\Contracts\Mail\Mail $mail
     * @param array<string,array>           $filters
     * @param callable                      $finalCb
     * @return void
     */
    public function __construct(Mail $mail, array $filters, $finalCb)
    {
        $this->mail = $mail;
        $this->filters = $filters;
        $this->finalCb = $finalCb;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $time = microtime(true);
        $this->handleWrapper(function () {
            $this->mail = (new Pipeline(app()))
                ->send($this->mail)
                ->via('filter')
                ->through($this->filters['queued'] ?? [])
                ->thenReturn();
        });
        $this->mail->addExtraData(M::TIMINGS, 'data', microtime(true) - $time);

        $this->mail = call_user_func($this->finalCb, $this->mail);

        Transport::send($this->mail);

        /** @var \Illuminate\Filesystem\FilesystemManager $filesystem */
        $filesystem = app('filesystem');
        $filesystem->disk('tmp')->delete("queue/{$this->mail->getQueueId()}");
    }

    /**
     * A wrapper for handle methods to DRY up each handle method.
     * This will catch Reject, Defer, Quarantine
     * and Drop throws and will act upon them as necessary.
     *
     * @param callable $handleMethod
     *
     * @return void
     */
    private function handleWrapper(callable $handleMethod): void
    {
        try {
            $handleMethod();
        } catch (Reject $reject) {
            $this->mail->setFinalDestination('reject');
        } catch (Defer $defer) {
            $this->mail->setFinalDestination('defer');
        } catch (Quarantine $quarantine) {
            $this->mail->setFinalDestination('quarantine');
        } catch (Drop $drop) {
            $this->mail->setFinalDestination('drop');
        }
    }
}
