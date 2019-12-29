<?php

namespace Elephant\Filtering\Scanners;

use Elephant\Contracts\Mail\Mail;
use Elephant\Filtering\Scanners\Scanner;
use Elephant\Contracts\Mail\Scanner as ScannerContract;
use Elephant\Helpers\Exceptions\SocketException;
use Elephant\Helpers\Socket;
use Elephant\Mail\Mail as M;

class SpamAssassin extends Scanner
{
    /**
     * Length of the content to send to SpamAssassin in bytes.
     *
     * @var int $contentLength
     */
    protected $contentLength = 0;

    /**
     * Construct a new SpamAssassin Scanner instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->results = [
            'total_score' => 0,
            'tests' => [],
            'autolearn' => false,
            'autolearn_force' => false,
            'version' => 'v0.0.0',
        ];
    }

    /** {@inheritdoc} */
    public function scan(Mail $mail): ?ScannerContract
    {
        $this->contentLength = strlen(str_replace("\n", "\r\n", $mail->getRaw()));
        $maxSize = config('scanners.spamassassin.max_size', 0);
        if ($maxSize > 0 && $this->contentLength > $maxSize) {
            $this->contentLength = $maxSize;
        }

        $timeBegin = microtime(true);

        try {
            /** @var Socket $socket */
            $socket = app(Socket::class);
            $socket = $socket->setDsn(config('scanners.spamassassin.socket', 'ipv4://127.0.0.1:783'));
            $socket->setOption(['sec' => config('scanners.spamassassin.timeout', 10), 'usec' => 0]);

            $socket->send('HEADERS SPAMC/1.2');
            $socket->send("Content-length: {$this->contentLength}");
            if (isset($this->user) && ! empty($this->user)) {
                $socket->send("User: {$this->user}");
            }
            $socket->send('');

            // Move on from headers to message.
            $lines = explode("\n", $mail->getRaw());
            $bytes = 0;
            foreach ($lines as $line) {
                $bytes += strlen("$line\r\n");
                $socket->send($line);

                if ($bytes >= $this->contentLength) {
                    break;
                }
            }

            $currentLine = '';
            while ($line = $socket->read(4096)) {
                if (preg_match('/^\s+[a-z]+/i', $line)) {
                    $currentLine .= ' ' . trim($line);

                    continue;
                }
                $line = trim($line);
                if (preg_match('/^[a-z\-]+: /i', $line)) {
                    $regex = '/^X-Spam-Status: ' .
                        '(?:(?:Yes|No), score=[0-9\.\-]+ required=[0-9\.\-]+ )?' .
                        'tests=(.*) autolearn=(yes|no)(?: autolearn_force=(yes|no))?' .
                        ' version=([0-9\.]+)$/';
                    if (preg_match($regex, $currentLine, $matches)) {
                        [, $tests, $autolearn, $autolearn_force, $version] = $matches;
                        $tests = array_map(function ($test) {
                            $test = trim($test);
                            $score = 'undef';
                            if (strpos($test, '=') !== false) {
                                [$test, $score] = explode('=', $test);
                                $this->results['total_score'] += floatval($score);
                            }

                            return ['name' => $test, 'score' => (float) $score];
                        }, explode(',', $tests));
                        $this->results['tests'] = $tests;
                        $this->results['autolearn'] = strtolower($autolearn) === 'yes';
                        $this->results['autolearn_force'] = strtolower($autolearn_force) === 'yes';
                        $this->results['version'] = $version;

                        break;
                    }
                    $currentLine = $line;
                }
            }

            $socket->close();
        } catch (SocketException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        $mail->addExtraData(M::TIMINGS, 'spamassassin', microtime(true) - $timeBegin);

        return $this;
    }
}
