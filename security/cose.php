<?php namespace Obie\Security;
use Sop\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;
use Sop\CryptoTypes\Asymmetric\RSA\RSAPublicKey;
use Obie\Encoding\Asn1;
use Obie\Encoding\Pem;
use Obie\Log;

class Cose {
	// https://www.iana.org/assignments/cose/cose.xhtml#key-common-parameters
	const KEY_COMMON_PARAM_RESERVED = 0;
	const KEY_COMMON_PARAM_KTY = 1;
	const KEY_COMMON_PARAM_KID = 2;
	const KEY_COMMON_PARAM_ALG = 3;
	const KEY_COMMON_PARAM_KEY_OPS = 4;
	const KEY_COMMON_PARAM_BASE_IV = 5;

	// https://www.iana.org/assignments/cose/cose.xhtml#key-type-parameters
	const KEY_TYPE_PARAM_RESERVED = 0;

	const KEY_TYPE_PARAM_OKP_CRV = -1; // EC identifier -- Taken from the "COSE Elliptic Curves" registry
	const KEY_TYPE_PARAM_OKP_X = -2; // Public Key
	const KEY_TYPE_PARAM_OKP_D = -4; // Private key

	const KEY_TYPE_PARAM_EC2_CRV = -1; // EC identifier -- Taken from the "COSE Elliptic Curves" registry
	const KEY_TYPE_PARAM_EC2_X = -2; // x-coordinate
	const KEY_TYPE_PARAM_EC2_Y = -3; // y-coordinate
	const KEY_TYPE_PARAM_EC2_D = -4; // Private key

	const KEY_TYPE_PARAM_RSA_N = -1; // the RSA modulus n
	const KEY_TYPE_PARAM_RSA_E = -2; // the RSA public exponent e
	const KEY_TYPE_PARAM_RSA_D = -3; // the RSA private exponent d
	const KEY_TYPE_PARAM_RSA_P = -4; // the prime factor p of n
	const KEY_TYPE_PARAM_RSA_Q = -5; // the prime factor q of n
	const KEY_TYPE_PARAM_RSA_DP = -6; // dP is d mod (p - 1)
	const KEY_TYPE_PARAM_RSA_DQ = -7; // dQ is d mod (q - 1)
	const KEY_TYPE_PARAM_RSA_QINV = -8; // qInv is the CRT coefficient q^(-1) mod p
	const KEY_TYPE_PARAM_RSA_OTHER = -9; // other prime infos, an array
	const KEY_TYPE_PARAM_RSA_R_I = -10; // a prime factor r_i of n, where i >= 3
	const KEY_TYPE_PARAM_RSA_D_I = -11; // d_i = d mod (r_i - 1)
	const KEY_TYPE_PARAM_RSA_T_I = -12; // the CRT coefficient t_i = (r_1 * r_2 * ... * r_(i-1))^(-1) mod r_i

	const KEY_TYPE_PARAM_SYMMETRIC_K = -1; // Key Value

	const KEY_TYPE_PARAM_HSS_LMS_PUB = -1; // Public key for HSS/LMS hash-based digital signature

	const KEY_TYPE_PARAM_WALNUT_DSA_N = -1; // Group and Matrix (NxN) size
	const KEY_TYPE_PARAM_WALNUT_DSA_Q = -2; // Finite field F_q
	const KEY_TYPE_PARAM_WALNUT_DSA_T_VALUES = -3; // List of T-values, entries in F_q
	const KEY_TYPE_PARAM_WALNUT_DSA_MATRIX_1 = -4; // NxN Matrix of entries in F_q in column-major form
	const KEY_TYPE_PARAM_WALNUT_DSA_PERMUTATION_1 = -5; // Permutation associated with matrix 1
	const KEY_TYPE_PARAM_WALNUT_DSA_MATRIX_2 = -6; // NxN Matrix of entries in F_q in column-major form

