<?php namespace Obie\Encoding;
use \Obie\Log;

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
	 * @param string|array|null $keys If a string is passed, it is assumed to be a HMAC key or base64-encoded X.509 certificate in DER form. If an array is passed, it is assumed to be an array of JWKs (if passing a single JWK, it must be wrapped in an array, making it an array of 1 JWK). If null is passed, signature is ignored (NOT RECOMMENDED).
	 * @param string $algorithm Allowed JWT algorithm
	 * @return mixed The decoded data within the JWT or null on failure
	 */
	public static function decode(string $jwt, string|array|null $keys = null, string $algorithm = self::ALG_HS256) {
		$ignore_signature = $keys === null;

		// check that algorithm is supported
		if (!array_key_exists($algorithm, self::KTY_BY_ALG)) {
			Log::info('JWT: Unsupported algorithm');
			return null;
		}

		// decode JWT parts
		$parts = static::decodeParts($jwt);
		if (count($parts) < 3) {
			Log::info('JWT: Invalid part count');
			return null;
		}

		// get header from parts
		$hdr = Json::decode($parts[0]);
		if (empty($hdr) || !is_array($hdr)) return null;

		// get key from header/JWKs
		$key = null;
		$kty = self::KTY_BY_ALG[$algorithm];
		$kid = array_key_exists('kid', $hdr) ? $hdr['kid'] : null;
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
		} elseif (!empty($kid)) {
			Log::info('JWT: Key ID found in JWT, but no JWKs provided');
			return null;
		} else {
			$key = $keys;
		}
		if (empty($key) && !$ignore_signature) {
			Log::info('JWT: No matching JWK found');
			return null;
		}

		// get type and algorithm from header
		$typ = array_key_exists('typ', $hdr) ? $hdr['typ'] : null;
		$alg = array_key_exists('alg', $hdr) ? $hdr['alg'] : self::ALG_NONE;
		if ($alg !== $algorithm) {
			Log::info('JWT: Invalid algorithm');
			return null;
		}

		// peform signature verification or decryption depending on type
		switch ($typ) {
		case self::TYP_JWT:
			if (!$ignore_signature) {
				// verify signature
				$sig_data = static::encodeParts(array_slice($parts, 0, 2));
				$sig = $parts[2];
				switch ($alg) {
				case self::ALG_HS256:
					// HMAC with SHA-256
					if (!is_string($key)) {
						Log::info('JWT: Invalid key');
						return null;
					}
					if (!hash_equals(hash_hmac('sha256', $sig_data, $key, true), $sig)) {
						Log::info('JWT: Invalid signature');
						return null;
					}
					break;
				case self::ALG_RS256:
				case self::ALG_RS384:
				case self::ALG_RS512:
					// RSASSA-PKCS1-v1_5 with SHA-256/384/512
					if (!is_array($key) || !array_key_exists('x5c', $key) || !is_array($key['x5c']) || count($key['x5c']) < 1) {
						if (is_array($key) && array_key_exists('x5u', $key)) {
							Log::info('JWT: Invalid key (keys containing links to remote certificates (x5u) are not currently supported by Obie)');
						} else {
							Log::info('JWT: Invalid key');
						}
						return null;
					}
					$cert = Pem::encode(base64_decode($key['x5c'][0]), Pem::LABEL_CERTIFICATE);
					$public_key = openssl_pkey_get_public($cert);
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
			}
			// return decoded data
			return Json::decode($parts[1]);
		default:
			Log::info('JWT: Unsupported type');
			return null;
		}
	}

	public static function encodeParts(array $parts): string {
		return implode('.', array_map('\Obie\Encoding\Base64Url::encodeUnpadded', $parts));
	}

	public static function decodeParts(string $jwt): array {
		return array_map('\Obie\Encoding\Base64Url::decode', array_map('trim', explode('.', $jwt, 3)));
	}
}