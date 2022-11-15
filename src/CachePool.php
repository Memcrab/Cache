<?php

declare(strict_types=1);

namespace Memcrab\Cache;

use Memcrab\Log\Log;
use Redis;
use RedisException;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;

class CachePool
{
    protected static CachePool $instance;
    private RedisPool $RedisPool;
    private string $host;
    private int $port;
    private string $password;
    private int $database;
    private int $timeout = 3;
    private \Monolog\Logger $ErrorHandler;

    private function __construct()
    {
        //
    }

    private function __clone()
    {
        //
    }

    public function __wakeup()
    {
        //
    }

    public static function obj(): CachePool
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function error(string $message): void
    {
        $this->ErrorHandler->error($message);
    }

    public function declareConnection(
        string $host,
        int $port,
        string $password,
        int $database,
        \Monolog\Logger $ErrorHandler
    ): void {
        $this->RedisPool = new RedisPool(
            (new RedisConfig)
                ->withHost($host)
                ->withPort($port)
                ->withAuth($password)
                ->withDbIndex($database)
                ->withTimeout($this->timeout)
        );
        \register_shutdown_function("Memcrab\Cache\CachePool::shutdown");
    }

    private function getRedis(): Redis
    {
        return $this->RedisPool->get();
    }

    private function putRedis(Redis $Redis): void
    {
        $this->RedisPool->put($Redis);
    }

    /**
     * @throws RedisException
     */
    public function getLastNKeys($minuteCounter, $keyNumber, $keyPrefix, &$resultArray): void
    {
        for ($i = 1; $i <= $keyNumber; $i++) {
            if ($this->exists($keyPrefix . $minuteCounter)) {
                $resultRaw = $this->get($keyPrefix . $minuteCounter);
                if (!is_bool($resultRaw)) {
                    $resultArray[] = unserialize($resultRaw);
                }
            }

            if ($minuteCounter == 0) {
                $minuteCounter = 9;
            } else {
                $minuteCounter--;
            }
        }
    }

    private function addError($errorMessage = "")
    {
        $trace = debug_backtrace();
        array_shift($trace);
        Log::stream("errors")->error("Redis Exception: " . $errorMessage . self::getTraceAsString($trace));
        $this->errors[] = array($errorMessage, self::getTraceAsString($trace));
        return $this;
    }

    public static function getTraceAsString(array $backtrace)
    {
        $result = "\n";
        foreach ($backtrace as $key => $value) {
            $result .= (isset($value['file']) ? $value['file'] : "") .
                " (" . (isset($value['line']) ? $value['line'] : "") . ") " .
                (isset($value['class']) ? $value['class'] : "") .
                (isset($value['type']) ? $value['type'] : "") .
                $value['function'] .
                "\n";
        }
        return $result;
    }

    public function get($key)
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->get($key);
            $this->putRedis($Redis);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
                isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    public function delete($key)
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->del($key);
            $this->putRedis($Redis);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
                isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    public function exists($key): bool
    {
        try {
            $Redis = $this->getRedis();
            $result = (bool)$Redis->exists($key);
            $this->putRedis($Redis);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
                isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    public function setEx($key, $ttl, $value)
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->setEx($key, $ttl, $value);
            $this->putRedis($Redis);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
                isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    public function hSet($key, $hashKey, $value)
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->hSet($key, $hashKey, $value);
            $this->putRedis($Redis);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
                isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    public static function shutdown(): void
    {
        if (isset(self::$instance)) {
            self::$instance->close();
        }
    }

    function __destruct()
    {
        $this->close();
    }
}
