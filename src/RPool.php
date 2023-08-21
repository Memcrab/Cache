<?php
declare(strict_types=1);

namespace Memcrab\Cache;

use Monolog\Logger;
use OpenSwoole\Core\Coroutine\Client\RedisConfig;

class RPool extends Pool
{
    private static self $instance;
    private RedisConfig $RedisConfig;

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }

    public static function obj(): self
    {
        return self::$instance;
    }

    public static function declareConnection(
        string $host,
        int    $port,
        string $password,
        int    $database,
        int    $waitTimeout,
        int    $waitTimeoutPool,
        Logger $ErrorHandler,
        int    $capacity = self::DEFAULT_CAPACITY,
    ): void
    {
        self::$instance = new self($capacity);
        self::$instance->RedisConfig = (new RedisConfig())
            ->withHost($host)
            ->withPort($port)
            ->withAuth($password)
            ->withDbIndex($database)
            ->withTimeout($waitTimeout);
        self::$instance->setWaitTimeoutPool($waitTimeoutPool);
        self::$instance->setErrorHandler($ErrorHandler);
    }

    protected function error(\Exception $e): void
    {
        $this->ErrorHandler->error('RPool Exception: ' . $e);
    }

    protected function connect(): Redis|bool
    {
        try {
            return (new Redis($this->RedisConfig, $this->ErrorHandler));
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }

    protected function disconnect($connection): bool
    {
        try {
            $connection->close();
            return true;
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }

    protected function checkConnectionForErrors($connection): bool
    {
        return !$connection->isConnected();
    }

    public function __destruct()
    {
        while (true) {
            if (!$this->isEmpty()) {
                $connection = $this->pop($this->waitTimeoutPool);
                $this->disconnect($connection);
            } else {
                break;
            }
        }
        $this->close();
    }
}