	// https://www.iana.org/assignments/cose/cose.xhtml#key-type
	const KTY_RESERVED = 0; // This value is reserved
	const KTY_OKP = 1; // Octet Key Pair
	const KTY_EC2 = 2; // Elliptic Curve Keys w/ x- and y-coordinate pair
	const KTY_RSA = 3; // RSA Key
	const KTY_SYMMETRIC = 4; // Symmetric Keys
	const KTY_HSS_LMS = 5; // Public key for HSS/LMS hash-based digital signature
	const KTY_WALNUT_DSA = 6; // WalnutDSA public key

	// https://www.iana.org/assignments/cose/cose.xhtml#algorithms
	const ALG_RS1 = -65535; // RSASSA-PKCS1-v1_5 using SHA-1
	const ALG_RS512 = -259; // RSASSA-PKCS1-v1_5 using SHA-512
	const ALG_RS384 = -258; // RSASSA-PKCS1-v1_5 using SHA-384
	const ALG_RS256 = -257; // RSASSA-PKCS1-v1_5 using SHA-256

	const ALG_PS512 = -39; // RSASSA-PSS w/ SHA-512
	const ALG_PS384 = -38; // RSASSA-PSS w/ SHA-384
	const ALG_PS256 = -37; // RSASSA-PSS w/ SHA-256

	const ALG_RSAES_OAEP_SHA512 = -42; // RSAES-OAEP w/ SHA-512
	const ALG_RSAES_OAEP_SHA256 = -41; // RSAES-OAEP w/ SHA-256
	const ALG_RSAES_OAEP_RFC_8017 = -40; // RSAES-OAEP w/ SHA-1

	const ALG_A128CTR = -65534; // AES-CTR w/ 128-bit key
	const ALG_A192CTR = -65533; // AES-CTR w/ 192-bit key
	const ALG_A256CTR = -65532; // AES-CTR w/ 256-bit key

	const ALG_A128CBC = -65531; // AES-CBC w/ 128-bit key
	const ALG_A192CBC = -65530; // AES-CBC w/ 192-bit key
	const ALG_A256CBC = -65529; // AES-CBC w/ 256-bit key

	const ALG_A128GCM = 1; // AES-GCM mode w/ 128-bit key, 128-bit tag
	const ALG_A192GCM = 2; // AES-GCM mode w/ 192-bit key, 128-bit tag
	const ALG_A256GCM = 3; // AES-GCM mode w/ 256-bit key, 128-bit tag

	const ALG_AESCCM_16_64_128 = 10; // AES-CCM mode 128-bit key, 64-bit tag, 13-byte nonce
	const ALG_AESCCM_16_64_256 = 11; // AES-CCM mode 256-bit key, 64-bit tag, 13-byte nonce
	const ALG_AESCCM_64_64_128 = 12; // AES-CCM mode 128-bit key, 64-bit tag, 7-byte nonce
	const ALG_AESCCM_64_64_256 = 13; // AES-CCM mode 256-bit key, 64-bit tag, 7-byte nonce

	const ALG_AESMAC_128_64 = 14; // AES-MAC 128-bit key, 64-bit tag
	const ALG_AESMAC_256_64 = 15; // AES-MAC 256-bit key, 64-bit tag
	const ALG_AESMAC_128_128 = 25; // AES-MAC 128-bit key, 128-bit tag
	const ALG_AESMAC_256_128 = 26; // AES-MAC 256-bit key, 128-bit tag

	const ALG_AESCCM_16_128_128 = 30; // AES-CCM mode 128-bit key, 128-bit tag, 13-byte nonce
	const ALG_AESCCM_16_128_256 = 31; // AES-CCM mode 256-bit key, 128-bit tag, 13-byte nonce
	const ALG_AESCCM_64_128_128 = 32; // AES-CCM mode 128-bit key, 128-bit tag, 7-byte nonce
	const ALG_AESCCM_64_128_256 = 33; // AES-CCM mode 256-bit key, 128-bit tag, 7-byte nonce

