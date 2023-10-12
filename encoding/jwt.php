<?php namespace Obie\Encoding;
use Obie\Log;

class Jwt {
	const TYP_JWT = 'JWT';
	const TYP_JWS = 'JWS';
	const TYP_JWE = 'JWE';

	const ALG_HS256 = 'HS256';
	const ALG_RS256 = 'RS256';
	const ALG_RS384 = 'RS384';
	const ALG_RS512 = 'RS512';
	const ALG_NONE = 'none';

	const KTY_BY_ALG = [
		self::ALG_HS256 => 'oct',
		self::ALG_RS256 => 'RSA',
		self::ALG_RS384 => 'RSA',
		self::ALG_RS512 => 'RSA',
		self::ALG_NONE => null,
	];

	const OPENSSL_ALGO_BY_ALG = [
		self::ALG_RS256 => OPENSSL_ALGO_SHA256,
		self::ALG_RS384 => OPENSSL_ALGO_SHA384,
		self::ALG_RS512 => OPENSSL_ALGO_SHA512,
	];

	const HEADER_JWT_HS256 = ['typ' => 'JWT', 'alg' => self::ALG_HS256];

	/**
	 * Encodes and signs a JSON Web Token, as per RFC 7519
	 *
	 * @param mixed $data The data to encode and sign
	 * @param null|string $key The HMAC key or RSA private key in PEM form to sign the data with
	 * @param array $hdr The header to prepend to the JWT data and signature, which also specifies which algorithm to sign the data with
	 * @return null|string The encoded JWT or null on failure
	 */
	public static function encode($data, ?string $key = null, array $hdr = self::HEADER_JWT_HS256): ?string {
		// get type and algorithn from header
		$typ = array_key_exists('typ', $hdr) ? $hdr['typ'] : null;
		$alg = array_key_exists('alg', $hdr) ? $hdr['alg'] : self::ALG_NONE;
		// add JSON encoding of header to parts
		$parts = [ Json::encode($hdr) ];
		// perform signing or encryption depending on type
		switch ($typ) {
		case self::TYP_JWS:
		case self::TYP_JWT:
			// add JSON encoding of data to parts
			$parts[] = Json::encode($data);
			// sign data, add signature to parts
			$sig_data = static::encodeParts($parts);
			$sig = '';
			switch ($alg) {
			case self::ALG_HS256:
				// HMAC with SHA-256
				if (empty($key)) {
					Log::info('JWT: Signing key not provided');
					return null;
				}
				$sig = hash_hmac('sha256', $sig_data, $key, true);
				break;
			case self::ALG_RS256:
			case self::ALG_RS384:
			case self::ALG_RS512:
				// RSASSA-PKCS1-v1_5 with SHA-256/384/512
				if (!openssl_sign($sig_data, $sig, $key, self::OPENSSL_ALGO_BY_ALG[$alg])) {
					Log::info('JWT: Unable to sign data');
					return null;
				}
				break;
			case self::ALG_NONE:
				$sig = '';
				break;
			default:
				Log::info('JWT: Unsupported algorithm');
				return null;
			}
			$parts[] = $sig;
			break;
		default:
			Log::info('JWT: Unsupported type');
			return null;
		}
		// return encoded parts
		return static::encodeParts($parts);
	}

