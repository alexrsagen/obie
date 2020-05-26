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

	protected static function stripAsn1Header(string $key): string {
		if (strlen($key) === 0 || $key[0] !== "\x30") return $key;
		$offset = 0;
		if (substr($key, 0, strlen(self::ASN1HEADER_SECP256R1_UNCOMPRESSED)) === self::ASN1HEADER_SECP256R1_UNCOMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP256R1_UNCOMPRESSED);
		} elseif (substr($key, 0, strlen(self::ASN1HEADER_SECP256R1_COMPRESSED)) === self::ASN1HEADER_SECP256R1_COMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP256R1_COMPRESSED);
		} elseif (substr($key, 0, strlen(self::ASN1HEADER_SECP384R1_UNCOMPRESSED)) === self::ASN1HEADER_SECP384R1_UNCOMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP384R1_UNCOMPRESSED);
		} elseif (substr($key, 0, strlen(self::ASN1HEADER_SECP384R1_COMPRESSED)) === self::ASN1HEADER_SECP384R1_COMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP384R1_COMPRESSED);
		} elseif (substr($key, 0, strlen(self::ASN1HEADER_SECP521R1_UNCOMPRESSED)) === self::ASN1HEADER_SECP521R1_UNCOMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP521R1_UNCOMPRESSED);
		} elseif (substr($key, 0, strlen(self::ASN1HEADER_SECP521R1_COMPRESSED)) === self::ASN1HEADER_SECP521R1_COMPRESSED) {
			$offset = strlen(self::ASN1HEADER_SECP521R1_COMPRESSED);
		}
		if (substr($key, $offset, 1) === "\0") {
			switch (strlen($key) - $offset) {
				case self::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 2:
				case self::PUBKEYLEN_SECP384R1_UNCOMPRESSED + 2:
				case self::PUBKEYLEN_SECP521R1_UNCOMPRESSED + 2:
				case self::PUBKEYLEN_SECP256R1_COMPRESSED + 2:
				case self::PUBKEYLEN_SECP384R1_COMPRESSED + 2:
				case self::PUBKEYLEN_SECP521R1_COMPRESSED + 2:
					$offset++;
					break;
			}
		}
		return substr($key, $offset);
	}

	protected static function getAsn1Header(string $key_raw): string {
		switch (strlen($key_raw)) {
			case self::PUBKEYLEN_SECP256R1_COMPRESSED + 1:
				return self::ASN1HEADER_SECP256R1_COMPRESSED;
			case self::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 1:
				return self::ASN1HEADER_SECP256R1_UNCOMPRESSED;
			case self::PUBKEYLEN_SECP384R1_COMPRESSED + 1:
				return self::ASN1HEADER_SECP384R1_COMPRESSED;
			case self::PUBKEYLEN_SECP384R1_UNCOMPRESSED + 1:
				return self::ASN1HEADER_SECP384R1_UNCOMPRESSED;
			case self::PUBKEYLEN_SECP521R1_COMPRESSED + 1:
				return self::ASN1HEADER_SECP521R1_COMPRESSED;
			case self::PUBKEYLEN_SECP521R1_UNCOMPRESSED + 1:
				return self::ASN1HEADER_SECP521R1_UNCOMPRESSED;
			default:
				return '';
		}
	}

	protected static function getPointLength(string $key_raw): int {
		switch (strlen($key_raw)) {
			case self::PUBKEYLEN_SECP256R1_COMPRESSED + 1:
			case self::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 1:
				return self::PUBKEYLEN_SECP256R1_COMPRESSED;
			case self::PUBKEYLEN_SECP384R1_COMPRESSED + 1:
			case self::PUBKEYLEN_SECP384R1_UNCOMPRESSED + 1:
				return self::PUBKEYLEN_SECP384R1_COMPRESSED;
			case self::PUBKEYLEN_SECP521R1_COMPRESSED + 1:
			case self::PUBKEYLEN_SECP521R1_UNCOMPRESSED + 1:
				return self::PUBKEYLEN_SECP521R1_COMPRESSED;
			default:
				return 0;
		}
	}

	public static function getCompressedPrefixForY(string $y): string {
		return ord(substr($y, -1)) % 2 === 0 ? self::PREFIX_EVEN : self::PREFIX_ODD;
	}

	public static function pubkeyIsRaw(string $key): bool {
		switch (substr($key, 0, 1)) {
			case self::PREFIX_UNCOMPRESSED:
			case self::PREFIX_EVEN:
			case self::PREFIX_ODD:
				return true;
		}
		return false;
	}

	public static function pubkeyIsDER(string $key): bool {
		return substr($key, 0, 1) === "\x30";
	}

	public static function pubkeyToRaw(string $key, bool $compress = true): string {
		if (static::pubkeyIsRaw($key)) goto DONE;
		if (!static::pubkeyIsDER($key) && Pem::isEncoded($key)) {
			$label = Pem::decodeLabel($key);
			if ($label === Pem::LABEL_CERTIFICATE) {
				$key_res = openssl_pkey_get_public($key);
				if ($key_res === false) return '';
				$key_details = openssl_pkey_get_details($key_res);
				openssl_free_key($key_res);
				if (!is_array($key_details) || !array_key_exists('ec', $key_details)) return '';
				$key = $key_details['key'];
			}
			$key = Pem::decode($key);
		}
		$key = static::stripAsn1Header($key);
	DONE:
		return $compress ? static::compressPubkey($key) : $key;
	}

	public static function pubkeyToDER(string $key, bool $compress = true): string {
		if (!$compress && static::pubkeyIsDER($key)) return $key;
		$key = static::pubkeyToRaw($key, $compress);
		if ($key === '') return '';
		$hdr = static::getAsn1Header($key);
		if ($hdr === '') return '';
		return $hdr . "\0" . $key;
	}

	public static function pubkeyToPEM(string $key, bool $compress = true): string {
		if (!$compress && Pem::isEncoded($key) && Pem::decodeLabel($key) !== Pem::LABEL_CERTIFICATE) return $key;
		$der = static::pubkeyToDER($key, $compress);
		if ($der === '') return '';
		return Pem::encode($der, Pem::LABEL_PUBLICKEY);
	}

	public static function compressPubkey(string $key): string {
		$key = static::pubkeyToRaw($key, false);
		if ($key === '') return '';
		$prefix = substr($key, 0, 1);
		switch ($prefix) {
			case self::PREFIX_EVEN:
			case self::PREFIX_ODD:
				return $key;
			case self::PREFIX_UNCOMPRESSED:
				$plen = static::getPointLength($key);
				if ($plen === 0) return $key;
				$x = substr($key, 1, $plen);
				$y = substr($key, 1 + $plen);
				$prefix = static::getCompressedPrefixForY($y);
				return $prefix . $x;
			default:
				return ''; // invalid prefix
		}
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
		$public_key = static::pubkeyToPem($public_key);
		if ($public_key === '') return false;

		// verify signature
		return openssl_verify($data, $signature, $public_key, $alg) === 1;
	}
}