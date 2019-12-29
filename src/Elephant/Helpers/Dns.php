<?php

namespace Elephant\Helpers;

use Elephant\Helpers\Exceptions\NoSpfException;
use Elephant\Helpers\Matchers\Regex;
use Illuminate\Support\Collection;
use React\Dns\Model\Message;
use React\EventLoop\Factory as Loop;
use React\Dns\RecordNotFoundException;
use React\Dns\Resolver\Factory as Resolver;

class Dns
{
    const ANY = Message::TYPE_ANY;
    const A = Message::TYPE_A;
    const AAAA = Message::TYPE_AAAA;
    const PTR = Message::TYPE_PTR;
    const TXT = Message::TYPE_TXT;
    const MX = Message::TYPE_MX;
    const CNAME = Message::TYPE_CNAME;

    const SRV = Message::TYPE_SRV;
    const SSHFP = Message::TYPE_SSHFP;
    const SOA = Message::TYPE_SOA;
    const NS = Message::TYPE_NS;

    /** @var \React\Dns\Resolver\ResolverInterface|null $resolver */
    private static $resolver = null;

    /** @var \React\EventLoop\LoopInterface|null $loop */
    private static $loop = null;

    /**
     * A simple A record lookup. Will only return one result.
     *
     * @param  string $lookup The DNS record to lookup.
     * @return string         The first IP address returned.
     */
    public static function a(string $lookup): string
    {
        $response = static::lookup($lookup, static::A);

        return $response[0];
    }

    /**
     * A simple MX record lookup. Will only return one result.
     *
     * @param  string $lookup           The DNS record to lookup.
     * @return array<string,int|string> The first record returned.
     *  Contains `priority` and `target`.
     */
    public static function mx(string $lookup): array
    {
        $response = static::lookup($lookup, static::MX);

        return $response[0];
    }

    /**
     * A simple PTR record lookup. Will only return one result.
     *
     * @param  string $lookup The DNS record to lookup.
     * @return string         The first record returned.
     */
    public static function ptr(string $lookup): string
    {
        $response = static::lookup($lookup, static::PTR);

        return $response[0];
    }

    /**
     * A simple TXT record lookup. Will only return one result.
     *
     * @param  string $lookup The DNS record to lookup.
     * @return string         The first record returned.
     */
    public static function txt(string $lookup): string
    {
        $response = static::lookup($lookup, static::TXT);

        return implode('', $response[0]);
    }

    /**
     * A recursive lookup of a CNAME record.
     *
     * @param  string $lookup     The DNS record to lookup.
     * @param  int    $resultType The final type that the CNAME lookup should reveal.
     *  Generally this will be either an A record or TXT record.
     * @return string             The first record returned.
     */
    public static function cname(string $lookup, int $resultType = self::A): string
    {
        $response = static::lookup($lookup, static::CNAME);

        $ret = $response[0];

        $resp2 = static::lookup($ret, $resultType);
        if (Regex::match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $resp2[0])) {
            return $resp2[0];
        }

