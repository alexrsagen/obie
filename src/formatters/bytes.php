<?php namespace Obie\Formatters;

class Bytes {
	const BASE_2  = 2;
	const BASE_10 = 10;

	const SUFFIXES = [
		self::BASE_2  => ['byte', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'],
		self::BASE_10 => ['byte', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'],
	];

	public static function toString(int $bytes, int $base = 2, int $decimals = 2, string $dec_point = '.', string $thousands_sep = '', bool $show_zero_dec = false): string {
		if (!array_key_exists($base, self::SUFFIXES)) return '';
		$suffixes = self::SUFFIXES[$base];
		$mul = $base === 2 ? 1024 : 1000;
		$suffix_min = 1;
		for ($i = 0; $i < count($suffixes); $i++) {
			$suffix = $suffixes[$i];
			$suffix_div = $suffix_min;
			$suffix_min = bcpow($mul, $i + 1);
			if (bccomp($bytes, $suffix_min) < 0) break;
		}
		$dec_div = bcpow(10, $decimals);
		$suffix_n = (float)bcdiv(bcmul(bcdiv($bytes, $suffix_div, $decimals), $dec_div), $dec_div, $decimals);
		$suffix_n_fmt = number_format($suffix_n, $decimals, $dec_point, $thousands_sep);
		if (!$show_zero_dec) {
			$suffix_n_fmt = rtrim(rtrim($suffix_n_fmt, '0'), $dec_point);
		}
		return $suffix_n_fmt . ' ' . $suffix . ($i === 0 ? ($suffix_n === 1.0 ? '' : 's') : '');
	}
}