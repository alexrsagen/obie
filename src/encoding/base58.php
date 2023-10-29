<?php namespace Obie\Encoding;

class Base58 {
	const ALPHABET = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";

	public static function encode(string $str): string {
		return static::encodeBytes(array_values(unpack('C*', $str)));
	}

	public static function decode(string $str): ?string {
		$bytes = static::decodeToBytes($str);
		if ($bytes === null) return null;
		return pack('C*', ...$bytes);
	}

	public static function encodeBytes(array $input): string {
		if (count($input) === 0) return '';

		// Skip & count leading zeroes.
		for ($zeroes = 0; $zeroes < count($input) && $input[$zeroes] === 0; $zeroes++) {}
		$input = array_slice($input, $zeroes);

		// Allocate enough space in big-endian base58 representation.
		$size = count($input) * 138 / 100 + 1; // log(256) / log(58), rounded up.
		$b58 = array_fill(0, $size, 0);

		// Process the bytes.
		$length = 0;
		for ($i = 0; $i < count($input); $i++) {
			$carry = $input[$i];
			for ($j = 0; ($carry !== 0 || $j < $length) && $j < count($b58); $j++) {
				$carry += 256 * $b58[count($b58) - 1 - $j];
				$b58[count($b58) - 1 - $j] = $carry % 58;
				$carry = intdiv($carry, 58);
			}
			$length = $j;
		}

		// Skip leading zeroes in base58 result.
		for ($i = 0; $i < count($b58) && $b58[$i] === 0; $i++) {}

		// Translate the result into a string.
		$str = [];
		for ($j = 0; $j < $zeroes; $j++) {
			$str[$j] = '1';
		}
		for (; $i < count($b58); $i++) {
			$str[$zeroes + $i] = self::ALPHABET[$b58[$i]];
		}
		return implode('', $str);
	}

	public static function decodeToBytes(string $str): ?array {
		// Trim leading and trailing spaces.
		$str = trim($str);
		if (strlen($str) === 0) return [];

		// Skip and count leading '1's.
		for ($zeroes = 0; $zeroes < strlen($str) && $str[$zeroes] === '1'; $zeroes++) {}
		$str = substr($str, $zeroes);

		// Allocate enough space in big-endian base256 representation.
		$size = ceil(strlen($str) * 733 / 1000); // log(58) / log(256), rounded up.
		$b256 = array_fill(0, $size, 0);

		// Process the characters.
		$length = 0;
		for ($i = 0; $i < strlen($str); $i++) {
			$carry = strpos(self::ALPHABET, $str[$i]);
			if ($carry === false) return null;
			for ($j = 0; ($carry !== 0 || $j < $length) && $j < count($b256); $j++) {
				$carry += 58 * $b256[count($b256) - 1 - $j];
				$b256[count($b256) - 1 - $j] = $carry % 256;
				$carry = intdiv($carry, 256);
			}
			$length = $j;
		}

		// Skip leading zeroes in b256.
		for ($i = 0; $i < count($b256) && $b256[$i] === 0; $i++) {}
		return array_slice($b256, $i, $length);
	}
}