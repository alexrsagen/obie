<?php namespace Obie\Security;
use \Obie\Encoding\Pem;
use \Sop\CryptoTypes\Asymmetric\PublicKeyInfo;
use \Sop\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use \Sop\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;
use \Sop\CryptoTypes\Asymmetric\EC\ECPublicKey;

class Ecdsa {
	const PREFIX_EVEN = "\x02";
	const PREFIX_ODD = "\x03";
	const PREFIX_UNCOMPRESSED = "\x04";

	protected static function pubkeyDERToRaw(string $key): string {
		$info = PublicKeyInfo::fromDER($key);
		$algo = $info->algorithmIdentifier();
		if (!($algo instanceof ECPublicKeyAlgorithmIdentifier)) return false;
		if ($algo->oid() != AlgorithmIdentifier::OID_EC_PUBLIC_KEY) return '';
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
		return substr($key, 0, 1) === "\x30";
	}

	public static function pubkeyToRaw(string $key): string {
		// handle RAW input
		if (static::pubkeyIsRaw($key)) return $key;
		// handle DER/PEM input
		return static::pubkeyDERToRaw(static::pubkeyToDER($key));
	}

	public static function pubkeyToDER(string $key, ?string $curve_oid_for_raw_key = null): string {
		// handle DER input
		if (static::pubkeyIsDER($key)) return $key;
		// handle PEM input
		if (Pem::isEncoded($key)) {
			$label = Pem::decodeLabel($key);
			if ($label === Pem::LABEL_CERTIFICATE) {
				$key_res = openssl_pkey_get_public($key);
				if ($key_res === false) return '';
				$key_details = openssl_pkey_get_details($key_res);
				if (!is_array($key_details) || !array_key_exists('ec', $key_details)) return '';
				$key = $key_details['key'];
			}
			return Pem::decode($key);
		}
		// handle RAW input
		if ($curve_oid_for_raw_key === null || !static::pubkeyIsRaw($key)) return '';
		try {
			$info = PublicKeyInfo::fromPublicKey(new ECPublicKey($key, $curve_oid_for_raw_key));
			return $info->toDER();
		} catch (\Exception $e) {
			return '';
		}
	}

	public static function pubkeyToPEM(string $key, ?string $curve_oid_for_raw_key = null): string {
		// handle PEM input
		if (Pem::isEncoded($key) && Pem::decodeLabel($key) === Pem::LABEL_PUBLICKEY) return $key;
		// handle DER/RAW input
		$der = static::pubkeyToDER($key, $curve_oid_for_raw_key);
		if ($der === '') return '';
		return Pem::encode($der, Pem::LABEL_PUBLICKEY);
	}

	/**
	 * Verifies an ECDSA signature with the given data and public key.
	 *
	 * @param string $data Raw data which is used for the signature
	 * @param string $signature Raw signature data
	 * @param string $public_key ECDSA public key, in PEM or DER form
	 * @param string|int $alg OpenSSL algorithm constant
	 * @param ?string $curve_oid_for_raw_key ECDSA curve OID that may optionally be specified to support $public_key in RAW form
	 * @return bool
	 */
	public static function verify(string $data, string $signature, string $public_key, string|int $alg = OPENSSL_ALGO_SHA256, ?string $curve_oid_for_raw_key = null): bool {
		// PEM-encode public key if in raw DER form, so OpenSSL can use it
		$public_key_pem = static::pubkeyToPem($public_key, $curve_oid_for_raw_key);
		if ($public_key_pem === '') return false;

		// verify signature
		return openssl_verify($data, $signature, $public_key_pem, $alg) === 1;
	}
}