<?php
declare (strict_types = 1);
namespace Memcrab\Cache;

interface FileCache extends Cache {
	public function fileKey(string $filePath): string;
	public function get(string $key);
	public function set(string $key): void;
	public function exists(string $key): bool;
}
