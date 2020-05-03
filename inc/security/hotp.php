<?php namespace ZeroX\Security;
if (!defined('IN_ZEROX')) {
	return;
}

class Hotp {
	/**
	 * RFC 4226 compliant HOTP generator
	 * @param string $k - HMAC password
	 * @param int $counter - HOTP counter value
	 * @param int $len - HOTP code digits
	 * @param string $algo - HMAC algorithm
	 */
	public static function gen(string $k, int $counter = null, int $len = 6, string $algo = 'sha1') {
		$msg_bytes = [];
		while (0 !== $counter) {
			$msg_bytes[] = chr($counter & 0xFF);
			$counter >>= 8;
		}
		$msg = str_pad(implode(array_reverse($msg_bytes)), 8, "\000", STR_PAD_LEFT);
		$hmac = hash_hmac($algo, $msg, $k, true);
		$offset = ord($hmac[strlen($hmac) - 1]) & 0xF;
		$binary = (ord($hmac[$offset])     & 0x7F) << 24 |
		          (ord($hmac[$offset + 1]) & 0xFF) << 16 |
		          (ord($hmac[$offset + 2]) & 0xFF) << 8  |
		          (ord($hmac[$offset + 3]) & 0xFF);
		$otp = $binary % pow(10, $len);
		return str_pad((string)$otp, $len, '0', STR_PAD_LEFT);
	}
}