	const ALG_A256KW = -5; // AES Key Wrap w/ 256-bit key
	const ALG_A192KW = -4; // AES Key Wrap w/ 192-bit key
	const ALG_A128KW = -3; // AES Key Wrap w/ 128-bit key

	const ALG_ES256 = -7; // ECDSA w/ SHA-256
	const ALG_ES256K = -47; // ECDSA using secp256k1 curve and SHA-256
	const ALG_ES512 = -36; // ECDSA w/ SHA-512
	const ALG_ES384 = -35; // ECDSA w/ SHA-384

	const ALG_WALNUT_DSA = -260; // WalnutDSA signature

	const ALG_EDDSA = -8; // EdDSA

	const ALG_ECDH_SS_HKDF_SHA256_A256KW = -34; // ECDH SS w/ Concat KDF and AES Key Wrap w/ 256-bit key
	const ALG_ECDH_SS_HKDF_SHA256_A192KW = -33; // ECDH SS w/ Concat KDF and AES Key Wrap w/ 192-bit key
	const ALG_ECDH_SS_HKDF_SHA256_A128KW = -32; // ECDH SS w/ Concat KDF and AES Key Wrap w/ 128-bit key
	const ALG_ECDH_ES_HKDF_SHA256_A256KW = -31; // ECDH ES w/ Concat KDF and AES Key Wrap w/ 256-bit key
	const ALG_ECDH_ES_HKDF_SHA256_A192KW = -30; // ECDH ES w/ Concat KDF and AES Key Wrap w/ 192-bit key
	const ALG_ECDH_ES_HKDF_SHA256_A128KW = -29; // ECDH ES w/ Concat KDF and AES Key Wrap w/ 128-bit key

	const ALG_ECDH_SS_HKDF_SHA512 = -28; // ECDH SS w/ HKDF - generate key directly
	const ALG_ECDH_SS_HKDF_SHA256 = -27; // ECDH SS w/ HKDF - generate key directly
	const ALG_ECDH_ES_HKDF_SHA512 = -26; // ECDH ES w/ HKDF - generate key directly
	const ALG_ECDH_ES_HKDF_SHA256 = -25; // ECDH ES w/ HKDF - generate key directly

	const ALG_DIRECT = -6; // Direct use of CEK
	const ALG_DIRECT_HKDF_AES256 = -13; // Shared secret w/ AES-MAC 256-bit key
	const ALG_DIRECT_HKDF_AES128 = -12; // Shared secret w/ AES-MAC 128-bit key
	const ALG_DIRECT_HKDF_SHA512 = -11; // Shared secret w/ HKDF and SHA-512
	const ALG_DIRECT_HKDF_SHA256 = -10; // Shared secret w/ HKDF and SHA-256

	const ALG_HSS_LMS = -46; // HSS/LMS hash-based digital signature

	const ALG_CHACHA20_POLY1305 = 24; // ChaCha20/Poly1305 w/ 256-bit key, 128-bit tag

	const ALG_HMAC_SHA256 = 5; // HMAC w/ SHA-256
	const ALG_HMAC_SHA256_64 = 4; // HMAC w/ SHA-256 truncated to 64 bits
	const ALG_HMAC_SHA384 = 6; // HMAC w/ SHA-384
	const ALG_HMAC_SHA512 = 7; // HMAC w/ SHA-512

	const ALG_SHAKE128 = -18; // SHAKE-128 256-bit Hash Value
	const ALG_SHAKE256 = -45; // SHAKE-256 512-bit Hash Value

	const ALG_SHA1 = -14; // SHA-1 Hash
	const ALG_SHA256 = -16; // SHA-2 256-bit Hash
	const ALG_SHA256_64 = -15; // SHA-2 256-bit Hash truncated to 64-bits
	const ALG_SHA384 = -43; // SHA-2 384-bit Hash
	const ALG_SHA512 = -44; // SHA-2 512-bit Hash
	const ALG_SHA512_256 = -17; // SHA-2 512-bit Hash truncated to 256-bits

	const ALG_IV_GENERATION = 34; // For doing IV generation for symmetric algorithms