        return static::cname($ret);
    }

    /**
     * Get the SPF record of a domain.
     *
     * @param  string $domain The domain to get the SPF record for.
     * @return string         The SPF record.
     * @throws NoSpfException If the domain doesn't have an SPF record.
     */
    public static function spf(string $domain): string
    {
        $responses = static::lookup($domain, static::TXT);

        foreach ($responses as $response) {
            if (is_array($response)) {
                $response = implode('', $response);
            }
            $response = strtolower($response);
            if (Regex::match('/^v=spf1/i', $response)) {
                return $response;
            }
        }

        throw new NoSpfException($domain);
    }


    /**
     * Check if an IP address matches an SPF record.
     *
     * @todo
     * @param  string $ip     The IP address to check.
     * @param  string $domain The domain to get the SPF record for.
     * @return bool           If the IP is in the SPF record.
     * @throws NoSpfException If the domain doesn't have an SPF record.
     */
    public static function ipInSpf(string $ip, string $spfDomain): bool
    {
        return false;
    }

    /**
     * Get the DMARC record of a domain.
     *
     * If no DMARC record is found, it will lookup the DMARC record for the root
     *  domain, if the lookup was for a subdomain.
     *
     * @param  string $domain The domain to get the DMARC record for.
     * @return string         The DMARC record.
     * @throws NoDmarcException If the domain doesn't have an DMARC record.
     */
    public static function dmarc(string $domain): array
    {
        $responses = static::lookup("_dmarc.{$domain}", static::TXT);

        foreach ($responses as $response) {
            if (is_array($response)) {
                $response = implode('', $response);
            }

            if (Regex::match('/^v=dmarc1/i', $response)) {
                return Collection::make(explode(';', $response))
                    ->filter(function ($rr) {
                        return ! Regex::match('/^v=/i', $rr);
                    })->map(function ($rr) {
                        [$key, $val] = explode('=', $rr);
                        $key = trim($key);
                        $val = trim($val);

                        return [$key => $val];
                    })->collapse()
                    ->toArray();
            }
        }

        $rootDomain = static::getRootDomain($domain);

        if ($rootDomain !== $domain) {
            return static::dmarc($rootDomain);
        }

        throw new NoSpfException($domain);
    }

    /**
     * Do a single DNS record lookup.
     *
     * @param string $lookup The record to lookup.
     * @param int    $type   DNS Record type, values can be found in the constants.
     * @return array         The responses of the DNS lookup.
     */
    public static function lookup(string $lookup, int $type = self::A): array
    {
        $responses = static::multiLookup([$lookup], $type);

        return $responses[$lookup];
    }

    /**
     * Do multiple DNS record lookups asynchronously.
     *
     * @param array<string> $lookups An array of DNS records to lookup.
     * @param int           $type    DNS Record type, values can be found in the constants.
     * @return array<string,array>  A mapping of 'lookup' => ['ip.va.l.ue|nxdomain'].
     */
    public static function multiLookup(array $lookups, int $type = self::A): array
    {
        static::prepare();

        $results = [];
        $counter = count($lookups);

        $promises = [];

        foreach ($lookups as $lookup) {
            $lookupKey = $lookup;
            if ($type === static::PTR) {
                if (strpos($lookup, ':') !== false) { // IPv6
                    $lookup = str_replace(':', '', static::expand($lookup));
                    $lookup = implode('.', array_reverse(str_split($lookup)));
                } else {
                    $lookup = implode('.', array_reverse(explode('.', $lookup)));
                }
                $lookup .= ".in-addr.arpa";
            }
            $promises[] = static::$resolver->resolveAll($lookup, $type)
                ->then(
                    function ($response) use ($lookupKey, &$results, &$counter) {
                        $results[$lookupKey] = $response;
                        $counter--;

                        static::$loop->addTimer(
                            0.01,
                            function () use ($counter) {
                                if ($counter <= 0) {
                                    static::$loop->stop();
                                }
                            }
                        );
                    },
                    function (RecordNotFoundException $e) use ($lookupKey, &$results, &$counter) {
                        $results[$lookupKey] = ['nxdomain'];
                        $counter--;

                        static::$loop->addTimer(
                            0.01,
                            function () use ($counter) {
                                if ($counter <= 0) {
                                    static::$loop->stop();
                                }
                            }
                        );
                    }
                );
        }

        static::$loop->addTimer(
            config('app.dns.timeout', 10),
            function () use ($promises) {
                foreach ($promises as $promise) {
                    $promise->cancel();
                }
                static::$loop->stop();
            }
        );

        static::$loop->run();

        return $results;
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

    private static function expand(string $ip): string
    {
        $hex = unpack("H*hex", inet_pton($ip));
        $ip = substr(preg_replace("/([A-f0-9]{4})/", "$1:", $hex['hex']), 0, -1);

        return $ip;
    }

    private static function getRootDomain(string $domain): string
    {
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $m)) {
            return $m['domain'];
        }
    }
}
