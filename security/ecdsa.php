<?php namespace Obie\Security;
use Obie\Encoding\Asn1;
use Obie\Encoding\Pem;
use Sop\CryptoTypes\Asymmetric\PublicKeyInfo;
use Sop\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;
use Sop\CryptoTypes\Asymmetric\EC\ECPrivateKey;
use Sop\CryptoTypes\Asymmetric\EC\ECPublicKey;
use Sop\CryptoTypes\Asymmetric\PrivateKeyInfo;

class Ecdsa {
	const PREFIX_EVEN = "\x02";
	const PREFIX_ODD = "\x03";
	const PREFIX_UNCOMPRESSED = "\x04";

	protected static function pubkeyDERToRaw(string $key): ?string {
		$info = PublicKeyInfo::fromDER($key);
		$algo = $info->algorithmIdentifier();
		if (!($algo instanceof ECPublicKeyAlgorithmIdentifier)) return null;
		if ($algo->oid() != AlgorithmIdentifier::OID_EC_PUBLIC_KEY) return null;
		return $info->publicKeyData();
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
		default:
			return false;
		}
	}

	public static function pubkeyIsDER(string $key): bool {
		return Asn1::isSequence($key);
	}

	public static function pubkeyToRaw(string $key): ?string {
		// handle RAW input
		if (static::pubkeyIsRaw($key)) return $key;
		// handle DER/PEM input
		return static::pubkeyDERToRaw(static::pubkeyToDER($key));
	}

	public static function pubkeyToDER(string $key, ?string $curve_oid_for_raw_key = null): ?string {
		// handle DER input
		if (static::pubkeyIsDER($key)) return $key;
		// handle PEM input
		if (Pem::isEncoded($key)) {
			$key_res = openssl_pkey_get_public($key);
			if ($key_res === false) return null;
			$key_details = openssl_pkey_get_details($key_res);
			if (!is_array($key_details) || !array_key_exists('ec', $key_details)) return null;
			$key = $key_details['key'];
			return Pem::decode($key);
		}
		// handle RAW input
		if ($curve_oid_for_raw_key === null || !static::pubkeyIsRaw($key)) return null;
		try {
			$info = PublicKeyInfo::fromPublicKey(new ECPublicKey($key, $curve_oid_for_raw_key));
			return $info->toDER();
		} catch (\Exception $e) {
			return null;
		}
	}

	public static function pubkeyToPEM(string $key, ?string $curve_oid_for_raw_key = null): ?string {
		// handle PEM input
		if (Pem::isEncoded($key) && Pem::decodeLabel($key) === Pem::LABEL_PUBLICKEY) return $key;
		// handle DER/RAW input
		$der = static::pubkeyToDER($key, $curve_oid_for_raw_key);
		if ($der === null) return null;
		return Pem::encode($der, Pem::LABEL_PUBLICKEY);
	}

	public static function privkeyIsDER(string $key): bool {
		return Asn1::isSequence($key);
	}

	public static function privkeyToDER(string $key, ?string $curve_oid_for_raw_key = null): ?string {
		// handle DER input
		if (static::privkeyIsDER($key)) return $key;
		// handle PEM input
		if (Pem::isEncoded($key)) return Pem::decode($key);
		// handle RAW input
		if ($curve_oid_for_raw_key === null) return null;
		try {
			$info = PrivateKeyInfo::fromPrivateKey(new ECPrivateKey($key, $curve_oid_for_raw_key));
			return $info->toDER();
		} catch (\Exception $e) {
			return null;
		}
	}

	public static function privkeyToPEM(string $key, ?string $curve_oid_for_raw_key = null): ?string {
		// handle PEM input
		if (Pem::isEncoded($key) && in_array(Pem::decodeLabel($key), [Pem::LABEL_EC_PRIVATEKEY, Pem::LABEL_PRIVATEKEY])) return $key;
		// handle DER/RAW input
		$der = static::privkeyToDER($key, $curve_oid_for_raw_key);
		if ($der === null) return null;
		return Pem::encode($der, Pem::LABEL_EC_PRIVATEKEY);
	}

	/**
	 * Verifies an ECDSA signature with the given data and public key.
	 *
	 * @param string $data Raw data which is used for the signature
	 * @param string $signature Raw signature data
	 * @param string $public_key An ECDSA public key, in PEM, DER or raw form (if $curve_oid_for_raw_key specified)
	 * @param string|int $alg OpenSSL algorithm constant
	 * @param ?string $curve_oid_for_raw_key ECDSA curve OID that may optionally be specified to support $public_key in RAW form
	 * @return bool
	 */
	public static function verify(string $data, string $signature, string $public_key, string|int $alg = OPENSSL_ALGO_SHA256, ?string $curve_oid_for_raw_key = null): bool {
		// PEM-encode public key if in raw DER form, so OpenSSL can use it
		$public_key_pem = static::pubkeyToPem($public_key, $curve_oid_for_raw_key);
		if ($public_key_pem === null) return false;

		// verify signature
		return openssl_verify($data, $signature, $public_key_pem, $alg) === 1;
	}

	/**
	 * Creates an ECDSA signature for the given data with the given public key.
	 *
	 * @param string $data Raw data which is used for the signature
	 * @param string $private_key An ECDSA private key, in PEM, DER or raw form (if $curve_oid_for_raw_key specified)
	 * @param string|int $alg OpenSSL algorithm constant
	 * @param ?string $curve_oid_for_raw_key ECDSA curve OID that may optionally be specified to support $private_key in raw form
	 * @return string Raw signature data
	 */
	public static function sign(string $data, string $private_key, string|int $alg = OPENSSL_ALGO_SHA256, ?string $curve_oid_for_raw_key = null): ?string {
		// PEM-encode public key if in raw DER form, so OpenSSL can use it
		$private_key_pem = static::privkeyToPem($private_key, $curve_oid_for_raw_key);
		if ($private_key_pem === null) return false;

		// create signature
		if (!openssl_sign($data, $signature, $private_key_pem, $alg)) {
			return null;
		}
		return $signature;
	}
}