<?php namespace ZeroX\Security;
use ZeroX\Util;
use ZeroX\Encoding\Pem;
if (!defined('IN_ZEROX')) {
	return;
}

class Ecdsa {
	const PREFIX_EVEN = "\x02";
	const PREFIX_ODD = "\x03";
	const PREFIX_UNCOMPRESSED = "\x04";

	const PUBKEYLEN_SECP256R1_UNCOMPRESSED = (256/8)*2;
	const PUBKEYLEN_SECP256R1_COMPRESSED = (256/8);
	const PUBKEYLEN_SECP384R1_UNCOMPRESSED = (384/8)*2;
	const PUBKEYLEN_SECP384R1_COMPRESSED = (384/8);
	const PUBKEYLEN_SECP521R1_UNCOMPRESSED = 66*2; // ceil(521/8)*2
	const PUBKEYLEN_SECP521R1_COMPRESSED = 66; // ceil(521/8)

	const ASN1HEADER_SECP256R1_UNCOMPRESSED = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42";
	const ASN1HEADER_SECP256R1_COMPRESSED = "\x30\x39\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x22";
	const ASN1HEADER_SECP384R1_UNCOMPRESSED = "\x30\x76\x30\x10\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01\x06\x05\x2B\x81\x04\x00\x22\x03\x62";
	const ASN1HEADER_SECP384R1_COMPRESSED = "\x30\x46\x30\x10\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x05\x2b\x81\x04\x00\x22\x03\x32";
	const ASN1HEADER_SECP521R1_UNCOMPRESSED = "\x30\x81\x9B\x30\x10\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01\x06\x05\x2B\x81\x04\x00\x23\x03\x81\x86";
	const ASN1HEADER_SECP521R1_COMPRESSED = "\x30\x58\x30\x10\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x05\x2b\x81\x04\x00\x23\x03\x44";

	public static function pubKeyToPem(string $key, bool $compress = true): string {
		$label = Pem::LABEL_PUBLICKEY;
		if (!$compress) {
			// If key is already PEM-encoded, just return key
			if (Pem::isEncoded($key)) return $key;
			// If key is an ASN.1 DER sequence, just return PEM encoding of key
			if (!$compress && substr($key, 0, 1) === "\x30") return Pem::encode($key);
			$key_raw = static::stripCompressionPrefix($key);
		} else {
			if (Pem::isEncoded($key)) {
				$label = Pem::decodeLabel($key);
				if ($label === Pem::LABEL_CERTIFICATE) {
					$key_details = openssl_pkey_get_details(openssl_pkey_get_public($key));
					if (!is_array($key_details)) return '';
					$key = $key_details['key'];
				}
				$key = Pem::decode($key);
			}
			$key_raw = static::compressPubKey($key);
		}

		switch (strlen($key_raw)) {
			case self::PUBKEYLEN_SECP256R1_COMPRESSED:
			case self::PUBKEYLEN_SECP384R1_COMPRESSED:
			case self::PUBKEYLEN_SECP521R1_COMPRESSED:
				$key_raw = static::addCompressionPrefix($key_raw);
				break;
		}
		switch (strlen($key_raw)) {
			case self::PUBKEYLEN_SECP256R1_COMPRESSED + 1:
				return Pem::encode(self::ASN1HEADER_SECP256R1_COMPRESSED . "\0" . $key_raw);
			case self::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 1:
				return Pem::encode(self::ASN1HEADER_SECP256R1_UNCOMPRESSED . "\0" . $key_raw);
			case self::PUBKEYLEN_SECP384R1_COMPRESSED + 1:
				return Pem::encode(self::ASN1HEADER_SECP384R1_COMPRESSED . "\0" . $key_raw);
			case self::PUBKEYLEN_SECP384R1_UNCOMPRESSED + 1:
				return Pem::encode(self::ASN1HEADER_SECP384R1_UNCOMPRESSED . "\0" . $key_raw);
			case self::PUBKEYLEN_SECP521R1_COMPRESSED + 1:
				return Pem::encode(self::ASN1HEADER_SECP521R1_COMPRESSED . "\0" . $key_raw);
			case self::PUBKEYLEN_SECP521R1_UNCOMPRESSED + 1:
				return Pem::encode(self::ASN1HEADER_SECP521R1_UNCOMPRESSED . "\0" . $key_raw);
			default:
				return '';
		}
	}

