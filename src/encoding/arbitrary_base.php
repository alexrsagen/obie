<?php namespace Obie\Encoding;

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

	public static function encode(string $alphabet, string|int $input): string {
		$output = '';
		if (function_exists('gmp_init')) {
			$input = gmp_init($input);
			$base = gmp_init(strlen($alphabet));
			$zero = gmp_init(0);
			do {
				$offset = gmp_intval(gmp_mod($input, $base));
				$output = $alphabet[$offset] . $output;
				$input = gmp_div($input, $base);
			} while (gmp_cmp($input, $zero) > 0);
		} elseif (function_exists('bcadd')) {
			$base = (string)strlen($alphabet);
			$input = (string)$input;
			$zero = '0';
			do {
				$offset = bcmod($input, $base, 0);
				$output = $alphabet[(int)$offset] . $output;
				$input = bcdiv($input, $base, 0);
			} while (bccomp($input, $zero) > 0);
		} else {
			throw new \Exception('GMP or BCMath extension is required for encode/decode');
		}
		return $output;
	}

	public static function decode(string $alphabet, string $input, bool $return_string = false): int|string {
		$len = strlen($input);
		if (function_exists('gmp_init')) {
			$base = gmp_init(strlen($alphabet));
			$output = gmp_init(0);

			for ($i = 0; $i < $len; $i++) {
				$offset = strpos($alphabet, $input[$i]);
				if ($offset === false) {
					throw new \Exception('Invalid input string');
				}
				$offset = gmp_init($offset);
				$exp = $len - $i - 1;
				$output = gmp_add($output, gmp_mul($offset, gmp_pow($base, $exp)));
			}

			return $return_string ? gmp_strval($output) : gmp_intval($output);
		} elseif (function_exists('bcadd')) {
			$base = (string)strlen($alphabet);
			$output = '0';

			for ($i = 0; $i < $len; $i++) {
				$offset = strpos($alphabet, $input[$i]);
				if ($offset === false) {
					throw new \Exception('Invalid input string');
				}
				$offset = (string)$offset;
				$exp = (string)($len - $i - 1);
				$output = bcadd($output, bcmul($offset, bcpow($base, $exp, 0), 0), 0);
			}

			return $return_string ? $output : (int)$output;
		} else {
			throw new \Exception('GMP or BCMath extension is required for encode/decode');
		}
	}

	public static function convert(string $number, int $from_base, int $to_base): string {
		$number = ltrim($number, '0');
		$digits = str_split($number);

		// Iterate over the input, modulo-ing out an output digit
		// at a time until input is gone.
		$result = '';
		while ($digits) {
			$work = 0;
			$workDigits = [];

			// Long division...
			foreach ($digits as $digit) {
				$work *= $from_base;
				$work += $digit;

				if ($workDigits || $work >= $to_base) {
					$workDigits[] = (int)($work / $to_base);
				}
				$work %= $to_base;
			}

			// All that division leaves us with a remainder,
			// which is conveniently our next output digit.
			$result .= (string)$work;

			// And we continue!
			$digits = $workDigits;
		}

		return strrev($result);
	}
}