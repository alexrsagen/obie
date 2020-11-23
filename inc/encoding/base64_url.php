<?php namespace ZeroX\Encoding;

class Base64Url {
	public static function encode(string $input) {
		return urlencode(str_replace(array('/', '+'), array('_', '-'), base64_encode($input)));
	}

	public static function encodeUnpadded(string $input) {
		return urlencode(str_replace(array('/', '+'), array('_', '-'), rtrim(base64_encode($input), '=')));
	}

	public static function decode(string $input) {
		return base64_decode(str_replace(array('_', '-'), array('/', '+'), urldecode($input)));
	}
}