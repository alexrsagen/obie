<?php namespace Obie\Encoding;

class Uuid {
	const PREFIX_URN = 'urn:uuid:';

	public static function decode(string $uuid): ?string {
		$uuid = hex2bin(str_replace(['-', '{', '}', self::PREFIX_URN], '', $uuid));
		if (!is_string($uuid) || strlen($uuid) !== 16) return null;
		return $uuid;
	}

	public static function encode(string $uuid, bool $brackets = false, string $prefix = ''): string {
		$uuid = bin2hex($uuid);
		return $prefix . ($brackets ? '{' : '') . substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12) . ($brackets ? '}' : '');
	}

	public static function generate(): string {
		$buf = random_bytes(16);
		// As per RFC 4122 section 4.4, set bits for version and clock_seq_hi_and_reserved
		$buf[6] = chr((ord($buf[6]) & 0x0f) | 0x40);
		$buf[8] = chr((ord($buf[8]) & 0x3f) | 0x80);
		return static::encode($buf);
	}
}