	/**
	 * Decodes and verifies a JSON Web Token, as per RFC 7519
	 *
	 * @param string $jwt The JSON Web Token to decode and verify
	 * @param \OpenSSLAsymmetricKey|\OpenSSLCertificate|array|string|null $keys If a string or OpenSSL resource is passed, it is assumed to be an X.509 certificate or a HMAC key (depending on algorithm). If an array is passed, it is assumed to be an array of JWKs (if passing a single JWK, it must be wrapped in an array, making it an array of 1 JWK).
	 * @param string $algorithm Allowed JWT algorithm
	 * @param bool $unsafe_ignore_signature Whether to ignore the signature This is NOT RECOMMENDED, therefore the default value is false.
	 * @param bool $key_is_x5c_root Whether the provided key is an X.509 root certificate used to sign the last certificate in the "x5c" chain in the JWT header.
	 * @return mixed The decoded data within the JWT or null on failure
	 */
	public static function decode(
		string $jwt,
		\OpenSSLAsymmetricKey|\OpenSSLCertificate|array|string|null $keys = null,
		string $algorithm = self::ALG_HS256,
		bool $unsafe_ignore_signature = false,
		bool $key_is_x5c_root = false,
	): mixed {
		// check that algorithm is supported
		if (!array_key_exists($algorithm, self::KTY_BY_ALG)) {
			Log::info('JWT: Unsupported algorithm');
			return null;
		}
		$kty = self::KTY_BY_ALG[$algorithm];

		// decode JWT parts
		$parts = static::decodeParts($jwt);
		if (count($parts) < 3) {
			Log::info('JWT: Invalid part count');
			return null;
		}

		// get header from parts
		$hdr = Json::decode($parts[0]);
		if (empty($hdr) || !is_array($hdr)) return null;

		// get data from header
		$kid = array_key_exists('kid', $hdr) ? $hdr['kid'] : null;
		$x5c = array_key_exists('x5c', $hdr) ? $hdr['x5c'] : null;
		$typ = array_key_exists('typ', $hdr) ? $hdr['typ'] : null;
		$alg = array_key_exists('alg', $hdr) ? $hdr['alg'] : self::ALG_NONE;
		if ($alg !== $algorithm) {
			Log::info('JWT: Invalid algorithm');
			return null;
		}

		// get key from header/JWKs
		$key = null;
		if (is_array($keys)) {
			if (count($keys) === 0) {
				Log::info('JWT: No keys provided');
				return null;
			}
			if (empty($kid)) {
				Log::info('JWT: JWKs provided, but no key ID found in JWT header');
				return null;
			}
			foreach ($keys as $k) {
				if (
					array_key_exists('kid', $k) &&
					array_key_exists('kty', $k) &&
					$k['kid'] === $kid &&
					$k['kty'] === $kty
				) {
					$key = $k;
					break;
				}
			}
		}

		// peform signature verification or decryption depending on type
		if (!in_array($typ, [self::TYP_JWT, self::TYP_JWS])) {
			Log::info('JWT: Unsupported type');
			return null;
		}

		if ($unsafe_ignore_signature) goto DECODE_DATA;

		// verify signature
		$sig_data = static::encodeParts(array_slice($parts, 0, 2));
		$sig = $parts[2];
		switch ($alg) {
		// HMAC with SHA-256
		case self::ALG_HS256:
			if (!is_string($key)) {
				Log::info('JWT: Invalid key (not a HMAC key string)');
				return null;
			}

			// verify signature
			if (!hash_equals(hash_hmac('sha256', $sig_data, $key, true), $sig)) {
				Log::info('JWT: Invalid signature');
				return null;
			}
			break;

		// RSASSA-PKCS1-v1_5 with SHA-256/384/512
		case self::ALG_RS256:
		case self::ALG_RS384:
		case self::ALG_RS512:
			// get public key or X.509 certificate from provided key or X.509 chain
			$public_key = null;
			if ($key_is_x5c_root) {
				if (!is_array($x5c) || count($x5c) < 1) {
					Log::info('JWT: Invalid key (X.509 chain (x5c) missing from JWT header)');
					return null;
				}

				// validate X.509 certificate chain in JWT header
				// against root public key (from last to first certificate)
				$last_public_key = openssl_pkey_get_public($keys);
				for ($i = count($x5c) - 1; $i >= 0; $i--) {
					if (!$last_public_key) {
						Log::info('JWT: Invalid key (unable to get public key from root or intermediate certificate)');
						return null;
					}

					$cur_cert = false;
					if (array_key_exists($i, $x5c) && is_string($x5c[$i])) $cur_cert = base64_decode($x5c[$i]);
					if (is_string($cur_cert)) $cur_cert = Pem::encode($cur_cert, Pem::LABEL_CERTIFICATE);
					if (is_string($cur_cert)) $cur_cert = openssl_x509_read($cur_cert);
					if (!$cur_cert || openssl_x509_verify($cur_cert, $last_public_key) !== 1) {
						Log::info('JWT: Invalid key (unable to validate certificate chain)');
						return null;
					}

					$last_public_key = openssl_pkey_get_public($cur_cert);
				}

				// store the first certificate in the chain
				$public_key = $last_public_key;
			} elseif (
				is_array($key) &&
				array_key_exists('x5c', $key) &&
				is_array($key['x5c']) && count($key['x5c']) > 0
			) {
				// get public key from provided JWK
				// XXX: implicitly trust X.509 chain validity as $keys is not expected to be end user input
				$public_key_source = Pem::encode(base64_decode($key['x5c'][0]), Pem::LABEL_CERTIFICATE);
				$public_key = openssl_pkey_get_public($public_key_source);
			} elseif (is_array($key) && array_key_exists('x5u', $key)) {
				Log::info('JWT: Invalid key (keys containing links to remote certificates (x5u) are not currently supported by Obie)');
			}

			// ensure public key from first certificate in the chain is valid
			if (!$public_key) {
				Log::info('JWT: Invalid key (unable to get public key from first certificate in the chain)');
				return null;
			}

			// verify signature
			if (openssl_verify($sig_data, $sig, $public_key, self::OPENSSL_ALGO_BY_ALG[$alg]) !== 1) {
				Log::info('JWT: Invalid signature');
				return null;
			}
			break;

		case self::ALG_NONE:
			break;

		default:
			Log::info('JWT: Unsupported algorithm');
			return null;
		}

	DECODE_DATA:
		return Json::decode($parts[1]);
	}

	public static function encodeParts(array $parts): string {
		return implode('.', array_map('\Obie\Encoding\Base64Url::encodeUnpadded', $parts));
	}

	public static function decodeParts(string $jwt): array {
		return array_map('\Obie\Encoding\Base64Url::decode', array_map('trim', explode('.', $jwt, 3)));
	}
}