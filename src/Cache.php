<?php

declare(strict_types=1);

namespace Memcrab\Cache;

class Cache extends \Redis
{
    protected static Cache $instance;
    private string $host;
    private int $port;
    private string $password;
    private int $database;
    private int $timeout = 3;
    private \Monolog\Logger $ErrorHandler;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }

    public static function obj()
    {
        if (!isset(self::$instance)) {
            throw new \Exception('Undefined Redis object, please declare connection first', 500);
        }

        return self::$instance;
    }

    private function error(string $message)
    {
        $this->ErrorHandler->error($message);
    }

    public static function declareConnection(
        string $host,
        int $port,
        string $password,
        int $database,
        \Monolog\Logger $ErrorHandler,
    ) {
        self::$instance = new Cache();
        self::$instance->host =  $host;
        self::$instance->port =  $port;
        self::$instance->password =  $password;
        self::$instance->database =  $database;
        self::$instance->ErrorHandler = $ErrorHandler;

        \register_shutdown_function("Memcrab\Cache\Cache::shutdown");
    }

    public function setConnection(): bool
    {
        try {
            if ($this->connect($this->host, $this->port, $this->timeout)  === false) {
                throw new \Exception("Can't connect to Redis Server by host: " . $this->host . " and port: " . $this->port, 500);
            }

            if ($this->auth($this->password) === false) {
                throw new \Exception("Can't autentificate Redis user by password", 500);
            }

            if ($this->select($this->database) === false) {
                throw new \Exception("Can't select Redis database with index: " . $this->database, 500);
            }
            return true;
        } catch (\Exception $e) {
            $this->error((string) $e);
            return false;
        }
    }

    public function ping($message = null): string|bool
    {
        if (parent::ping() === true) {
            return true;
        } else {
            $this->error('Ping is lost connection with Redis');
            return false;
        }
    }

    public static function shutdown()
    {
        if (isset(self::$instance)) {
            self::$instance->close();
        }
    }

    function __destruct()
    {
        $this->close();
    }

    public function getLastNKeys($minuteCounter, $keyNumber, $keyPrefix, &$resultArray)
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

    public function copyConnection(): \Memcrab\Cache\Cache
    {
        $vars = get_object_vars($this);
        $connections = new self();
        foreach ($vars as $key => $value) {
            $connections->$key = $value;
        }
        $connections->setConnection();
        return $connections;
    }
}
