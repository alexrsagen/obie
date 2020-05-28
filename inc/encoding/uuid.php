<?php namespace ZeroX\Encoding;

class Uuid {
	const PREFIX_URN = 'urn:uuid:';

	public static function decode(string $uuid) {
		$uuid = hex2bin(str_replace(array('-', '{', '}', self::PREFIX_URN), '', $uuid));
		if (!$uuid || strlen($uuid) !== 16) {
			throw new \Exception('Invalid UUID');
		}
		return $uuid;
	}

	public static function encode(string $uuid, bool $brackets = false, string $prefix = '') {
		$uuid = bin2hex($uuid);
		return $prefix . ($brackets ? '{' : '') . substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12) . ($brackets ? '}' : '');
	}

	public static function generate(): string {
		return static::encode(random_bytes(16));
	}
}