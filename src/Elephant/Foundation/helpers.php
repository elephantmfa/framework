<?php

use Illuminate\Support\Carbon;
use Illuminate\Container\Container;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string|null  $abstract
     * @param  array   $parameters
     * @return mixed|\Illuminate\Contracts\Foundation\Application
     */
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed|\Illuminate\Config\Repository
     */
    function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('config');
        }

        if (is_array($key)) {
            return app('config')->set($key);
        }

        return app('config')->get($key, $default);
    }
}

if (! function_exists('info')) {
    /**
     * Logs out the $logMessage.
     *
     * @param string $logMessage
     * @return void
     */
    function info(string $logMessage)
    {
        echo '[' . Carbon::now() . "] $logMessage\n";
    }
}

if (! function_exists('dd')) {
    /**
     * Die and var_export the data.
     *
     * @param mixed $dump
     * @return void
     */
    function dd($dump)
    {
        die(var_export($dump, true) . "\n");
    }
}

if (! function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '')
    {
        return app('path.storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (! function_exists('database_path')) {
    /**
     * Get the database path.
     *
     * @param  string  $path
     * @return string
     */
    function database_path($path = '')
    {
        return app()->databasePath($path);
    }
}

if (! function_exists('validate_ip')) {
    /**
     * Verify that an IP or IP:port are valid.
     *
     * @param string $ip
     * @param bool $ipv6
     * @return boolean
     */
    function validate_ip(string $ip, bool $ipv6 = false): bool
    {
        if ($ipv6) {
            $match = preg_match('/(\[[a-fA-F0-9:]{3,39}\])(:\d+)/', $ip, $matches);
            if ($match === false) {
                return false;
            }
            if (count($matches) > 0) {
                [, $ip, $port] = $matches;
                if (!is_int($port)) {
                    return false;
                }
            }

            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }
        if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?/', $ip) === false) {
            return false;
        }
        if (substr_count($ip, ':') == 1) {
            // In this case, we are likely an IPv4 address with a port.
            [$ip, $port] = explode(':', $ip, 2);
            if (! is_int($port)) {
                return false;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP);
    }
}

if (! function_exists('fold_header')) {
    /**
     * Folds a header value to be no more than 78 characters long.
     *
     * @param string $headerVal
     * @param int $numSpaces = 4
     * @return string
     */
    function fold_header(string $headerVal, int $numSpaces = 4): string
    {
        if (preg_match('/^(.{68,78})\s+(.+)$/', $headerVal, $matches) !== false) {
            [, $p1, $p2] = $matches;
            $headerVal = "{$p1}\n";
            for ($i=0; $i < $numSpaces; $i++) {
                $headerVal .= ' ';
            }
            if (strlen($p2) > 78) {
                $p2 = fold_header($p2, $numSpaces);
            }
            $headerVal .= $p2;
        }
        return $headerVal;
    }
}