	// https://www.iana.org/assignments/cose/cose.xhtml#elliptic-curves
	const EC_P256 = 1; // NIST P-256 also known as secp256r1
	const EC_P384 = 2; // NIST P-384 also known as secp384r1
	const EC_P521 = 3; // NIST P-521 also known as secp521r1
	const EC_X25519 = 4; // X25519 for use w/ ECDH only
	const EC_X448 = 5; // X448 for use w/ ECDH only
	const EC_ED25519 = 6; // Ed25519 for use w/ EdDSA only
	const EC_ED448 = 7; // Ed448 for use w/ EdDSA only
	const EC_SECP256K1 = 8; // SECG secp256k1 curve

	public static function ecPubkeyToRaw(array $key, bool $compress = true): ?string {
		if (!array_key_exists(self::KEY_COMMON_PARAM_KTY, $key)) return null;
		if ($key[self::KEY_COMMON_PARAM_KTY] !== self::KTY_EC2) return null;

		if (!array_key_exists(self::KEY_TYPE_PARAM_EC2_X, $key)) return null; // x coord
		if (!array_key_exists(self::KEY_TYPE_PARAM_EC2_Y, $key)) return null; // y coord

		// Get X coordinate
		$x = $key[self::KEY_TYPE_PARAM_EC2_X];

		// Get y coordinate
		$y = $key[self::KEY_TYPE_PARAM_EC2_Y];

		// Assemble raw ECDSA public key, format = compression prefix + X( + Y)
		return $compress ? Ecdsa::getCompressedPrefixForY($y) . $x : Ecdsa::PREFIX_UNCOMPRESSED . $x . $y;
	}

	public static function ecPubkeyToCurveOid(array $key): ?string {
		if (!array_key_exists(self::KEY_COMMON_PARAM_KTY, $key)) return null;
		if ($key[self::KEY_COMMON_PARAM_KTY] !== self::KTY_EC2) return null;

		if (!array_key_exists(self::KEY_TYPE_PARAM_EC2_CRV, $key)) return null;
		$curve = $key[self::KEY_TYPE_PARAM_EC2_CRV];

		return match ($curve) {
			self::EC_P256 => ECPublicKeyAlgorithmIdentifier::CURVE_PRIME256V1,
			self::EC_P384 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP384R1,
			self::EC_P521 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP521R1,
			self::EC_SECP256K1 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP256K1,
			default => null,
		};
	}

	/**
	 * Converts a an EC public key from RFC 8152 COSE Key Object format
	 * to ITU X.690 DER format.
	 *
	 * @param array $key COSE Key Object
	 * @return string DER key or null if failed
	 */
	public static function ecPubkeyToDER(array $key, bool $compress = true): ?string {
		if (!array_key_exists(self::KEY_COMMON_PARAM_KTY, $key)) return null;
		if ($key[self::KEY_COMMON_PARAM_KTY] !== self::KTY_EC2) return null;

		$curve_oid = static::ecPubkeyToCurveOid($key);
		if ($curve_oid === null) return null;

		$key_raw = static::ecPubkeyToRaw($key, $compress);
		if ($key_raw === null) return null;

		return Ecdsa::pubkeyToDER($key_raw, $curve_oid);
	}

	/**
	 * Converts a an RSA public key from RFC 8152 COSE Key Object format
	 * to ITU X.690 DER format.
	 *
	 * @param array $key COSE Key Object
	 * @return string DER key or null if failed
	 */
	public static function rsaPubkeyToDER(array $key): ?string {
		if (!array_key_exists(self::KEY_COMMON_PARAM_KTY, $key)) return null;
		if ($key[self::KEY_COMMON_PARAM_KTY] !== self::KTY_RSA) return null;

		if (!array_key_exists(self::KEY_TYPE_PARAM_RSA_N, $key) || !is_string($key[self::KEY_TYPE_PARAM_RSA_N])) return null;
		if (!array_key_exists(self::KEY_TYPE_PARAM_RSA_E, $key) || !is_string($key[self::KEY_TYPE_PARAM_RSA_E])) return null;
		$public_key = new RSAPublicKey($key[self::KEY_TYPE_PARAM_RSA_N], $key[self::KEY_TYPE_PARAM_RSA_E]);
		return $public_key->toDER();
	}

