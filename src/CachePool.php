<?php

declare(strict_types=1);

namespace Memcrab\Cache;

use Exception;
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
    private array $errors;

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

    /**
     * @return CachePool
     */
    public static function obj(): CachePool
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $message
     * @return void
     */
    private function error(string $message): void
    {
        $this->ErrorHandler->error($message);
    }

    /**
     * @param string $host
     * @param int $port
     * @param string $password
     * @param int $database
     * @param \Monolog\Logger $ErrorHandler
     * @return void
     */
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
    }

    /**
     * @return Redis
     */
    private function getRedis(): Redis
    {
        return $this->RedisPool->get();
    }

    /**
     * @param Redis $Redis
     * @return void
     */
    private function putRedis(Redis $Redis): void
    {
        $this->RedisPool->put($Redis);
    }

    /**
     * @param $minuteCounter
     * @param $keyNumber
     * @param $keyPrefix
     * @param $resultArray
     * @return void
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

    /**
     * @param string $errorMessage
     * @return $this
     */
    private function addError(string $errorMessage = ""): self
    {
        $trace = debug_backtrace();
        array_shift($trace);
        Log::stream("errors")->error("Redis Exception: " . $errorMessage . self::getTraceAsString($trace));
        $this->errors[] = array($errorMessage, self::getTraceAsString($trace));
        return $this;
    }

    /**
     * @param array $backtrace
     * @return string
     */
    public static function getTraceAsString(array $backtrace): string
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

    /**
     * @param $key
     * @return false|mixed|Redis|string
     */
    public function get($key): mixed
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->get($key);
            $this->putRedis($Redis);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    /**
     * @param $key
     * @return false|int|Redis
     */
    public function delete($key): bool|int|Redis
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->del($key);
            $this->putRedis($Redis);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    /**
     * @param $key
     * @return bool
     */
    public function exists($key): bool
    {
        try {
            $Redis = $this->getRedis();
            $result = (bool)$Redis->exists($key);
            $this->putRedis($Redis);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    /**
     * @param $key
     * @param $ttl
     * @param $value
     * @return bool|Redis
     */
    public function setEx($key, $ttl, $value): bool|Redis
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->setEx($key, $ttl, $value);
            $this->putRedis($Redis);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
                isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    /**
     * @param $key
     * @param $hashKey
     * @param $value
     * @return bool|int|Redis
     */
    public function hSet($key, $hashKey, $value): bool|int|Redis
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->hSet($key, $hashKey, $value);
            $this->putRedis($Redis);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
                isset($Redis) ?? $this->putRedis($Redis);
            return false;
        }

        return $result;
    }

    /**
     * @param $key
     * @return array|false|Redis
     */
    public function hVals($key): bool|array|Redis
    {
        try {
            $Redis = $this->getRedis();
            $result = $Redis->hVals($key);
            $this->putRedis($Redis);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        return $result;
    }
}
