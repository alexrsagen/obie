<?php namespace Obie\Encoding;

class Querystring {
	const DELIMITER = '&';

	const NUMERIC_TYPE_INDEXED = 0;
	const NUMERIC_TYPE_BRACKETS = 1;
	const NUMERIC_TYPE_APPEND = 2;

	public static function decode(string $qs): array {
		// get last possible query string,
		// in case invalid input or a full URL is passed
		$qs_pos = strrpos($qs, '?');
		if ($qs_pos !== false) {
			$qs = substr($qs, $qs_pos + 1);
		}

		// ensure explode has valid input
		if (strlen($qs) === 0) return [];

		// properly handle duplicate keys
		// case 1: "foo=1&foo"         => ['foo' => ['1', '']]
		// case 2: "foo[0]=1&foo[0]=2" => ['foo' => '1', '2']
		// case 3: "foo[a]=1&foo[a]=2" => ['foo' => ['a' => ['1', '2']]]
		//
		// note: explode should be faster than strtok:
		// https://www.php.net/manual/en/function.strtok.php#115124
		return array_merge_recursive(...array_map(function($v) {
			parse_str($v, $pair);
			return $pair;
		}, explode(self::DELIMITER, $qs)));
	}

	public static function encode(array $data, int $numeric_type = self::NUMERIC_TYPE_INDEXED): string {
		$qs = http_build_query($data, '', self::DELIMITER, PHP_QUERY_RFC3986);
		return match ($numeric_type) {
			self::NUMERIC_TYPE_BRACKETS => preg_replace('/%5B[0-9]+%5D/', '%5B%5D', $qs),
			self::NUMERIC_TYPE_APPEND => preg_replace('/%5B[0-9]+%5D/', '', $qs),
			default => $qs,
		};
	}
}