<?php namespace ZeroX\Security;
use ZeroX\Util;
if (!defined('IN_ZEROX')) {
	return;
}

class Cose {
	const KTY_RESERVED = 0; // This value is reserved
	const KTY_OKP = 1; // Octet Key Pair
	const KTY_EC2 = 2; // Elliptic Curve Keys w/ x- and y-coordinate pair
	const KTY_SYMMETRIC = 4; // Symmetric Keys

	const ALG_HMAC_SHA256_64 = 4; // HMAC w/ SHA-256 truncated to 64 bits
	const ALG_HMAC_SHA256 = 5; // HMAC w/ SHA-256
	const ALG_HMAC_SHA384 = 6; // HMAC w/ SHA-384
	const ALG_HMAC_SHA512 = 7; // HMAC w/ SHA-512
	const ALG_AESMAC_128_64 = 14; // AES-MAC 128-bit key, 64-bit tag
	const ALG_AESMAC_256_64 = 15; // AES-MAC 256-bit key, 64-bit tag
	const ALG_AESMAC_128_128 = 25; // AES-MAC 128-bit key, 128-bit tag
	const ALG_AESMAC_256_128 = 26; // AES-MAC 256-bit key, 128-bit tag
	const ALG_AESCCM_16_64_128 = 10; // AES-CCM mode 128-bit key, 64-bit tag, 13-byte nonce
	const ALG_AESCCM_16_64_256 = 11; // AES-CCM mode 256-bit key, 64-bit tag, 13-byte nonce
	const ALG_AESCCM_64_64_128 = 12; // AES-CCM mode 128-bit key, 64-bit tag, 7-byte nonce
	const ALG_AESCCM_64_64_256 = 13; // AES-CCM mode 256-bit key, 64-bit tag, 7-byte nonce
	const ALG_AESCCM_16_128_128 = 30; // AES-CCM mode 128-bit key, 128-bit tag, 13-byte nonce
	const ALG_AESCCM_16_128_256 = 31; // AES-CCM mode 256-bit key, 128-bit tag, 13-byte nonce
	const ALG_AESCCM_64_128_128 = 32; // AES-CCM mode 128-bit key, 128-bit tag, 7-byte nonce
	const ALG_AESCCM_64_128_256 = 33; // AES-CCM mode 256-bit key, 128-bit tag, 7-byte nonce
	const ALG_A128KW = -3; // AES Key Wrap w/ 128-bit key
	const ALG_A192KW = -4; // AES Key Wrap w/ 192-bit key
	const ALG_A256KW = -5; // AES Key Wrap w/ 256-bit key
	const ALG_ECDH_ES_HKDF_SHA256 = -25; // ECDH ES w/ HKDF - generate key directly
	const ALG_ECDH_ES_HKDF_SHA512 = -26; // ECDH ES w/ HKDF - generate key directly
	const ALG_ECDH_SS_HKDF_SHA256 = -27; // ECDH SS w/ HKDF - generate key directly
	const ALG_ECDH_SS_HKDF_SHA512 = -28; // ECDH SS w/ HKDF - generate key directly
	const ALG_ECDH_ES_HKDF_SHA256_A128KW = -29; // ECDH ES w/ Concat KDF and AES Key Wrap w/ 128-bit key
	const ALG_ECDH_ES_HKDF_SHA256_A192KW = -30; // ECDH ES w/ Concat KDF and AES Key Wrap w/ 192-bit key
	const ALG_ECDH_ES_HKDF_SHA256_A256KW = -31; // ECDH ES w/ Concat KDF and AES Key Wrap w/ 256-bit key
	const ALG_ECDH_SS_HKDF_SHA256_A128KW = -32; // ECDH SS w/ Concat KDF and AES Key Wrap w/ 128-bit key
	const ALG_ECDH_SS_HKDF_SHA256_A192KW = -33; // ECDH SS w/ Concat KDF and AES Key Wrap w/ 192-bit key
	const ALG_ECDH_SS_HKDF_SHA256_A256KW = -34; // ECDH SS w/ Concat KDF and AES Key Wrap w/ 256-bit key
	const ALG_ES256 = -7; // ECDSA w/ SHA-256
	const ALG_ES384 = -35; // ECDSA w/ SHA-384
	const ALG_ES512 = -36; // ECDSA w/ SHA-512
	const ALG_EDDSA = -8; // EdDSA

