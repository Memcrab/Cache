<?php
declare (strict_types = 1);
namespace Memcrab\Cache;
use memCrab\Exceptions\FileException;

class PHPCache implements FileCache {
	private $cacheFolderPath;

	function __construct(string $cacheFolderPath) {
		$this->cacheFolderPath = $cacheFolderPath;
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

		//TODO: check file name limits to 255 characters
		$file = "CACHE_" . str_replace(DIRECTORY_SEPARATOR, "_", $fullpath) . "_" . $modified;

		return $this->cacheFolderPath . DIRECTORY_SEPARATOR . $file;
	}

	public function exists(string $key): bool {
		return file_exists($key . ".php");
	}

	public function get(string $key): array{
		return include $key . ".php";
	}

	public function set(string $key, array $array): bool {
		return file_put_contents(
			$key . ".php",
			"<?php return " . var_export($array) . ";"
		);
	}
}
