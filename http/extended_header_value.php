<?php namespace Obie\Http;

/**
 * ExtendedHeaderValue implements an RFC 8187 compliant HTTP ext-value encoder/decoder.
 *
 * @package Obie\Encoding
 */
class ExtendedHeaderValue {
	public static function needsEncoding(string $input): string {
		return preg_match('/[^!#$%&\'*+-.^_`|~0-9A-Za-z]/', $input) === 1;
	}

	public static function encode(string $input): string {
		if (!static::needsEncoding($input)) return $input;
		return "UTF-8''" . rawurlencode($input);
	}

	public static function decode(string $input): string {
		$charset_end_pos = strpos($input, "'");
		if ($charset_end_pos === false || $charset_end_pos === 0) return $input;
		$charset = substr($input, 0, $charset_end_pos);

		$language_end_pos = strpos($input, "'", $charset_end_pos + 1);
		if ($language_end_pos === false) return $input;
		// XXX: PHP implementation of iconv does not support specifying a language
		// TODO: Use another method of converting, which does support language-specific charsets?
		// $language = locale_parse(substr($input, $charset_end_pos + 1, $language_end_pos));

		$value = rawurldecode(substr($input, $language_end_pos + 1));
		if (strcasecmp($charset, 'UTF-8') !== 0) {
			return iconv($charset, 'UTF-8', $value) ?: $input;
		}
		return $value;
	}
}