	const EC_P256 = 1; // NIST P-256 also known as secp256r1
	const EC_P384 = 2; // NIST P-384 also known as secp384r1
	const EC_P521 = 3; // NIST P-521 also known as secp521r1
	const EC_X25519 = 4; // X25519 for use w/ ECDH only
	const EC_X448 = 5; // X448 for use w/ ECDH only
	const EC_ED25519 = 6; // Ed25519 for use w/ EdDSA only
	const EC_ED448 = 7; // Ed448 for use w/ EdDSA only

	/**
	 * Converts a an EC public key from RFC 8152 COSE Key Object format
	 * to ITU X.690 DER format.
	 *
	 * @param array $key - COSE Key Object
	 * @return string DER key or empty string if failed
	 */
	public static function ecPubKeyToDER(array $key, bool $include_asn_header = true): string {
		if (!array_key_exists(3, $key)) return ''; // curve
		if (!array_key_exists(-2, $key)) return ''; // x coord
		if (!array_key_exists(-3, $key)) return ''; // y coord

		// Get X coordinate
		$x = $key[-2];

		// Get y coordinate
		$y = $key[-3];

		// Assemble raw ECDSA public key, format = compression prefix + X + Y
		$key_raw = Ecdsa::PREFIX_UNCOMPRESSED . $x . $y;

		// Return early if ASN.1 header not requested
		if (!$include_asn_header) return $key_raw;

		// Get curve
		$curve = null;
		switch ($key[3]) {
			case 'ES256':
			case self::ALG_ES256:
				$curve = array_key_exists(-1, $key) ? $key[-1] : self::EC_P256;
				break;
			case 'ES384':
			case self::ALG_ES384:
				$curve = array_key_exists(-1, $key) ? $key[-1] : self::EC_P384;
				break;
			case 'ES512':
			case self::ALG_ES512:
				$curve = array_key_exists(-1, $key) ? $key[-1] : self::EC_P521;
				break;
			default:
				return '';
		}

		// Get public key header based on curve
		$hdr = '';
		switch ($curve) {
			case self::EC_P256:
				$hdr = Ecdsa::ASN1HEADER_SECP256R1;
				break;
			case self::EC_P384:
				$hdr = Ecdsa::ASN1HEADER_SECP384R1;
				break;
			case self::EC_P521:
				$hdr = Ecdsa::ASN1HEADER_SECP521R1;
				break;
			default:
				return '';
		}

		// DER form = ASN.1 header + sign byte + raw key (see above)
		return $hdr . "\0" . $key_raw;
	}

	/**
	 * Get algorithm OpenSSL constant
	 *
	 * @param int $alg - COSE algorithm identifier
	 */
	public static function getAlgOpenSSLConstant(int $alg) {
		switch ($alg) {
			case self::ALG_ES256:
				return OPENSSL_ALGO_SHA256;
			case self::ALG_ES384:
				return OPENSSL_ALGO_SHA384;
			case self::ALG_ES512:
				return OPENSSL_ALGO_SHA512;
			default:
				return null;
		}
	}

	/**
	 * Verifies a signature against a specified algorithm
	 *
	 * @param string $data - Data to verify the signature of
	 * @param string $signature - Raw cryptographic signature of data (not encoded)
	 * @param string $public_key - Public key to verify signature with, in PEM or DER form
	 * @param int $alg - COSE algorithm constant
	 */
	public static function verify(string $data, string $signature, string $public_key, int $alg) {
		switch ($alg) {
			case self::ALG_ES256:
			case self::ALG_ES384:
			case self::ALG_ES512:
				$openssl_alg = static::getAlgOpenSSLConstant($alg);
				return Ecdsa::verify($data, $signature, $public_key, $openssl_alg);
			default:
				return null;
		}
	}
}