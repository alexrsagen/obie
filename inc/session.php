<?php namespace ZeroX;
use ZeroX\Vars\StaticVarTrait;
if (!defined('IN_ZEROX')) {
	exit;
}

class Session {
	use StaticVarTrait;

	public static function generateNewID() {
		session_id(Util::randomString(64, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789,-'));
	}

	public static function new() {
		if (self::started()) self::destroy();
		self::generateNewID();
		self::_start();
	}

	private static function _start() {
		session_start();
		self::_init_vars($_SESSION, true);
	}

	public static function start() {
		if (session_status() === PHP_SESSION_DISABLED) {
			throw new \Exception('Sessions are disabled in PHP configuration');
		}

		if (Config::get('sessions', 'save_handler') === 'redis' && !extension_loaded('redis')) {
			throw new \Exception('Redis module is not loaded');
		}

		ini_set('session.save_handler', Config::get('sessions', 'save_handler'));
		ini_set('session.save_path', Config::get('sessions', 'save_path'));
		ini_set('session.gc_maxlifetime', (int)Config::get('sessions', 'lifetime'));
		session_set_cookie_params((int)Config::get('sessions', 'lifetime'), '/', substr(Config::get('url'), strpos(Config::get('url'), '://') + 3), strpos(Config::get('url'), 'https://') === 0, true);
		session_name(Config::get('sessions', 'name'));

		if (session_status() === PHP_SESSION_NONE) {
			if (!isset($_COOKIE[session_name()])) {
				self::new();
			} else {
				self::_start();
			}
			if (count(self::$vars) !== 0) {
				if (!self::isset('_real')) {
					self::new();
				} else {
					$data = self::get();
					unset($data['_real']);
					if (!hash_equals(hash_hmac('sha256', serialize($data), Config::get('sessions', 'secret')), self::get('_real'))) {
						self::new();
					}
				}
			}
			if (self::isset('_lastactive')) {
				$last_active = self::get('_lastactive');
				if ($last_active !== null && time() - $last_active > (int)Config::get('sessions', 'lifetime')) {
					self::new();
				}
			}
		}
	}

	public static function started() {
		return session_status() === PHP_SESSION_ACTIVE && session_id() !== '';
	}

	public static function reset() {
		if (!self::started()) {
			return;
		}

		session_reset();
	}

	public static function destroy() {
		if (!self::started()) {
			self::$vars = null;
			return;
		}

		session_unset();
		session_destroy();
		self::$vars = null;
	}

	public static function end() {
		if (!self::started()) {
			return;
		}

		self::set('_lastactive', time());
		$data = self::get();
		unset($data['_real']);
		self::set('_real', hash_hmac('sha256', serialize($data), Config::get('sessions', 'secret')));
		session_write_close();
	}
}
