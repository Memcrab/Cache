<?php declare (strict_types=1);

namespace Memcrab\Cache;


use Memcrab\Log\Log;

class Cache
{
    protected static $instance;

    private $connect;
    private $timeout = 2.5;

    private $errors;

    private function __construct(){}

    private function __clone(){}

    private function __wakeup(){}

    public static function obj()
    {
        if (!isset(self::$instance) || !(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function trySetConnect(string $host, $port, int $database, string $password)
    {
        try {
            $this->connect = new \Redis();
            if (!$this->connect->connect($host, $port, $this->timeout)) {
                throw new \Exception(_("Unable to connect to the cache server"), 1);
            }

            $this->connect->auth($password);
            $this->connect->select($database);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
        }
    }

    public function pingConnection()
    {
        try {
            $status = $this->connect->ping();
            if ($status != '+PONG') {
                return false;
            } else {
                return true;
            }
        } catch (\Exception | \RuntimeException | \Error $e) {
            $this->addError($e);
            return false;
        }
    }

    public function getConnect()
    {
        return $this->connect;
    }

    function __destruct()
    {
        try {
            if ($this->connect !== false && $this->connect !== NULL) {
                $this->connect->close();
            }
        } catch (\RedisException $e) {
            $this->addError($e->getMessage());
        }
    }

    public function exists($key)
    {
        try {
            return $this->connect->exists($key);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function get($key)
    {
        return $this->connect->get($key);
    }

    public function delete($key)
    {
        return $this->connect->del($key);
    }

    public function set($key, $value)
    {
        try {
            return $this->connect->set($key, $value);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function setEx($key, $ttl, $value)
    {
        try {
            return $this->connect->setEx($key, $ttl, $value);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function setExpire($key, $ttl)
    {
        try {
            return $this->connect->expire($key, $ttl);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function getTTL($key)
    {
        try {
            return $this->connect->ttl($key);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
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

    public function sAdd(...$params)
    {
        try {
            return call_user_func_array(array($this->connect, 'sAdd'), $params);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function sRem(...$params)
    {
        try {
            return call_user_func_array(array($this->connect, 'sRem'), $params);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function sGetMembers($key)
    {
        try {
            return $this->connect->sGetMembers($key);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function hSet($key, $hashKey, $value)
    {
        try {
            return $this->connect->hSet($key, $hashKey, $value);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function hGet($key, $hashKey)
    {
        try {
            return $this->connect->hGet($key, $hashKey);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function hDel($key, $hashKey)
    {
        try {
            return $this->connect->hDel($key, $hashKey);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function hMSet($key, array $data)
    {
        try {
            return $this->connect->hMSet($key, $data);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function hMGet($key, array $hashKeys)
    {
        try {
            return $this->connect->hMGet($key, $hashKeys);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    public function hVals($key)
    {
        try {
            return $this->connect->hVals($key);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
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

    public function getLastNKeys($minuteCounter, $keyNumber, $keyPrefix, &$resultArray)
    {
        for ($i = 1; $i <= $keyNumber; $i++) {
            if ($this->connect->exists($keyPrefix . $minuteCounter)) {
                $resultRaw = $this->connect->get($keyPrefix . $minuteCounter);
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

    public function getErrors()
    {
        return $this->errors;
    }
}
