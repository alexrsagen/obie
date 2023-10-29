<?php namespace Obie\Security;

class Totp {
	/**
	 * Gets the current timestamp in UTC
	 * @return int
	 */
	protected static function now(): \DateTime {
		return (new \DateTime('now', new \DateTimeZone('UTC')));
	}

	/**
	 * RFC 6238 compliant TOTP generator
	 * @param string $k - HMAC password
	 * @param int|\DateTime|null $time - TOTP time reference in seconds (unix epoch)
	 * @param int $time_step - TOTP time step value in seconds
	 * @param int $len - TOTP code digits
	 * @param string $algo - HMAC algorithm
	 */
	public static function gen(string $k, \DateTime|int|null $time = null, int $time_step = 30, int $len = 6, string $algo = 'sha1'): string {
		if ($time === null) $time = static::now();
		if ($time instanceof \DateTime) $time = $time->getTimestamp();
		return Hotp::gen($k, (int)floor($time / $time_step), $len, $algo);
	}

	/**
	 * RFC 6238 compliant TOTP verifier
	 * @param string $k - HMAC password
	 * @param int|\DateTime|null $time - TOTP time reference in seconds (unix epoch)
	 * @param int $time_step - TOTP time step value in seconds
	 * @param int $drift_steps_back - Amount of time steps backwards to allow to account for clock drift
	 * @param int $drift_steps_fwd - Amount of time steps forwards to allow to account for clock drift
	 * @param int $len - TOTP code digits
	 * @param string $algo - HMAC algorithm
	 */
	public static function verify(string $k, string $code, \DateTime|int|null $time = null, int $time_step = 30, int $drift_steps_back = 0, int $drift_steps_fwd = 0, int $len = 6, string $algo = 'sha1'): bool {
		if ($time === null) $time = static::now();
		if ($time instanceof \DateTime) $time = $time->getTimestamp();
		if ($drift_steps_back < 0) $drift_steps_back = 0;
		if ($drift_steps_fwd < 0) $drift_steps_fwd = 0;
		$valid = false;
		for ($drift_step = -$drift_steps_back; $drift_step < $drift_steps_fwd + 1; $drift_step++) {
			if (hash_equals(Hotp::gen($k, (int)floor(($time + $drift_step * $time_step) / $time_step), $len, $algo), $code)) {
				$valid = true;
			}
		}
		return $valid;
	}
}
