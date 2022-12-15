<?php namespace Obie\Security;
use \Obie\Encoding\Pem;
use \Sop\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;

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

	public static function ecPubkeyToRaw(array $key, bool $compress = true): string {
		if (!array_key_exists(-2, $key)) return ''; // x coord
		if (!array_key_exists(-3, $key)) return ''; // y coord

		// Get X coordinate
		$x = $key[-2];

		// Get y coordinate
		$y = $key[-3];

		// Assemble raw ECDSA public key, format = compression prefix + X( + Y)
		return $compress ? Ecdsa::getCompressedPrefixForY($y) . $x : Ecdsa::PREFIX_UNCOMPRESSED . $x . $y;
	}

	public static function ecPubkeyToCurveOid(array $key): string {
		if (!array_key_exists(-1, $key)) return '';
		$curve = $key[-1];
		return match ($curve) {
			self::EC_P256 => ECPublicKeyAlgorithmIdentifier::CURVE_PRIME256V1,
			self::EC_P384 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP384R1,
			self::EC_P521 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP521R1,
			default => '',
		};
	}

	/**
	 * Converts a an EC public key from RFC 8152 COSE Key Object format
	 * to ITU X.690 DER format.
	 *
	 * @param array $key COSE Key Object
	 * @return string DER key or empty string if failed
	 */
	public static function ecPubkeyToDER(array $key, bool $compress = true): string {
		$curve_oid = static::ecPubkeyToCurveOid($key);
		if ($curve_oid === '') return '';
		$key_raw = static::ecPubkeyToRaw($key, $compress);
		if ($key_raw === '') return '';
		return Ecdsa::pubkeyToDER($key_raw, $curve_oid);
	}

	public static function ecPubkeyToPEM(array $key, bool $compress = true): string {
		$key_der = static::ecPubkeyToDER($key, $compress);
		if ($key_der === '') return '';
		return Pem::encode($key_der, Pem::LABEL_PUBLICKEY);
	}

	/**
	 * Get algorithm OpenSSL constant
	 *
	 * @param int $alg COSE algorithm identifier
	 */
	public static function getAlgOpenSSLConstant(int $alg): ?int {
		return match ($alg) {
			self::ALG_ES256 => OPENSSL_ALGO_SHA256,
			self::ALG_ES384 => OPENSSL_ALGO_SHA384,
			self::ALG_ES512 => OPENSSL_ALGO_SHA512,
			default => null,
		};
	}

	/**
	 * Verifies a signature against a specified algorithm and curve
	 *
	 * @param string $data Data to verify the signature of
	 * @param string $signature Raw cryptographic signature of data (not encoded)
	 * @param string $public_key Public key to verify signature with, in PEM or DER form
	 * @param int $alg COSE algorithm constant
	 * @param ?string $ecdsa_curve_oid_for_raw_key ECDSA curve OID that may optionally be specified to support $public_key in RAW form
	 * @return ?bool null if unsupported algorithm
	 */
	public static function verify(string $data, string $signature, string $public_key, int $alg, ?string $ecdsa_curve_oid_for_raw_key = null): ?bool {
		switch ($alg) {
		case self::ALG_ES256:
		case self::ALG_ES384:
		case self::ALG_ES512:
			$openssl_alg = static::getAlgOpenSSLConstant($alg);
			if ($openssl_alg === null) return false;
			return Ecdsa::verify($data, $signature, $public_key, $openssl_alg, $ecdsa_curve_oid_for_raw_key);
		default:
			return null;
		}
	}
}