	public static function ecPubkeyToPEM(array $key, bool $compress = true): ?string {
		$key_der = static::ecPubkeyToDER($key, $compress);
		if ($key_der === null) return null;
		return Pem::encode($key_der, Pem::LABEL_PUBLICKEY);
	}

	/**
	 * Converts a a public key from RFC 8152 COSE Key Object format
	 * to ITU X.690 DER format.
	 *
	 * @param array $key COSE Key Object
	 * @return string|null DER key or null if failed
	 */
	public static function pubkeyToDER(array $key, bool $compress_ec = true): ?string {
		if (!array_key_exists(self::KEY_COMMON_PARAM_KTY, $key)) return null;
		return match ($key[self::KEY_COMMON_PARAM_KTY]) {
			self::KTY_EC2 => self::ecPubkeyToDER($key, $compress_ec),
			self::KTY_RSA => self::rsaPubkeyToDER($key),
			self::KTY_SYMMETRIC => array_key_exists(self::KEY_TYPE_PARAM_SYMMETRIC_K, $key) ? $key[self::KEY_TYPE_PARAM_SYMMETRIC_K] : null,
			default => null,
		};
	}

	public static function pubkeyToPEM(array $key, bool $compress_ec = true): ?string {
		$key_der = static::pubkeyToDER($key, $compress_ec);
		if ($key_der === null) return null;
		return Pem::encode($key_der, Pem::LABEL_PUBLICKEY);
	}

	/**
	 * Get algorithm OpenSSL constant
	 *
	 * @param int $alg COSE algorithm identifier
	 */
	public static function getAlgOpenSSLConstant(int $alg): ?int {
		return match ($alg) {
			self::ALG_RS1,
			self::ALG_RSAES_OAEP_RFC_8017,
			self::ALG_SHA1,
				=> OPENSSL_ALGO_SHA1,

			self::ALG_DIRECT_HKDF_SHA256,
			self::ALG_ES256,
			self::ALG_ES256K,
			self::ALG_HMAC_SHA256,
			self::ALG_PS256,
			self::ALG_RS256,
			self::ALG_RSAES_OAEP_SHA256,
			self::ALG_SHA256,
				=> OPENSSL_ALGO_SHA256,

			self::ALG_ES384,
			self::ALG_HMAC_SHA384,
			self::ALG_PS384,
			self::ALG_RS384,
			self::ALG_SHA384,
				=> OPENSSL_ALGO_SHA384,

			self::ALG_DIRECT_HKDF_SHA512,
			self::ALG_ES512,
			self::ALG_HMAC_SHA512,
			self::ALG_PS512,
			self::ALG_RS512,
			self::ALG_RSAES_OAEP_SHA512,
			self::ALG_SHA512,
				=> OPENSSL_ALGO_SHA512,

			default => null,
		};
	}

	/**
	 * Get algorithm PHP string
	 *
	 * @param int $alg COSE algorithm identifier
	 */
	public static function getAlgPHPString(int $alg): ?string {
		return match ($alg) {
			self::ALG_RS1,
			self::ALG_RSAES_OAEP_RFC_8017,
			self::ALG_SHA1,
				=> 'sha1',

			self::ALG_DIRECT_HKDF_SHA256,
			self::ALG_ES256,
			self::ALG_ES256K,
			self::ALG_HMAC_SHA256,
			self::ALG_HMAC_SHA256_64,
			self::ALG_PS256,
			self::ALG_RS256,
			self::ALG_RSAES_OAEP_SHA256,
			self::ALG_SHA256,
			self::ALG_SHA256_64,
				=> 'sha256',

			self::ALG_ES384,
			self::ALG_HMAC_SHA384,
			self::ALG_PS384,
			self::ALG_RS384,
			self::ALG_SHA384,
				=> 'sha384',

			self::ALG_DIRECT_HKDF_SHA512,
			self::ALG_ES512,
			self::ALG_HMAC_SHA512,
			self::ALG_PS512,
			self::ALG_RS512,
			self::ALG_RSAES_OAEP_SHA512,
			self::ALG_SHA512,
				=> 'sha512',

			self::ALG_SHA512_256 => 'sha512/256',

			default => null,
		};
	}

