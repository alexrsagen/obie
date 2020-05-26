<?php namespace ZeroX\Encoding;
if (!defined('IN_ZEROX')) {
	return;
}

class Pem {
	const REGEX = '/^-{5}BEGIN (?<label>[\x21-\x2C\x2E-\x7E](?:[- ]?[\x21-\x2C\x2E-\x7E])*)-{5}[ \t]*(?:\r\n|\r|\n)[ \t\r\n]*(?<data>(?:[A-Za-z0-9+\/]+[ \t]*(?:\r\n|\r|\n))*[A-Za-z0-9+\/]*(?:=[ \t]*(?:\r\n|\r|\n)=|={0,2}))[ \t]*(?:\r\n|\r|\n)-{5}END \k<label>-{5}[ \t]*(?:\r\n|\r|\n)?$/';

	const BOUNDARY_PREFIX_PRE  = '-----BEGIN ';
	const BOUNDARY_PREFIX_POST = '-----END ';
	const BOUNDARY_SUFFIX      = '-----';

	const LABEL_PUBLICKEY      = 'PUBLIC KEY';
	const LABEL_RSA_PUBLICKEY  = 'RSA PUBLIC KEY';
	const LABEL_PGP_PUBLICKEY  = 'PGP PUBLIC KEY BLOCK';
	const LABEL_PRIVATEKEY     = 'PRIVATE KEY';
	const LABEL_RSA_PRIVATEKEY = 'RSA PRIVATE KEY';
	const LABEL_DSA_PRIVATEKEY = 'DSA PRIVATE KEY';
	const LABEL_EC_PRIVATEKEY  = 'EC PRIVATE KEY';
	const LABEL_PGP_PRIVATEKEY = 'PGP PRIVATE KEY BLOCK';
	const LABEL_CERTIFICATE    = 'CERTIFICATE';
	const LABEL_CSR            = 'CERTIFICATE REQUEST';
	const LABEL_NEW_CSR        = 'NEW CERTIFICATE REQUEST';
	const LABEL_PKCS7          = 'PKCS7';
	const LABEL_X509_CRL       = 'X509 CRL';

	public static function encode(string $data, string $label = self::LABEL_PUBLICKEY) {
		return static::getPreBoundary($label) . "\r\n" .
			chunk_split(base64_encode($data), 64) .
			static::getPostBoundary($label);
	}

	public static function decode(string $data) {
		if (preg_match(self::REGEX, $data, $matches) !== 1) return false;
		return base64_decode(str_replace([' ', "\t", "\r", "\n"], '', $matches['data']));
	}

	public static function decodeLabel(string $data) {
		if (preg_match(self::REGEX, $data, $matches) !== 1) return false;
		return trim($matches['label'], " \t\r\n");
	}

	public static function isEncoded(string $data) {
		return preg_match(self::REGEX, $data) === 1;
	}

	public static function getPreBoundary(string $label) {
		return self::BOUNDARY_PREFIX_PRE . strtoupper($label) . self::BOUNDARY_SUFFIX;
	}

	public static function getPostBoundary(string $label) {
		return self::BOUNDARY_PREFIX_POST . strtoupper($label) . self::BOUNDARY_SUFFIX;
	}
}