	public static function stripAsn1Header(string $key_raw): string {
		if (strlen($key_raw) === 0 || $key_raw[0] !== "\x30") return $key_raw;
		$offset = 0;
		if (substr($key_raw, 0, strlen(self::ASN1HEADER_SECP256R1_UNCOMPRESSED)) === self::ASN1HEADER_SECP256R1_UNCOMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP256R1_UNCOMPRESSED);
		} elseif (substr($key_raw, 0, strlen(self::ASN1HEADER_SECP256R1_COMPRESSED)) === self::ASN1HEADER_SECP256R1_COMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP256R1_COMPRESSED);
		} elseif (substr($key_raw, 0, strlen(self::ASN1HEADER_SECP384R1_UNCOMPRESSED)) === self::ASN1HEADER_SECP384R1_UNCOMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP384R1_UNCOMPRESSED);
		} elseif (substr($key_raw, 0, strlen(self::ASN1HEADER_SECP384R1_COMPRESSED)) === self::ASN1HEADER_SECP384R1_COMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP384R1_COMPRESSED);
		} elseif (substr($key_raw, 0, strlen(self::ASN1HEADER_SECP521R1_UNCOMPRESSED)) === self::ASN1HEADER_SECP521R1_UNCOMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP521R1_UNCOMPRESSED);
		} elseif (substr($key_raw, 0, strlen(self::ASN1HEADER_SECP521R1_COMPRESSED)) === self::ASN1HEADER_SECP521R1_COMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP521R1_COMPRESSED);
		}
		switch (strlen($key_raw) - $offset) {
			case self::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 2:
			case self::PUBKEYLEN_SECP384R1_UNCOMPRESSED + 2:
			case self::PUBKEYLEN_SECP521R1_UNCOMPRESSED + 2:
			case self::PUBKEYLEN_SECP256R1_COMPRESSED + 2:
			case self::PUBKEYLEN_SECP384R1_COMPRESSED + 2:
			case self::PUBKEYLEN_SECP521R1_COMPRESSED + 2:
				$offset++;
				break;
		}
		return substr($key_raw, $offset);
	}

	public static function stripCompressionPrefix(string $key_raw): string {
		if (strlen($key_raw) === 0) return '';
		if ($key_raw[0] === "\x30") $key_raw = static::stripAsn1Header($key_raw);
		switch (strlen($key_raw)) {
			case self::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 1:
			case self::PUBKEYLEN_SECP384R1_UNCOMPRESSED + 1:
			case self::PUBKEYLEN_SECP521R1_UNCOMPRESSED + 1:
			case self::PUBKEYLEN_SECP256R1_COMPRESSED + 1:
			case self::PUBKEYLEN_SECP384R1_COMPRESSED + 1:
			case self::PUBKEYLEN_SECP521R1_COMPRESSED + 1:
				return substr($key_raw, 1);
		}
		return $key_raw;
	}

	public static function addCompressionPrefix(string $key_raw): string {
		if (strlen($key_raw) === 0) return '';
		switch ($key_raw[0]) {
			case self::PREFIX_UNCOMPRESSED:
			case self::PREFIX_EVEN:
			case self::PREFIX_ODD:
				break;
			default:
				$is_even = ord($key_raw[strlen($key_raw)-1]) % 2 === 0;
				if ($is_even) {
					$key_raw = self::PREFIX_EVEN . $key_raw;
				} else {
					$key_raw = self::PREFIX_ODD . $key_raw;
				}
				break;
		}
		return $key_raw;
	}

	public static function compressPubKey(string $key): string {
		$prefix = '';
		$key_raw = static::stripCompressionPrefix($key);
		switch (strlen($key_raw)) {
			case self::PUBKEYLEN_SECP256R1_COMPRESSED:
			case self::PUBKEYLEN_SECP384R1_COMPRESSED:
			case self::PUBKEYLEN_SECP521R1_COMPRESSED:
				return static::addCompressionPrefix($key_raw);
			case self::PUBKEYLEN_SECP256R1_UNCOMPRESSED:
				return static::addCompressionPrefix(substr($key_raw, 0, self::PUBKEYLEN_SECP256R1_COMPRESSED));
			case self::PUBKEYLEN_SECP384R1_UNCOMPRESSED:
				return static::addCompressionPrefix(substr($key_raw, 0, self::PUBKEYLEN_SECP384R1_COMPRESSED));
			case self::PUBKEYLEN_SECP521R1_UNCOMPRESSED:
				return static::addCompressionPrefix(substr($key_raw, 0, self::PUBKEYLEN_SECP521R1_COMPRESSED));
		}
		return $key;
	}

	/**
	 * Verifies an ECDSA signature with the given data and public key.
	 *
	 * @param string $data - Raw data which is used for the signature
	 * @param string $signature - Raw signature data
	 * @param string $public_key - ECDSA public key, in PEM or DER form
	 * @param $alg - OpenSSL algorithm constant
	 * @return bool
	 */
	public static function verify(string $data, string $signature, string $public_key, $alg = OPENSSL_ALGO_SHA256): bool {
		// PEM-encode public key if in raw DER form, so OpenSSL can use it
		$public_key = static::pubKeyToPem($public_key);
		if ($public_key === '') return false;

		// verify signature
		return openssl_verify($data, $signature, $public_key, $alg) === 1;
	}
}