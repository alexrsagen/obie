<?php namespace Obie\Encoding;

class Bits {
	public static function encode(string $input): string {
		$output = '';
		for ($i = 0; $i < strlen($input); $i++) {
			$output .= str_pad(decbin(ord($input[$i])), 8, '0', STR_PAD_LEFT);
		}
		return $output;
	}

	public static function decode(string $input): string {
		$bytes_rev = str_split(strrev($input), 8);
		$output = '';
		for ($i = count($bytes_rev) - 1; $i >= 0; $i--) {
			$output .= chr(bindec(strrev($bytes_rev[$i])));
		}
		return $output;
	}

	public static function fromDecimal(string|int $dec): string {
		if (function_exists('gmp_init')) {
			return gmp_strval(gmp_init($dec, 10), 2);
		} elseif (function_exists('bcadd')) {
			$dec = (string)$dec;
			$bin = '';
			do {
				$bin = bcmod($dec, '2', 0) . $bin;
				$dec = bcdiv($dec, '2', 0);
			} while (bccomp($dec, '0', 0) > 0);
			return $bin;
		} else {
			return ArbitraryBase::convert((string)$dec, 10, 2);
		}
	}

	public static function fromOctal(string $oct): string {
		if (function_exists('gmp_init')) {
			return gmp_strval(gmp_init($oct, 8), 2);
		} elseif (function_exists('bcadd')) {
			$dec = '0';
			for ($i = 0; $i < strlen($oct); $i++) {
				$dec = bcmul($dec, '8', 0);
				$dec = bcadd($dec, $oct[$i], 0);
			}
			$bin = '';
			do {
				$bin = bcmod($dec, '2', 0) . $bin;
				$dec = bcdiv($dec, '2', 0);
			} while (bccomp($dec, '0', 0) > 0);
			return $bin;
		} else {
			return ArbitraryBase::convert($oct, 8, 2);
		}
	}
}