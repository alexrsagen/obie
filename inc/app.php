<?php namespace ZeroX;

class App {
	public static $app = self::class;
	protected static $config = null;
	protected static $router = null;
	protected static $db = null;
	protected static $ro_db = null;

	public static function getConfig() {
		return self::$config;
	}

	public static function getRouter() {
		return self::$router;
	}

	public static function getDB() {
		return self::$db;
	}

	public static function getReadonlyDB() {
		return self::$ro_db;
	}

	public static function register() {
		self::$app = static::class;
	}

	public static function init(): bool {
		if (!self::$app::initTime()) {
			error_log(self::$app . '::initTime failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initVendor()) {
			error_log(self::$app . '::initVendor failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initAppDir()) {
			error_log(self::$app . '::initAppDir failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initConfig()) {
			error_log(self::$app . '::initConfig failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initLogger()) {
			error_log(self::$app . '::initLogger failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initTemp()) {
			error_log(self::$app . '::initTemp failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initEventHandlers()) {
			error_log(self::$app . '::initEventHandlers failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initLocale()) {
			error_log(self::$app . '::initLocale failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initRouter()) {
			error_log(self::$app . '::initRouter failed', E_USER_ERROR);
			return false;
		}
		if (!self::$app::initViews()) {
			error_log(self::$app . '::initViews failed', E_USER_ERROR);
			return false;
		}
		self::$app::initSessions();
		self::$app::initMail();
		self::$app::initDatabase();
		return true;
	}

	// public static function init(): bool {}

	/**
	 * Init everything related to time
	 */
	public static function initTime(): bool {
		// Set timezone
		if (date_default_timezone_get() !== 'UTC') {
			date_default_timezone_set('UTC');
		}
		// Set time language temporarily during init
		putenv('LC_TIME=en_US' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
		setlocale(LC_TIME, 'en_US' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
		return true;
	}

	/**
	 * Init everything related to localization
	 */
	public static function initLocale(): bool {
		if (!self::$app::initConfig()) return false;
		if (self::$config->isset('lang')) {
			// Set language from config
			putenv('LC_ALL=' . self::$config->get('lang') . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
			setlocale(LC_ALL, self::$config->get('lang') . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
		} else {
			// Set fallback language
			putenv('LC_ALL=en_US' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
			setlocale(LC_ALL, 'en_US' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
		}
		return true;
	}

	/**
	 * Init temp dir
	 */
	public static function initTemp(): bool {
		if (defined('ZEROX_TMP_DIR')) return true;
		if (!self::$app::initConfig()) return false;
		if (array_key_exists('ZEROX_TMP_DIR', $_ENV)) {
			define('ZEROX_TMP_DIR', $_ENV['ZEROX_TMP_DIR']);
		} elseif (array_key_exists('ZEROX_TMP_DIR', $_SERVER)) {
			define('ZEROX_TMP_DIR', $_SERVER['ZEROX_TMP_DIR']);
		} elseif (self::$config->isset('paths', 'tmp_dir')) {
			define('ZEROX_TMP_DIR', self::$config->get('paths', 'tmp_dir'));
		} else {
			define('ZEROX_TMP_DIR', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'tmp');
		}
		ini_set('sys_temp_dir', ZEROX_TMP_DIR);
		return true;
	}

	/**
	 * Init vendor paths and autoloading
	 */
	public static function initVendor(): bool {
		if (defined('ZEROX_IS_VENDORED')) return true;
		// Load vendor autoloader if ZeroX is not loaded as a vendor module
		if (array_key_exists('ZEROX_IS_VENDORED', $_ENV)) {
			define('ZEROX_IS_VENDORED', $_ENV['ZEROX_IS_VENDORED']);
		} elseif (array_key_exists('ZEROX_IS_VENDORED', $_SERVER)) {
			define('ZEROX_IS_VENDORED', $_SERVER['ZEROX_IS_VENDORED']);
		} else {
			define('ZEROX_IS_VENDORED', basename(dirname(ZEROX_BASE_DIR)) === 'alexrsagen');
		}
		if (!ZEROX_IS_VENDORED) {
			if (!file_exists(ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
				error_log(self::$app . '::initVendor: Vendor autoloader is missing. Run "composer install".', E_USER_ERROR);
				return false;
			}
			require ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
		}
		return true;
	}

	/**
	 * Init ZeroX app directory
	 */
	public static function initAppDir(): bool {
		if (defined('ZEROX_APP_DIR')) return true;
		if (!self::$app::initVendor()) return false;
		// Get app directory path
		if (array_key_exists('ZEROX_APP_DIR', $_ENV)) {
			define('ZEROX_APP_DIR', $_ENV['ZEROX_APP_DIR']);
		} elseif (array_key_exists('ZEROX_APP_DIR', $_SERVER)) {
			define('ZEROX_APP_DIR', $_SERVER['ZEROX_APP_DIR']);
		} elseif (ZEROX_IS_VENDORED) {
			define('ZEROX_APP_DIR', dirname(dirname(dirname(ZEROX_BASE_DIR))));
		} else {
			define('ZEROX_APP_DIR', dirname(debug_backtrace()[2]['file']));
		}
		if (!file_exists(ZEROX_APP_DIR) || !is_dir(ZEROX_APP_DIR)) {
			error_log(self::$app . '::initAppDir: App directory is missing or not a directory.', E_USER_ERROR);
			return false;
		}
		return true;
	}

	/**
	 * Init config
	 */
	public static function initConfig(): bool {
		if (self::$config !== null) return true;
		if (!self::$app::initAppDir()) return false;
		if (!defined('ZEROX_CONFIG_PATH')) {
			if (array_key_exists('ZEROX_CONFIG_PATH', $_ENV)) {
				define('ZEROX_CONFIG_PATH', $_ENV['ZEROX_CONFIG_PATH']);
			} elseif (array_key_exists('ZEROX_CONFIG_PATH', $_SERVER)) {
				define('ZEROX_CONFIG_PATH', $_SERVER['ZEROX_CONFIG_PATH']);
			} else {
				define('ZEROX_CONFIG_PATH', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'config.json');
			}
		}
		if (!file_exists(ZEROX_CONFIG_PATH)) {
			error_log(self::$app . '::initConfig: Server configuration is missing. If this is a new installation, create a configuration from the sample file and change the values to match your installation.', E_USER_ERROR);
			return false;
		}
		self::$config = Config::fromJSON(file_get_contents(ZEROX_CONFIG_PATH));
		return true;
	}

	/**
	 * Init router
	 */
	public static function initRouter(): bool {
		if (self::$router !== null) return true;
		if (!self::$app::initTime()) return false;
		if (!self::$app::initConfig()) return false;

		self::$router = Router::getInstance();
		$nonce = base64_encode(random_bytes(16));
		self::$router->vars->set('nonce', $nonce);
		self::$router->vars->set('config', self::$config);
		Router::setResponseHeader('Content-Security-Policy', "img-src data: 'self' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'img') ?? []) . "; media-src 'self' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'media') ?? []) . "; script-src 'self' 'nonce-$nonce' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'script') ?? []) . "; style-src 'self' 'unsafe-inline' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'style') ?? []) . "");
		Router::setResponseHeader('X-XSS-Protection', '1; mode=block');
		Router::setResponseHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
		Router::setResponseHeader('X-Frame-Options', 'SAMEORIGIN');
		Router::setResponseHeader('Date', gmdate('D, d M Y H:i:s T', time()));
		return true;
	}

	/**
	 * Init sessions
	 */
	public static function initSessions(): bool {
		if (Session::started()) return true;
		if (!self::$app::initConfig()) return false;
		if (php_sapi_name() === 'cli' || !self::$config->get('sessions', 'enable')) return false;
		if (!self::$app::initRouter()) return false;
		Session::start();
		Router::defer(function() {
			Session::end();
		});
		return true;
	}

	/**
	 * Init views
	 */
	public static function initViews(): bool {
		if (defined('ZEROX_VIEWS_DIR')) return true;
		if (!self::$app::initAppDir()) return false;
		self::$app::initConfig();
		if (array_key_exists('ZEROX_VIEWS_DIR', $_ENV)) {
			define('ZEROX_VIEWS_DIR', $_ENV['ZEROX_VIEWS_DIR']);
		} elseif (array_key_exists('ZEROX_VIEWS_DIR', $_SERVER)) {
			define('ZEROX_VIEWS_DIR', $_SERVER['ZEROX_VIEWS_DIR']);
		} elseif (self::$config !== null && self::$config->isset('paths', 'views_dir')) {
			define('ZEROX_VIEWS_DIR', self::$config->get('paths', 'views_dir'));
		} else {
			define('ZEROX_VIEWS_DIR', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'views');
		}
		View::$views_dir = ZEROX_VIEWS_DIR;
		View::$default_vars = [
			'base_url' => rtrim(self::$config->get('url'), '/'),
			'site_name' => self::$config->get('site_name')
		];
		return true;
	}

	/**
	 * Init logger
	 */
	public static function initLogger(): bool {
		if (defined('ZEROX_LOGS_DIR')) return true;
		if (!self::$app::initAppDir()) return false;
		self::$app::initConfig();
		if (array_key_exists('ZEROX_LOGS_DIR', $_ENV)) {
			define('ZEROX_LOGS_DIR', $_ENV['ZEROX_LOGS_DIR']);
		} elseif (array_key_exists('ZEROX_LOGS_DIR', $_SERVER)) {
			define('ZEROX_LOGS_DIR', $_SERVER['ZEROX_LOGS_DIR']);
		} elseif (self::$config !== null && self::$config->isset('paths', 'logs_dir')) {
			define('ZEROX_LOGS_DIR', self::$config->get('paths', 'logs_dir'));
		} else {
			define('ZEROX_LOGS_DIR', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'logs');
		}
		Logger::$logs_dir = ZEROX_LOGS_DIR;
		return true;
	}

	/**
	 * Init mail
	 */
	public static function initMail(): bool {
		if (!self::$app::initConfig()) return false;
		if (!self::$app::initTemp()) return false;
		if (!self::$config->get('mail', 'enable')) return false;

		// Set up SwiftMailer
		if (!class_exists('Swift_Preferences')) {
			error_log(self::$app . '::initMail: Unable to load SwiftMailer.', E_USER_ERROR);
			return false;
		}

		// Set SwiftMailer temp folder
		\Swift_Preferences::getInstance()->setTempDir(ZEROX_TMP_DIR)->setCacheType('disk');
		return true;
	}

	/**
	 * Init database
	 */
	public static function initDatabase(): bool {
		if (self::$db !== null && self::$ro_db !== null) return true;
		if (!self::$app::initConfig()) return false;
		if (!self::$config->get('db', 'enable')) return false;
		if (!extension_loaded('pdo')) {
			error_log(self::$app . '::initDatabase: PDO module is not loaded.', E_USER_ERROR);
			return false;
		}

		try {
			self::$db = new \PDO(
				self::$config->get('db', 'dsn'),
				self::$config->get('db', 'username'),
				self::$config->get('db', 'password'),
				self::$config->get('db', 'options')
			);
			self::$db->setAttribute(\PDO::ATTR_TIMEOUT, 5);
			self::$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		} catch (\PDOException $e) {
			error_log(self::$app . '::initDatabase: Unable to connect to database.', E_USER_ERROR);
			return false;
		}
		Models\BaseModel::setDefaultDatabase(self::$db);

		if (self::$config->get('db', 'read_only')) {
			try {
				self::$ro_db = new \PDO(
					self::$config->get('db', 'read_only', 'dsn'),
					self::$config->get('db', 'read_only', 'username'),
					self::$config->get('db', 'read_only', 'password'),
					self::$config->get('db', 'read_only', 'options')
				);
				self::$ro_db->setAttribute(\PDO::ATTR_TIMEOUT, 5);
				self::$ro_db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			} catch (\PDOException $e) {
				error_log(self::$app . '::initDatabase: Unable to connect to read-only database.', E_USER_ERROR);
				return false;
			}
			Models\BaseModel::setDefaultReadOnlyDatabase(self::$ro_db);
		}
		return true;
	}

	public static function initEventHandlers(): bool {
		if (!self::$app::initConfig()) return false;
		if (!self::$config->get('errors', 'handle')) return false;
		// Set up error handler
		if (php_sapi_name() !== 'cli') {
			set_error_handler([self::$app, 'errorHandler']);
		}
		// Set up shutdown handler
		register_shutdown_function([self::$app, 'shutdownHandler']);
		return true;
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		switch ($errno){
			case E_ERROR: // 1
				$typestr = 'E_ERROR'; break;
			case E_WARNING: // 2
				$typestr = 'E_WARNING'; break;
			case E_PARSE: // 4
				$typestr = 'E_PARSE'; break;
			case E_NOTICE: // 8
				$typestr = 'E_NOTICE'; break;
			case E_CORE_ERROR: // 16
				$typestr = 'E_CORE_ERROR'; break;
			case E_CORE_WARNING: // 32
				$typestr = 'E_CORE_WARNING'; break;
			case E_COMPILE_ERROR: // 64
				$typestr = 'E_COMPILE_ERROR'; break;
			case E_CORE_WARNING: // 128
				$typestr = 'E_COMPILE_WARNING'; break;
			case E_USER_ERROR: // 256
				$typestr = 'E_USER_ERROR'; break;
			case E_USER_WARNING: // 512
				$typestr = 'E_USER_WARNING'; break;
			case E_USER_NOTICE: // 1024
				$typestr = 'E_USER_NOTICE'; break;
			case E_STRICT: // 2048
				$typestr = 'E_STRICT'; break;
			case E_RECOVERABLE_ERROR: // 4096
				$typestr = 'E_RECOVERABLE_ERROR'; break;
			case E_DEPRECATED: // 8192
				$typestr = 'E_DEPRECATED'; break;
			case E_USER_DEPRECATED: // 16384
				$typestr = 'E_USER_DEPRECATED'; break;
		}

		$error_plain = $typestr . ': ' . $errstr . ' in ' . $errfile . ' on line ' . $errline;
		$error_html = "<p>A fatal error occurred at " . date(DATE_ATOM) . ".</p><p><b>" . $typestr . ": </b>" . $errstr . " in <b>" . $errfile . "</b> on line <b>" . $errline . "</b></p>";
		error_log($error_plain);

		if (self::$app::initConfig()) {
			if (self::$config->get('errors', 'mail')) {
				Util::sendMail(self::$config->get('errors', 'mail_address'), 'Internal server error on ' . self::$config->get('site_name'), $error_html, true);
			}

			if (self::$app::initRouter()) {
				Router::setResponseCode(Router::HTTP_INTERNAL_SERVER_ERROR);
				if (self::$config->get('errors', 'dump')) {
					Router::sendResponse($error_html);
				} else {
					Router::sendResponse('Internal server error.', Router::CONTENT_TYPE_TEXT);
				}
			}
		}
		exit;
	}

	public static function shutdownHandler() {
		// Handle fatal errors
		$error = error_get_last();
		if ($error && ($error['type'] & (E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))){
			self::$app::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}
}