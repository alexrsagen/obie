<?php namespace ZeroX\Security\TwoFactor;
if (!defined('IN_ZEROX')) {
	return;
}

class Totp {
	public static function gen(string $k, int $time = null) {
		if ($time === null) {
			$time = time();
		}
		$time = (int)(time() / 30);
		$msg_bytes = [];
		while (0 !== $time) {
			$msg_bytes[] = chr($time & 0xFF);
			$time >>= 8;
		}
		$msg = str_pad(implode(array_reverse($msg_bytes)), 8, "\000", STR_PAD_LEFT);
		$hmac = hash_hmac('sha1', $msg, $k, true);
		$offset = ord($hmac[strlen($hmac) - 1]) & 0xF;
		$binary = (ord($hmac[$offset])     & 0x7F) << 24 |
		          (ord($hmac[$offset + 1]) & 0xFF) << 16 |
		          (ord($hmac[$offset + 2]) & 0xFF) << 8  |
		          (ord($hmac[$offset + 3]) & 0xFF);
		$otp = $binary % pow(10, 6);
		return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
	}
}
