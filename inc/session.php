<?php namespace ZeroX;
use ZeroX\Vars\StaticVarTrait;
if (!defined('IN_ZEROX')) {
	return;
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

		if (Config::getGlobal()->get('sessions', 'save_handler') === 'redis' && !extension_loaded('redis')) {
			throw new \Exception('Redis module is not loaded');
		}

		ini_set('session.save_handler', Config::getGlobal()->get('sessions', 'save_handler'));
		ini_set('session.save_path', Config::getGlobal()->get('sessions', 'save_path'));
		ini_set('session.gc_maxlifetime', (int)Config::getGlobal()->get('sessions', 'lifetime'));
		session_set_cookie_params([
			'lifetime' => (int)Config::getGlobal()->get('sessions', 'lifetime'),
			'path'     => '/',
			'domain'   => substr(Config::getGlobal()->get('url'), strpos(Config::getGlobal()->get('url'), '://') + 3),
			'secure'   => strpos(Config::getGlobal()->get('url'), 'https://') === 0,
			'httponly' => true,
			'samesite' => Config::getGlobal()->get('sessions', 'samesite') ?? 'Lax'
		]);
		session_name(Config::getGlobal()->get('sessions', 'name'));

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
					if (!hash_equals(hash_hmac('sha256', serialize($data), Config::getGlobal()->get('sessions', 'secret')), self::get('_real'))) {
						self::new();
					}
				}
			}
			if (self::isset('_lastactive')) {
				$last_active = self::get('_lastactive');
				if ($last_active !== null && time() - $last_active > (int)Config::getGlobal()->get('sessions', 'lifetime')) {
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
		self::set('_real', hash_hmac('sha256', serialize($data), Config::getGlobal()->get('sessions', 'secret')));
		session_write_close();
	}
}
