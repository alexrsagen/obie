<?php namespace ZeroX\Security;
if (!defined('IN_ZEROX')) {
	return;
}

class Totp {
	/**
	 * Gets the current timestamp in UTC
	 * @return int
	 */
	protected static function getTimestamp(): int {
		return (new \DateTime('now', new \DateTimeZone('UTC')))->getTimestamp();
	}

	/**
	 * RFC 6238 compliant TOTP generator
	 * @param string $k - HMAC password
	 * @param int $time - TOTP time reference in seconds (unix epoch)
	 * @param int $time_step - TOTP time step value in seconds
	 * @param int $len - TOTP code digits
	 * @param string $algo - HMAC algorithm
	 */
	public static function gen(string $k, int $time = null, int $time_step = 30, int $len = 6, string $algo = 'sha1') {
		if ($time === null) {
			$time = static::getTimestamp();
		}
		return Hotp::gen($k, (int)floor($time / $time_step), $len, $algo);
	}

	/**
	 * RFC 6238 compliant TOTP verifier
	 * @param string $k - HMAC password
	 * @param int $time - TOTP time reference in seconds (unix epoch)
	 * @param int $time_step - TOTP time step value in seconds
	 * @param int $drift_steps_back - Amount of time steps backwards to allow to account for clock drift
	 * @param int $drift_steps_fwd - Amount of time steps forwards to allow to account for clock drift
	 * @param int $len - TOTP code digits
	 * @param string $algo - HMAC algorithm
	 */
	public static function verify(string $k, string $code, int $time = null, int $time_step = 30, int $drift_steps_back = 0, int $drift_steps_fwd = 0, int $len = 6, string $algo = 'sha1') {
		if ($time === null) {
			$time = static::getTimestamp();
		}
		if ($drift_steps_back < 0) $drift_steps_back = 0;
		if ($drift_steps_fwd < 0) $drift_steps_fwd = 0;
		$valid = false;
		for ($drift_step = -$drift_steps_back; $drift_step < $drift_steps_fwd + 1; $drift_step++) {
			if (hash_equals(Hotp::gen($k, (int)floor(($time + $drift_step * $time_step) / $time_step)), $code)) {
				$valid = true;
			}
		}
		return $valid;
	}
}
