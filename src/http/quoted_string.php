<?php namespace Obie\Http;

 /**
  * QuotedString implements an RFC 7230 compliant HTTP quoted-string encoder/decoder.
  *
  * @link https://datatracker.ietf.org/doc/html/rfc7230
  * @package Obie\Http
  */
class QuotedString {
	// An HTTP quoted-string token code point is U+0009 TAB, a code point in the range U+0020 SPACE to U+007E (~), inclusive, or a code point in the range U+0080 through U+00FF (ÿ), inclusive.
	// https://mimesniff.spec.whatwg.org/#http-quoted-string-token-code-point
	const VALID_CHARS = [
		// U+0009 TAB
		"\t",
		// a code point in the range U+0020 SPACE to U+007E (~), inclusive
		" ", "!", "\"", "#", "$", "%", "&", "'",
		"(", ")", "*", "+", ",", "-", ".", "/",
		"0", "1", "2", "3", "4", "5", "6", "7",
		"8", "9", ":", ";", "<", "=", ">", "?",
		"@", "A", "B", "C", "D", "E", "F", "G",
		"H", "I", "J", "K", "L", "M", "N", "O",
		"P", "Q", "R", "S", "T", "U", "V", "W",
		"X", "Y", "Z", "[", "\\", "]", "^", "_",
		"`", "a", "b", "c", "d", "e", "f", "g",
		"h", "i", "j", "k", "l", "m", "n", "o",
		"p", "q", "r", "s", "t", "u", "v", "w",
		"x", "y", "z", "{", "|", "}", "~",
		// a code point in the range U+0080 through U+00FF (ÿ), inclusive
		"\x80","\x81","\x82","\x83","\x84","\x85","\x86","\x87",
		"\x88","\x89","\x8a","\x8b","\x8c","\x8d","\x8e","\x8f",
		"\x90","\x91","\x92","\x93","\x94","\x95","\x96","\x97",
		"\x98","\x99","\x9a","\x9b","\x9c","\x9d","\x9e","\x9f",
		"\xa0","\xa1","\xa2","\xa3","\xa4","\xa5","\xa6","\xa7",
		"\xa8","\xa9","\xaa","\xab","\xac","\xad","\xae","\xaf",
		"\xb0","\xb1","\xb2","\xb3","\xb4","\xb5","\xb6","\xb7",
		"\xb8","\xb9","\xba","\xbb","\xbc","\xbd","\xbe","\xbf",
		"\xc0","\xc1","\xc2","\xc3","\xc4","\xc5","\xc6","\xc7",
		"\xc8","\xc9","\xca","\xcb","\xcc","\xcd","\xce","\xcf",
		"\xd0","\xd1","\xd2","\xd3","\xd4","\xd5","\xd6","\xd7",
		"\xd8","\xd9","\xda","\xdb","\xdc","\xdd","\xde","\xdf",
		"\xe0","\xe1","\xe2","\xe3","\xe4","\xe5","\xe6","\xe7",
		"\xe8","\xe9","\xea","\xeb","\xec","\xed","\xee","\xef",
		"\xf0","\xf1","\xf2","\xf3","\xf4","\xf5","\xf6","\xf7",
		"\xf8","\xf9","\xfa","\xfb","\xfc","\xfd","\xfe","\xff",
	];

	public static function isValid(string $input, bool $value_only = false): bool {
		// check start and end quote
		if (!$value_only && (!str_starts_with($input, '"') || !str_ends_with($input, '"'))) return false;
		// check charset
		for ($i = 1; $i < strlen($input) - 2; $i++) {
			if (!in_array($input[$i], self::VALID_CHARS)) return false;
		}
		return true;
	}

	public static function encode(string $input, bool $strip_invalid = false): ?string {
		$position = static::getOffset($input) ?? 0;
		$extracted = static::extract($input, $position);
		if ($extracted !== null && static::isValid($input)) {
			return $input;
		}
		if ($strip_invalid) {
			$input = preg_replace('/[^\x09\x20-\x7E\x80-\xFF]/', '', $input);
		}
		$input = sprintf('"%s"', str_replace(['\\', '"'], ['\\\\', '\\"'], $input));
		return static::isValid($input) ? $input : null;
	}

	public static function decode(string $input, bool $extract_value = true): ?string {
		$position = static::getOffset($input) ?? 0;
		return static::extract($input, $position, $extract_value);
	}

	public static function getOffset(string $input, int $offset = 0): ?int {
		$offset = strpos($input, '"', $offset);
		if ($offset === false) return null;
		return $offset;
	}

	public static function getLength(string $input): ?int {
		$offset = static::getOffset($input);
		if ($offset === null) return null;
		$position = $offset;
		static::extract($input, $position);
		return $position - $offset;
	}

	public static function extract(string $input, int &$position, bool $extract_value = false): ?string {
		// https://fetch.spec.whatwg.org/#collect-an-http-quoted-string
		// 1. Let positionStart be position.
		$position_start = $position;
		// 2. Let value be the empty string.
		$value = '';
		// 3. Assert: the code point at position within input is U+0022 (").
		if (substr($input, $position, 1) !== '"') return null;
		// 4. Advance position by 1.
		$position++;
		// 5. While true:
		while (true) {
			// 5.1. Append the result of collecting a sequence of code points that are not U+0022 (") or U+005C (\) from input, given position, to value.
			for (; $position < strlen($input) && $input[$position] !== '"' && $input[$position] !== '\\'; $position++) {
				$value .= $input[$position];
			}
			// 5.2. If position is past the end of input, then break.
			if ($position >= strlen($input)) break;
			// 5.3. Let quoteOrBackslash be the code point at position within input.
			$quote_or_backslash = $input[$position];
			// 5.4. Advance position by 1.
			$position++;
			// 5.5. If quoteOrBackslash is U+005C (\), then:
			if ($quote_or_backslash === '\\') {
				// 5.5.1. If position is past the end of input, then append U+005C (\) to value and break.
				if ($position >= strlen($input)) {
					$value .= '\\';
					break;
				}
				// 5.5.2. Append the code point at position within input to value.
				$value .= $input[$position];
				// 5.5.3. Advance position by 1.
				$position++;
			}
			// 5.6. Otherwise:
			else {
				// 5.6.1. Assert: quoteOrBackslash is U+0022 (").
				if ($quote_or_backslash !== '"') return null;
				// 5.6.2. Break.
				break;
			}
		}
		// 6. If the extract-value flag is set, then return value.
		if ($extract_value) return $value;
		// 7. Return the code points from positionStart to position, inclusive, within input.
		return substr($input, $position_start, $position - $position_start);
	}
}