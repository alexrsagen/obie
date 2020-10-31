<?php namespace ZeroX;
use \ZeroX\Encoding\Uuid;

class Random {
	public static function int(int $min, int $max): int {
		return random_int($min, $max);
	}

	public static function bytes(int $length): string {
		return random_bytes($length);
	}

	public static function string(int $length, string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'): string {
		$random_string = '';
		for ($i = 0; $i < $length; $i++) {
			$random_string .= $charset[random_int(0, strlen($charset) - 1)];
		}
		return $random_string;
	}

	public static function uuid(): string {
		return Uuid::generate();
	}

	public static function intHash(bool $short = false, bool $predictable = false, string $part_user = ''): int {
		// Create snowflake hash
		$hash_ctx = hash_init('sha256');
		if (!$predictable) {
			$part_time = (string)round(microtime(true) * 1000);
			hash_update($hash_ctx, $part_time);
		}
		hash_update($hash_ctx, $part_user);
		if (!$predictable) {
			$part_prng = random_bytes(32);
			hash_update($hash_ctx, $part_prng);
		}
		$id_hex = hash_final($hash_ctx, false);

		// Convert 32-byte snowflake hash to two 16-byte number parts and XOR them together
		$id_l_gmp = gmp_init(substr($id_hex, 0, 16), 16);
		$id_r_gmp = gmp_init(substr($id_hex, 16, 16), 16);
		$id_gmp = gmp_xor($id_l_gmp, $id_r_gmp);

		if ($short) {
			// Split 16-byte snowflake hash to two 8-byte number parts and XOR them together
			$id_hex = gmp_strval($id_gmp, 16);
			$id_l_gmp = gmp_init(substr($id_hex, 0, 8), 16);
			$id_r_gmp = gmp_init(substr($id_hex, 8, 8), 16);
			$id_gmp = gmp_xor($id_l_gmp, $id_r_gmp);
		}

		return gmp_intval($id_gmp);
	}
}