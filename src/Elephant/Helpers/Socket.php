<?php

namespace Elephant\Helpers;

use Elephant\Helpers\Exceptions\SocketException;

class Socket
{
    /** @var resource|null $socket */
    protected $socket;

    /** @var array $dsnData */
    protected $dsnData = [];

    /**
     * @param string $dsn ex. ipv4://127.0.0.1:10024 or unix://path/to/socket.sock
     * @uses self::connect()
     * @uses self::breakDsn
     * @throws SocketException
     */
    public function __construct(string $dsn = '')
    {
        if (! empty($dsn)) {
            $this->dsnData = self::breakDsn($dsn);
            $this->connect();
        }
    }

    /**
     * Set the DSN and connect. only works if not already connected.
     *
     * @param string $dsn ex. ipv4://127.0.0.1:10024 or unix://path/to/socket.sock
     * @return self
     * @throws SocketException
     */
    public function setDsn(string $dsn): self
    {
        if (empty($this->dsnData)) {
            $this->dsnData = self::breakDsn($dsn);
            $this->connect();
        }

        return $this;
    }

    public function __destruct()
    {
        if (! is_null($this->socket)) {
            socket_close($this->socket);
            unset($this->socket, $this->dsnData);
        }
    }

    /**
     * Send data to socket.
     *
     * @param string $val What to send.
     * @return int
     *
     * @throws SocketException Unable to send to socket.
     */
    public function send(string $val): int
    {
        if (is_null($this->socket)) {
            throw new SocketException('Unable to validate socket.');
        }

        $r = socket_send($this->socket, $val, strlen($val), 0);

        if ($r === false) {
            throw new SocketException("Unable to send [$val] to socket.");
        }

        return $r;
    }

    /**
     * Alias of send().
     *
     * @see self::send()
     * @uses self::send()
     *
     * @param string $val What to send.
     * @return int
     *
     * @throws SocketException Unable to send to socket.
     */
    public function write(string $val): int
    {
        return $this->send($val);
    }

    /**
     * Read from socket.
     *
     * @param int $bytesToRead Default: 8192
     * @param int $readType Default: PHP_NORMAL_READ
     *  Alternative: PHP_BINARY_READ
     * @return string
     *
     * @throws SocketException Unable to read from socket.
     */
    public function read(int $bytesToRead = 8192, int $readType = PHP_NORMAL_READ): string
    {
        if (is_null($this->socket)) {
            throw new SocketException('Unable to validate socket.');
        }

        $ret = @socket_read($this->socket, $bytesToRead, $readType);

        if ($ret === false) {
            throw new SocketException("Unable to read from socket.");
        }

        return rtrim($ret);
    }

    /**
     * Set an option for the socket.
     *
     * @param mixed $option
     * @return void
     * @throws SocketException Unable to set option.
     */
    public function setOption($option): void
    {
        if (is_null($this->socket)) {
            throw new SocketException('Unable to validate socket.');
        }

        $success = socket_set_option(
            $this->socket,
            SOL_SOCKET,
            SO_SNDTIMEO,
            $option
        );

        if (! $success) {
            throw new SocketException("Unable to set option.");
        }
    }

    /**
     * Closes the connection.
     *
     * @return void
     * @uses self::__destruct()
     */
    public function close(): void
    {
        $this->__destruct();
    }

    public function getLastError(): string
    {
        if (is_null($this->socket)) {
            throw new SocketException('Unable to validate socket.');
        }

        return socket_strerror(socket_last_error($this->socket));
    }

    /**
     * Makes a connection to the socket.
     *
     * @return void
     * @throws SocketException On invalid socket type or when unable to
     *  create/connect to socket.
     */
    protected function connect(): void
    {
        [$path, $port, $proto, $type] = $this->dsnData;

        if (is_null($type)) {
            throw new SocketException("Invalid socket type: $type");
        }

        $this->socket = socket_create($type, SOCK_STREAM, $proto);
        if (! $this->socket) {
            $error = 'Unable to create socket!';
            if (config('app.debug', false)) {
                $error .= ' ' . socket_strerror(socket_last_error($this->socket));
            }

            throw new SocketException($error);
        }
        if (! @socket_connect($this->socket, $path, $port)) {
            $error = 'Unable to connect to socket!';
            if (config('app.debug', false)) {
                $error .= ' ' . socket_strerror(socket_last_error($this->socket));
            }

            throw new SocketException($error);
        }
    }

    /**
     * Break a DSN (ex. ipv4://127.0.0.1:10024) into the parts.
     *
     * @param string $dsn ex. ipv4://127.0.0.1:10024 or unix://path/to/socket.sock
     * @return array [$path, $port, $proto, $type]
     * @throws SocketException On invalid dsn.
     */
    public static function breakDsn(string $dsn): array
    {
        [$type, $path] = explode('://', $dsn, 2);
        if (empty($path) || empty($type)) {
            throw new SocketException("DSN [$dsn] is invalid.");
        }

        $type = strtolower($type);
        if ($type === 'ipv4') {
            $type = AF_INET;
        } elseif ($type === 'ipv6') {
            $type = AF_INET6;
        } elseif ($type === 'unix') {
            $type = AF_UNIX;
        } else {
            $type = null;
        }

        $proto = SOL_TCP;
        if ($type == AF_INET6) {
            [$path, $port] = explode(']:', $path, 2);
            $path = trim($path, '[]');
        } elseif ($type == AF_INET) {
            [$path, $port] = explode(':', $path, 2);
        } else {
            $port = 0;
            $proto = 0;
        }

        return [$path, $port, $proto, $type];
    }
}
