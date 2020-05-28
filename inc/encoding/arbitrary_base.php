<?php namespace ZeroX\Encoding;

class ArbitraryBase {
	// XXX: Changing these constants is not a good idea
	const ALPHABET_BASE64URL   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
	const ALPHABET_BASE64      = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
	const ALPHABET_BASE62      = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	const ALPHABET_BASE36LOWER = 'abcdefghijklmnopqrstuvwxyz0123456789';
	const ALPHABET_BASE36UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	const ALPHABET_BASE32LOWER = 'abcdefghijklmnopqrstuvwxyz234567';
	const ALPHABET_BASE32UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	const ALPHABET_BASE16LOWER = '0123456789abcdef';
	const ALPHABET_BASE16UPPER = '0123456789ABCDEF';

	public static function encode(string $alphabet, int $input) {
		$input_gmp = gmp_init($input);
		$base_gmp = gmp_init(strlen($alphabet));
		$zero_gmp = gmp_init(0);
		$output = "";

		do {
			$offset = gmp_intval(gmp_mod($input_gmp, $base_gmp));
			$output = $alphabet[$offset] . $output;
			$input_gmp = gmp_div($input_gmp, $base_gmp);
		} while (gmp_cmp($input_gmp, $zero_gmp) > 0);

		return $output;
	}

	public static function decode(string $alphabet, string $input) {
		$base_gmp = gmp_init(strlen($alphabet));
		$output_gmp = gmp_init(0);
		$len = strlen($input);

		for ($i = 0; $i < $len; $i++) {
			$offset = strpos($alphabet, $input[$i]);
			if ($offset === false) {
				throw new \Exception("Invalid input string");
			}
			$offset_gmp = gmp_init($offset);
			$output_gmp = gmp_add($output_gmp, gmp_mul($offset_gmp, gmp_pow($base_gmp, $len - $i - 1)));
		}

		return gmp_intval($output_gmp);
	}
}