	/**
	 * Hashes data using the hash algorithm employed in a specified COSE algorithm
	 *
	 * @param string $data Data to verify the signature of
	 * @param int $alg COSE algorithm constant
	 * @return ?string null if unsupported algorithm
	 */
	public static function hash(string $data, int $alg): ?string {
		$php_alg = static::getAlgPHPString($alg);
		if ($php_alg === null || !in_array($php_alg, hash_algos())) {
			Log::info(sprintf('Cose/verify: COSE algorithm %d is not supported', $alg));
			return null;
		}
		$hash = hash($php_alg, $data, true);
		if ($alg === self::ALG_HMAC_SHA256_64 || $alg === self::ALG_SHA256_64) {
			return substr($hash, 0, 8);
		} elseif ($alg === self::ALG_SHA512_256) {
			return substr($hash, 0, 32);
		}
		return $hash;
	}

	/**
	 * Verifies a signature against a specified COSE algorithm (and optionally ECDSA curve, if raw keys are to be supported)
	 *
	 * @param string $data Data to verify the signature of
	 * @param string $signature Raw cryptographic signature of data (not encoded)
	 * @param string $public_key Public key to verify signature with, in PEM or DER form (or a HMAC secret string, depending on $alg)
	 * @param int $alg COSE algorithm constant
	 * @param ?string $ecdsa_curve_oid_for_raw_key ECDSA curve OID that may optionally be specified to support $public_key in raw form
	 * @return ?bool null if unsupported algorithm or bad public key
	 */
	public static function verify(string $data, string $signature, string $public_key, int $alg, ?string $ecdsa_curve_oid_for_raw_key = null): ?bool {
		switch ($alg) {
		// ECDSA
		case self::ALG_ES256:
		case self::ALG_ES256K:
		case self::ALG_ES384:
		case self::ALG_ES512:
			$openssl_alg = static::getAlgOpenSSLConstant($alg);
			if ($openssl_alg === null) return null;
			return Ecdsa::verify($data, $signature, $public_key, $openssl_alg, $ecdsa_curve_oid_for_raw_key);

		// RSASSA-PKCS1-v1_5
		case self::ALG_RS512:
		case self::ALG_RS384:
		case self::ALG_RS256:
		case self::ALG_RS1:
			$openssl_alg = static::getAlgOpenSSLConstant($alg);
			if ($openssl_alg === null) return null;
			if (Pem::isEncoded($public_key)) {
				$public_key = openssl_pkey_get_public($public_key);
			} elseif (Asn1::isSequence($public_key)) {
				$public_key = openssl_pkey_get_public(Pem::encode($public_key, Pem::LABEL_PUBLICKEY));
			} else {
				$public_key = false;
			}
			if (!$public_key) {
				Log::info('Cose/verify: Public key not in PEM or DER form');
				return null;
			}
			return openssl_verify($data, $signature, $public_key, $openssl_alg);

		// HMAC
		case self::ALG_HMAC_SHA256:
		case self::ALG_HMAC_SHA384:
		case self::ALG_HMAC_SHA512:
			$php_alg = static::getAlgPHPString($alg);
			if ($php_alg === null || !in_array($php_alg, hash_hmac_algos())) return null;
			return hash_equals(hash_hmac($php_alg, $data, $public_key, true), $signature);

		default:
			Log::info(sprintf('Cose/verify: COSE algorithm %d is not supported', $alg));
			return null;
		}
	}
}