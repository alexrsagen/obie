<?php namespace Obie\Http;

class Token {
	// token := ALPHA / DIGIT / "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "." / "^" / "_" / "`" / "|" / "~"
	const TOKEN_CHARS = [
		"!", "#", "$", "%", "&", "'", "*", "+",
		"-", ".", "^", "_", "`", "|", "~", "0",
		"1", "2", "3", "4", "5", "6", "7", "8",
		"9", "A", "B", "C", "D", "E", "F", "G",
		"H", "I", "J", "K", "L", "M", "N", "O",
		"P", "Q", "R", "S", "T", "U", "V", "W",
		"X", "Y", "Z", "a", "b", "c", "d", "e",
		"f", "g", "h", "i", "j", "k", "l", "m",
		"n", "o", "p", "q", "r", "s", "t", "u",
		"v", "w", "x", "y", "z",
	];

	// attr-char := ALPHA / DIGIT / "!" / "#" / "$" / "&" / "+" / "-" / "." / "^" / "_" / "`" / "|" / "~"
	// token except ( "*" / "'" / "%" )
	const ATTR_CHARS = [
		"!", "#", "$",      "&",           "+",
		"-", ".", "^", "_", "`", "|", "~", "0",
		"1", "2", "3", "4", "5", "6", "7", "8",
		"9", "A", "B", "C", "D", "E", "F", "G",
		"H", "I", "J", "K", "L", "M", "N", "O",
		"P", "Q", "R", "S", "T", "U", "V", "W",
		"X", "Y", "Z", "a", "b", "c", "d", "e",
		"f", "g", "h", "i", "j", "k", "l", "m",
		"n", "o", "p", "q", "r", "s", "t", "u",
		"v", "w", "x", "y", "z",
	];

	public static function isValidToken(string $input): bool {
		for ($i = 0; $i < strlen($input); $i++) {
			if (!in_array($input[$i], self::TOKEN_CHARS, true)) {
				return false;
			}
		}
		return true;
	}

	public static function isValidParamName(string $input): bool {
		for ($i = 0; $i < strlen($input); $i++) {
			if (!in_array($input[$i], self::ATTR_CHARS, true)) {
				return false;
			}
		}
		return true;
	}

	public static function isValidParamValue(string $input): bool {
		$hexdig_pos = -1;
		for ($i = 0; $i < strlen($input); $i++) {
			if ($hexdig_pos > -1) {
				if (!ctype_xdigit($input[$i])) return false;
				$hexdig_pos++;
				if ($hexdig_pos === 2) $hexdig_pos = -1;
			} elseif ($input[$i] === '%') {
				$hexdig_pos = 0;
			} elseif (!in_array($input[$i], self::ATTR_CHARS, true)) {
				return false;
			}
		}
		return true;
	}
}