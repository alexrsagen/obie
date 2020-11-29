<?php namespace Obie\Encoding;
use \CBOR\CBOREncoder;
use \CBOR\Types\CBORByteString;

class Cbor {
	public static function decode($input, &$rest = null) {
		if ($input === null) return null;

		$_input = $input;
		try {
			$output = CBOREncoder::decode($input);
		} catch (\Exception $e) {
			return null;
		}
		if (isset($rest) && $rest !== null && $input !== $_input) {
			$rest = $input;
		}

		if (is_array($output)) {
			array_walk_recursive($output, function(&$val, $idx) {
				if ($val instanceof CBORByteString) {
					$val = $val->get_byte_string();
				}
			});
		}

		return $output;
	}

	public static function encode($input) {
		try {
			$output = CBOREncoder::encode($input);
		} catch (\Exception $e) {
			return null;
		}
		return $output;
	}

	public static function decodeBase64Url(string $input) {
		$output = Base64Url::decode($input);
		if ($output === false) return null;
		return static::decode($output);
	}

	public static function encodeBase64Url($input) {
		$output = static::encode($input);
		if ($output === null) return null;
		return Base64Url::encode($output);
	}
}