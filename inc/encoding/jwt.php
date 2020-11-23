<?php namespace ZeroX\Encoding;
use \ZeroX\Log;

class Jwt {
	const TYP_JWT = 'JWT';
	const TYP_JWS = 'JWS';
	const TYP_JWE = 'JWE';

	const ALG_HS256 = 'HS256';
	const ALG_NONE = 'none';

	const HEADER_JWT_HS256 = ['typ' => 'JWT', 'alg' => self::ALG_HS256];

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
			switch ($alg) {
			case self::ALG_HS256:
				if (empty($key)) {
					Log::info('JWT: Signing key not provided');
					return null;
				}
				$parts[] = hash_hmac('sha256', static::encodeParts($parts), $key, true);
				break;
			case self::ALG_NONE:
				$parts[] = '';
				break;
			default:
				Log::info('JWT: Unsupported algorithm');
				return null;
			}
			break;
		default:
			Log::info('JWT: Unsupported type');
			return null;
		}
		// return encoded parts
		return static::encodeParts($parts);
	}

	public static function decode(string $jwt, ?string $key = null) {
		$parts = static::decodeParts($jwt);
		if (count($parts) < 3) {
			Log::info('JWT: Invalid part count');
			return null;
		}
		$hdr = Json::decode($parts[0]);
		if (empty($hdr) || !is_array($hdr)) return null;
		// get type and algorithn from header
		$typ = array_key_exists('typ', $hdr) ? $hdr['typ'] : null;
		$alg = array_key_exists('alg', $hdr) ? $hdr['alg'] : self::ALG_NONE;
		// peform signature verification or decryption depending on type
		switch ($typ) {
		case self::TYP_JWT:
			if (!empty($key)) {
				// verify signature
				switch ($alg) {
				case self::ALG_HS256:
					if (!hash_equals(hash_hmac('sha256', static::encodeParts(array_slice($parts, 0, 2)), $key, true), $parts[2])) {
						Log::info('JWT: Invalid signature');
						return null;
					}
					break;
				case self::ALG_NONE:
					break;
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
		return implode('.', array_map('\ZeroX\Encoding\Base64Url::encodeUnpadded', $parts));
	}

	public static function decodeParts(string $jwt): array {
		return array_map('\ZeroX\Encoding\Base64Url::decode', array_map('trim', explode('.', $jwt, 3)));
	}
}