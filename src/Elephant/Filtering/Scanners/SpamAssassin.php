<?php

namespace Elephant\Filtering\Scanners;

use Elephant\Contracts\Mail\Mail;
use Elephant\Filtering\Scanners\Scanner;
use Elephant\Contracts\Mail\Scanner as ScannerContract;
use Elephant\Helpers\Exceptions\SocketException;
use Elephant\Helpers\Socket;

class SpamAssassin extends Scanner
{
    /**
     * Length of the content to send to SpamAssassin in bytes.
     *
     * @var int $contentLength
     */
    protected $contentLength;

    /**
     * Construct a new SpamAssassin Scanner instance.
     *
     * @param Mail $mail The mail to be scanned.
     */
    public function __construct(Mail $mail)
    {
        parent::__construct($mail);
        $this->contentLength = strlen(str_replace("\n", "\r\n", $mail->getRaw()));
        $maxSize = config('scanners.spamassassin.max_size', 0);
        if ($maxSize > 0 && $this->contentLength > $maxSize) {
            $this->contentLength = $maxSize;
        }
        $this->results = [
            'total_score' => 0,
            'tests' => [],
            'autolearn' => false,
            'autolearn_force' => false,
            'version' => 'v0.0.0',
        ];
    }

    /** {@inheritdoc} */
    public function scan(): ?ScannerContract
    {
        $timeBegin = microtime(true);

        try {
            $socket = app(Socket::class)->setDsn(config('scanners.spamassassin.socket', 'ipv4://127.0.0.1:783'));
            $socket->setOption(['sec' => config('scanners.spamassassin.timeout', 10), 'usec' => 0]);

            $socket->send('HEADERS SPAMC/1.2');
            $socket->send("Content-length: {$this->contentLength}");
            if (isset($this->user) && ! empty($this->user)) {
                $socket->send("User: {$this->user}");
            }
            $socket->send('');

            // Move on from headers to message.
            $lines = explode("\n", $this->mail->getRaw());
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

        $this->mail->timings['spamassassin'] = microtime(true) - $timeBegin;

        return $this;
    }
}
