<?php

namespace Elephant\Helpers;

use React\EventLoop\Factory as Loop;
use React\Dns\RecordNotFoundException;
use React\Dns\Resolver\Factory as Resolver;

class Dns
{
    /** @var \React\Dns\Resolver\ResolverInterface|null $resolver */
    private static $resolver = null;

    /** @var \React\EventLoop\LoopInterface|null $loop */
    private static $loop = null;

    /**
     * Do multiple A record DNS lookups asynchronously.
     *
     * @param array<string> $lookups An array of A records to lookup.
     * @return array<string,string> A mapping of 'lookup' => 'ip.va.l.ue|nxdomain'.
     */
    public static function multiLookup(array $lookups): array
    {
        static::prepare();

        $results = [];
        $counter = count($lookups);

        $promises = [];

        foreach ($lookups as $lookup) {
            $promises[] = static::$resolver->resolve($lookup)
                ->then(function ($ip) use ($lookup, &$results, &$counter) {
                    $results[$lookup] = $ip;
                    $counter--;

                    static::$loop->addTimer(0.1, function () use ($counter) {
                        if ($counter <= 0) {
                            static::$loop->stop();
                        }
                    });
                }, function (RecordNotFoundException $e) use ($lookup, &$results, &$counter) {
                    $results[$lookup] = 'nxdomain';
                    $counter--;

                    static::$loop->addTimer(0.1, function () use ($counter) {
                        if ($counter <= 0) {
                            static::$loop->stop();
                        }
                    });
                });
        }

        static::$loop->addTimer(config('app.dns.timeout', 10), function () use ($promises) {
            foreach ($promises as $promise) {
                $promise->cancel();
            }
            static::$loop->stop();
        });

        static::$loop->run();

        return $results;
    }

    /**
     * Do a single A record lookup.
     *
     * @param string $lookup The record to lookup.
     * @return string The IP address (or nxdomain) of the $lookup.
     */
    public static function lookup(string $lookup): string
    {
        $responses = static::multiLookup([$lookup]);

        return $responses[$lookup];
    }

    private static function prepare(): void
    {
        if (is_null(static::$loop)) {
            static::$loop = Loop::create();
        }
        if (is_null(static::$resolver)) {
            $factory = new Resolver();
            static::$resolver = $factory->createCached(config('app.dns.server', '8.8.8.8'), static::$loop);
            unset($factory);
        }
    }
}
