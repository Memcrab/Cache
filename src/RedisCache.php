<?php
declare (strict_types = 1);
namespace memCrab\Cache;

use memCrab\Cache\FileCache;
use memCrab\Exceptions\FileException;

class RedislCache implements FileCache {
	private $Redis;

	function __construct(\Redis $Redis) {
		$this->Redis = $Redis;
	}

	public function fileKey(string $filePath): string{
		$fullpath = realpath($filePath);
		if ($fullpath === false) {
			throw new FileException(
				_("Can't get full path of file for caching:") . " " . $filePath,
				501
			);
		}

		$modified = filemtime($fullpath);

		return "YAML_" . $fullpath . "_" . $modified;
	}

	public function exists(string $key): bool {
		return $this->Redis->exists($key);
	}

	public function get(string $key): array{
		return unserialize($this->Redis->get($key));
	}

	public function set(string $key, array $array): bool {
		return $this->Redis->set($key, $array);
	}
}