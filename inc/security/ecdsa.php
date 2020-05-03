<?php namespace ZeroX\Security;
use ZeroX\Util;
if (!defined('IN_ZEROX')) {
	return;
}

class Ecdsa {
	const ASN1HEADER_SECP256R1 = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

	/**
	 * Verifies an ECDSA-SHA256 signature with the given data and public key
	 *
	 * @param string $data - Raw data which is used for the signature
	 * @param string $signature - Raw signature data
	 * @param string $public_key - ECDSA-SHA256 public key, in PEM or raw DER form
	 * @return bool
	 */
	public static function verify(string $data, string $signature, string $public_key): bool {
		// PEM-encode public key if in raw DER form, so OpenSSL can use it
		if (!Pem::isEncoded($public_key)) {
			$public_key = Pem::encode(self::ASN1HEADER_SECP256R1 . $public_key, Pem::LABEL_PUBLICKEY);
		}

		// verify signature of signature base
		return openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA256) === 1;
	}
}