<?php namespace Obie\Http;

/**
 * ExtendedHeaderValue implements an RFC 8187 compliant HTTP ext-value encoder/decoder.
 *
 * @package Obie\Encoding
 */
class ExtendedHeaderValue {
	const CHARSET_UTF8 = 'UTF-8';

	function __construct(
		public string $charset = self::CHARSET_UTF8,
		public string $language = '',
		public string $value = '',
	) {}

	public static function needsEncoding(string $input): bool {
		return !Token::isValidToken($input);
	}

	public static function encode(string $input): string {
		if (!static::needsEncoding($input)) return $input;
		return "UTF-8''" . rawurlencode($input);
	}

	public static function decode(string $input): ?string {
		$position = 0;
		return static::extract($input, $position, true)?->decodeValue();
	}

	public function decodeValue(): ?string {
		// XXX: PHP implementation of iconv does not support specifying a language
		// TODO: Use another method of converting, which does support language-specific charsets?
		$value = rawurldecode($this->value);
		if (strcasecmp($this->charset, self::CHARSET_UTF8) !== 0) {
			return @iconv($this->charset, self::CHARSET_UTF8, $value) ?: null;
		}
		return $value;
	}

	public static function extract(string $input, int &$position, bool $extract_value = false): static|string|null {
		// Let positionStart be position.
		$position_start = $position;

		// Let charset be the result of collecting a sequence of code points that are not U+0027 (') or U+003B (;) from input, given position.
		$charset = '';
		for (; $position < strlen($input) && $input[$position] !== '\'' && $input[$position] !== ';'; $position++) {
			$charset .= $input[$position];
		}
		// Remove any trailing HTTP whitespace from charset.
		$charset = rtrim($charset, "\n\r\t ");
		// Set charset to charset, in ASCII uppercase.
		$charset = strtoupper($charset);
		// If charset is the empty string or does not solely contain HTTP token code points, then return failure.
		if (strlen($charset) === 0 || !Token::isValidToken($charset)) return null;
		// If position is past the end of input or the code point at position within input is U+003B (;), then return failure.
		if ($position >= strlen($input) || $input[$position] === ';') return null;
		// Advance position by 1. (This skips past U+0027 (').)
		$position++;

		// Let language be the result of collecting a sequence of code points that are not U+0027 (') or U+003B (;) from input, given position.
		$language = '';
		for (; $position < strlen($input) && $input[$position] !== '\'' && $input[$position] !== ';'; $position++) {
			$language .= $input[$position];
		}
		// If language does not solely contain HTTP token code points, then return failure.
		if (!Token::isValidToken($language)) return null;
		// If position is past the end of input or the code point at position within input is U+003B (;), then return failure.
		if ($position >= strlen($input) || $input[$position] === ';') return null;
		// Advance position by 1. (This skips past U+0027 (').)
		$position++;

		// Let value be the result of collecting a sequence of code points that is not U+003B (;) from input, given position.
		$value = '';
		for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {
			$value .= $input[$position];
		}
		// If value does not solely contain RFC 8187 value-chars, then return failure.
		if (!Token::isValidParamValue($value)) return null;

		// If the extract-value flag is set, then return value.
		if ($extract_value) return new static($charset, $language, $value);

		// Return the code points from positionStart to position, inclusive, within input.
		return substr($input, $position_start, $position - $position_start);
	}
}