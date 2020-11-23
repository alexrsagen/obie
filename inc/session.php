<?php namespace ZeroX;
use \ZeroX\Router;
use \ZeroX\Log;
use \ZeroX\Vars\StaticVarTrait;

class Session {
	use StaticVarTrait;

	protected static function _applyHmac(): void {
		$data = self::get();
		unset($data['_real']);
		self::$vars->set('_real', hash_hmac('sha256', serialize($data), App::getConfig()->get('sessions', 'secret')));
	}

	protected static function _verifyHmac(): bool {
		$data = self::get();
		unset($data['_real']);
		return hash_equals(hash_hmac('sha256', serialize($data), App::getConfig()->get('sessions', 'secret')), self::get('_real'));
	}

	public static function set(...$v) {
		self::_init_vars();
		self::$vars->set(...$v);
		static::_applyHmac(); // generate new session integrity HMAC
	}

	public static function unset(...$v) {
		self::_init_vars();
		self::$vars->unset(...$v);
		static::_applyHmac(); // generate new session integrity HMAC
	}

	public static function generateNewID() {
		session_id(Random::string(64, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789,-'));
	}

	public static function getName(): string {
		return (string)(App::getConfig()->get('sessions', 'name') ?? 'session');
	}

	public static function getID(): ?string {
		$config = App::getConfig();
		$only_cookie = (bool)($config->get('sessions', 'use_only_cookies') ?? true);
		if (isset($_COOKIE[static::getName()]) && !empty(isset($_COOKIE[static::getName()]))) {
			return $_COOKIE[static::getName()];
		}
		if ($only_cookie) return null;
		return Router::getPost(static::getName()) ?? Router::getQuery(static::getName());
	}

	public static function new() {
		// destroy any existing session
		if (static::started()) static::destroy();

		// generate custom session ID
		static::generateNewID();

		// start the new session
		static::_start(true);
	}

	protected static function _start(bool $new = false) {
		// set session ID
		session_id(static::getID());

		// start a new session or resume an existing session
		session_start();

		// init VarCollection with $_SESSION as storage
		self::_init_vars($_SESSION, true);

		// set new flag if this is a fresh session (cleared in end)
		if ($new) self::set('_new', true);

		// set last active timestamp
		self::set('_lastactive', time());
	}

	public static function start() {
		// ensure sessions are enabled in PHP configuration
		if (session_status() === PHP_SESSION_DISABLED) {
			throw new \Exception('Sessions are disabled in PHP configuration');
		}

		// get app configuration
		$config = App::getConfig();

		// ensure redis module is loaded if configured save handler is redis
		if ($config->get('sessions', 'save_handler') === 'redis' && !extension_loaded('redis')) {
			throw new \Exception('Redis module is not loaded');
		}

		// set session parameters from config
		ini_set('session.save_handler', $config->get('sessions', 'save_handler'));
		ini_set('session.save_path', $config->get('sessions', 'save_path'));
		ini_set('session.gc_maxlifetime', (int)($config->get('sessions', 'lifetime') ?? 3600));
		ini_set('session.use_only_cookies', (int)(bool)($config->get('sessions', 'use_only_cookies') ?? true));
		session_set_cookie_params([
			'lifetime' => (int)($config->get('sessions', 'lifetime') ?? 3600),
			'path'     => '/',
			'domain'   => substr($config->get('url'), strpos($config->get('url'), '://') + 3),
			'secure'   => strpos($config->get('url'), 'https://') === 0,
			'httponly' => true,
			'samesite' => $config->get('sessions', 'samesite') ?? 'Lax'
		]);
		session_name(static::getName());

		// ensure session is only started once
		if (session_status() !== PHP_SESSION_NONE) return;

		// ensure a session has been started
		if (empty(static::getID())) {
			static::new();
		} else {
			static::_start();
		}

		// ensure session integrity HMAC is set
		if (!self::isset('_real')) {
			Log::info('New session: Session integrity marker not set');
			static::new();
		}

		// verify session integrity
		if (!static::_verifyHmac()) {
			Log::info('New session: Invalid session integrity');
			static::new();
		}

		// ensure last active timestamp is set
		if (!self::isset('_new') && !self::isset('_lastactive')) {
			Log::info('New session: Last active timestamp not set');
			static::new();
		}

		// verify last active timestamp
		$last_active = self::get('_lastactive');
		if ($last_active !== null && time() - $last_active > (int)App::getConfig()->get('sessions', 'lifetime')) {
			Log::info('New session: Lifetime expired');
			static::new();
		}
	}

	public static function started() {
		return session_status() === PHP_SESSION_ACTIVE && !empty(session_id());
	}

	public static function reset() {
		if (!static::started()) return;
		session_reset();
	}

	public static function destroy() {
		if (static::started()) {
			session_unset();
			session_destroy();
		}
		self::$vars = null;
	}

	public static function end() {
		// ensure a session has been started
		if (!static::started()) return;

		// clear new flag, as it is no longer a new session
		if (self::isset('_new')) self::unset('_new');

		// flush data to save handler
		session_write_close();
	}
}
