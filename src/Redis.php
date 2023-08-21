<?php
declare(strict_types=1);

namespace Memcrab\Cache;

use Monolog\Logger;
use OpenSwoole\Core\Coroutine\Client\RedisConfig;

class Redis extends \Redis
{
    private Logger $ErrorHandler;

    public function __construct(RedisConfig $RedisConfig, Logger $ErrorHandler)
    {
        $this->ErrorHandler = $ErrorHandler;

        try {
            $this->connect($RedisConfig->getHost(), $RedisConfig->getPort(), $RedisConfig->getTimeout());
        } catch (\Exception $e) {
            throw new \RedisException("Cant connect to Redis. " . $e, 500);
        }

        try {
            $this->auth($RedisConfig->getAuth());
        } catch (\Exception $e) {
            throw new \RedisException("Can't authenticate Redis user by password. " . $e, 500);
        }

        try {
            $this->select($RedisConfig->getDbIndex());
        } catch (\Exception $e) {
            throw new \RedisException("Can't select Redis database with index: " . $RedisConfig->getDbIndex() . ' ' . $e, 500);
        }
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable $e) {
            $this->ErrorHandler->error('Redis disconnect error: ' . $e);
        }
    }

    private function error(\Exception $e): void
    {
        $this->ErrorHandler->error('Redis Exception: ' . $e);
    }

    public function heartbeat(): void
    {
        $this->ping();
    }

    public function getLastNKeys($minuteCounter, $keyNumber, $keyPrefix, &$resultArray): void
    {
        try {
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
        } catch (\Exception $e) {
            $this->error($e);
            throw $e;
        }
    }
}