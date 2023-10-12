<?php namespace Obie;
use Obie\Http\Router;
use Obie\Http\Request;
use Obie\Http\Response;
use Obie\Http\RouterInstance;
use Obie\ApiClients\FidoMds;

/**
 * @property self $app
 */
class App {
	public static string $app = self::class;
	protected static ?Config $config = null;
	protected static ?RouterInstance $router = null;
	protected static ?\PDO $db = null;
	protected static ?\PDO $ro_db = null;

	public static function getConfig(): ?Config {
		return self::$config;
	}

	public static function getRouter(): ?RouterInstance {
		return self::$router;
	}

	public static function getDB(): ?\PDO {
		return self::$db;
	}

	public static function getReadonlyDB(): ?\PDO {
		return self::$ro_db;
	}

	public static function register(): void {
		self::$app = static::class;
	}

	public static function init(): bool {
		try {
			if (!self::$app::initTime()) {
				error_log(self::$app . '::initTime failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initTime failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initVendor()) {
				error_log(self::$app . '::initVendor failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initVendor failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initAppDir()) {
				error_log(self::$app . '::initAppDir failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initAppDir failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initConfig()) {
				error_log(self::$app . '::initConfig failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initConfig failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initLogger()) {
				error_log(self::$app . '::initLogger failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initLogger failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initTemp()) {
				error_log(self::$app . '::initTemp failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initTemp failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initEventHandlers()) {
				error_log(self::$app . '::initEventHandlers failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initEventHandlers failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initLocale()) {
				error_log(self::$app . '::initLocale failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initLocale failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initRouter()) {
				error_log(self::$app . '::initRouter failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initRouter failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initViews()) {
				error_log(self::$app . '::initViews failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initViews failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initSessions()) {
				error_log(self::$app . '::initSessions failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initSessions failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initMail()) {
				error_log(self::$app . '::initMail failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initMail failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::initDatabase()) {
				error_log(self::$app . '::initDatabase failed', E_USER_ERROR);
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::initDatabase failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::doHostRedirect()) {
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::doHostRedirect failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		try {
			if (!self::$app::doSchemeRedirect()) {
				return false;
			}
		} catch (\Exception $e) {
			error_log(self::$app . '::doSchemeRedirect failed: ' . $e->getMessage(), E_USER_ERROR);
			return false;
		}
		return true;
	}

	public static function doHostRedirect(): bool {
		if (!self::$app::initConfig()) return false;
		// Redirect if wrong host header
		if (php_sapi_name() === 'cli') return true;
		if (self::$config->get('host_redirect')) {
			$desired_host = substr(self::$config->get('url'), strpos(self::$config->get('url'), '://') + 3);
			if (Request::current()?->getHost() !== $desired_host) {
				Router::redirect($_SERVER['REQUEST_URI']);
				return false;
			}
		}
		return true;
	}

	public static function doSchemeRedirect(): bool {
		if (!self::$app::initConfig()) return false;
		// Redirect if wrong scheme
		if (php_sapi_name() === 'cli') return true;
		if (self::$config->get('scheme_redirect')) {
			$desired_scheme = substr(self::$config->get('url'), 0, strpos(self::$config->get('url'), '://'));
			if (Request::current()?->getScheme() !== $desired_scheme) {
				Router::redirect($_SERVER['REQUEST_URI']);
				return false;
			}
		}
		return true;
	}

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
		if (defined('OBIE_TMP_DIR')) return true;
		if (!self::$app::initConfig()) return false;
		if (array_key_exists('OBIE_TMP_DIR', $_ENV)) {
			define('OBIE_TMP_DIR', $_ENV['OBIE_TMP_DIR']);
		} elseif (array_key_exists('OBIE_TMP_DIR', $_SERVER)) {
			define('OBIE_TMP_DIR', $_SERVER['OBIE_TMP_DIR']);
		} elseif (self::$config->isset('paths', 'tmp_dir')) {
			define('OBIE_TMP_DIR', self::$config->get('paths', 'tmp_dir'));
		} else {
			define('OBIE_TMP_DIR', OBIE_APP_DIR . DIRECTORY_SEPARATOR . 'tmp');
		}
		ini_set('sys_temp_dir', OBIE_TMP_DIR);
		return true;
	}

	/**
	 * Init vendor paths and autoloading
	 */
	public static function initVendor(): bool {
		if (defined('OBIE_IS_VENDORED')) return true;
		// Load vendor autoloader if Obie is not loaded as a vendor module
		if (array_key_exists('OBIE_IS_VENDORED', $_ENV)) {
			define('OBIE_IS_VENDORED', $_ENV['OBIE_IS_VENDORED']);
		} elseif (array_key_exists('OBIE_IS_VENDORED', $_SERVER)) {
			define('OBIE_IS_VENDORED', $_SERVER['OBIE_IS_VENDORED']);
		} else {
			define('OBIE_IS_VENDORED', basename(dirname(OBIE_BASE_DIR)) === 'alexrsagen');
		}
		if (!OBIE_IS_VENDORED) {
			if (!file_exists(OBIE_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
				error_log(self::$app . '::initVendor: Vendor autoloader is missing. Run "composer install".', E_USER_ERROR);
				return false;
			}
			require OBIE_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
		}
		return true;
	}

	/**
	 * Init Obie app directory
	 */
	public static function initAppDir(): bool {
		if (defined('OBIE_APP_DIR')) return true;
		if (!self::$app::initVendor()) return false;
		// Get app directory path
		if (array_key_exists('OBIE_APP_DIR', $_ENV)) {
			define('OBIE_APP_DIR', $_ENV['OBIE_APP_DIR']);
		} elseif (array_key_exists('OBIE_APP_DIR', $_SERVER)) {
			define('OBIE_APP_DIR', $_SERVER['OBIE_APP_DIR']);
		} elseif (OBIE_IS_VENDORED) {
			define('OBIE_APP_DIR', dirname(dirname(dirname(OBIE_BASE_DIR))));
		} else {
			define('OBIE_APP_DIR', dirname(debug_backtrace()[2]['file']));
		}
		if (!file_exists(OBIE_APP_DIR) || !is_dir(OBIE_APP_DIR)) {
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
		if (!defined('OBIE_CONFIG_PATH')) {
			if (array_key_exists('OBIE_CONFIG_PATH', $_ENV)) {
				define('OBIE_CONFIG_PATH', $_ENV['OBIE_CONFIG_PATH']);
			} elseif (array_key_exists('OBIE_CONFIG_PATH', $_SERVER)) {
				define('OBIE_CONFIG_PATH', $_SERVER['OBIE_CONFIG_PATH']);
			} else {
				define('OBIE_CONFIG_PATH', OBIE_APP_DIR . DIRECTORY_SEPARATOR . 'config.json');
			}
		}
		if (!file_exists(OBIE_CONFIG_PATH)) {
			error_log(self::$app . '::initConfig: Server configuration is missing. If this is a new installation, create a configuration from the sample file and change the values to match your installation.', E_USER_ERROR);
			return false;
		}
		self::$config = Config::fromJSON(file_get_contents(OBIE_CONFIG_PATH));
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
		self::$router->vars->set('config', self::$config->vars);
		self::$router->vars->set('req', Request::current());
		$res = Response::current();
		self::$router->vars->set('res', $res);
		$res?->setHeader('content-security-policy', "img-src data: 'self' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'img') ?? []) . "; media-src 'self' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'media') ?? []) . "; script-src 'self' 'nonce-$nonce' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'script') ?? []) . "; style-src 'self' 'unsafe-inline' " .
			implode(' ', self::$config->get('server', 'csp_sources', 'style') ?? []) . "");
		$res?->setHeader('x-xss-protection', '1; mode=block');
		$res?->setHeader('referrer-policy', 'strict-origin-when-cross-origin');
		$res?->setHeader('x-frame-options', 'SAMEORIGIN');
		$res?->setHeader('date', gmdate('D, d M Y H:i:s T', time()));
		return true;
	}

	/**
	 * Init sessions
	 */
	public static function initSessions(): bool {
		if (Session::started()) return true;
		if (!self::$app::initConfig()) return false;
		if (php_sapi_name() === 'cli' || !self::$config->get('sessions', 'enable')) return true;
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
		if (defined('OBIE_VIEWS_DIR')) return true;
		if (!self::$app::initAppDir()) return false;
		self::$app::initConfig();
		if (array_key_exists('OBIE_VIEWS_DIR', $_ENV)) {
			define('OBIE_VIEWS_DIR', $_ENV['OBIE_VIEWS_DIR']);
		} elseif (array_key_exists('OBIE_VIEWS_DIR', $_SERVER)) {
			define('OBIE_VIEWS_DIR', $_SERVER['OBIE_VIEWS_DIR']);
		} elseif (self::$config !== null && self::$config->isset('paths', 'views_dir')) {
			define('OBIE_VIEWS_DIR', self::$config->get('paths', 'views_dir'));
		} else {
			define('OBIE_VIEWS_DIR', OBIE_APP_DIR . DIRECTORY_SEPARATOR . 'views');
		}
		View::$views_dir = OBIE_VIEWS_DIR;
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
		if (defined('OBIE_LOGS_DIR')) return true;
		if (!self::$app::initAppDir()) return false;
		self::$app::initConfig();
		if (array_key_exists('OBIE_LOGS_DIR', $_ENV)) {
			define('OBIE_LOGS_DIR', $_ENV['OBIE_LOGS_DIR']);
		} elseif (array_key_exists('OBIE_LOGS_DIR', $_SERVER)) {
			define('OBIE_LOGS_DIR', $_SERVER['OBIE_LOGS_DIR']);
		} elseif (self::$config !== null && self::$config->isset('paths', 'logs_dir')) {
			define('OBIE_LOGS_DIR', self::$config->get('paths', 'logs_dir'));
		} else {
			define('OBIE_LOGS_DIR', OBIE_APP_DIR . DIRECTORY_SEPARATOR . 'logs');
		}
		Logger::$logs_dir = OBIE_LOGS_DIR;
		return true;
	}

	/**
	 * Init mail
	 */
	public static function initMail(): bool {
		if (!self::$app::initConfig()) return false;
		if (!self::$app::initTemp()) return false;
		if (!self::$config->get('mail', 'enable')) return true;

		// Set up SwiftMailer
		if (!class_exists('Swift_Preferences')) {
			error_log(self::$app . '::initMail: Unable to load SwiftMailer.', E_USER_ERROR);
			return false;
		}

		// Set SwiftMailer temp folder
		\Swift_Preferences::getInstance()->setTempDir(OBIE_TMP_DIR)->setCacheType('disk');
		return true;
	}

	/**
	 * Init database
	 */
	public static function initDatabase(bool $force = false): bool {
		if (!$force && self::$db !== null && self::$ro_db !== null) return true;
		if (!self::$app::initConfig()) return false;
		if (!self::$config->get('db', 'enable')) return true;
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
			error_log(self::$app . '::initDatabase: Unable to connect to database: ' . $e->getMessage(), E_USER_ERROR);
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
				error_log(self::$app . '::initDatabase: Unable to connect to read-only database: ' . $e->getMessage(), E_USER_ERROR);
				return false;
			}
			Models\BaseModel::setDefaultReadOnlyDatabase(self::$ro_db);
		}
		return true;
	}

	public static function initEventHandlers(): bool {
		if (!self::$app::initConfig()) return false;
		if (!self::$config->get('errors', 'handle')) return true;
		// Set up error handler
		if (php_sapi_name() !== 'cli') {
			set_error_handler([self::$app, 'errorHandler']);
		}
		// Set up shutdown handler
		register_shutdown_function([self::$app, 'shutdownHandler']);
		return true;
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline): void {
		if (!(error_reporting() & $errno)) {
			// This error code is not included in error_reporting, so let it fall
			// through to the standard PHP error handler
			return;
		}

		$typestr = match ($errno) {
			E_ERROR => 'E_ERROR',
			E_WARNING => 'E_WARNING',
			E_PARSE => 'E_PARSE',
			E_NOTICE => 'E_NOTICE',
			E_CORE_ERROR => 'E_CORE_ERROR',
			E_CORE_WARNING => 'E_CORE_WARNING',
			E_COMPILE_ERROR => 'E_COMPILE_ERROR',
			E_CORE_WARNING => 'E_COMPILE_WARNING',
			E_USER_ERROR => 'E_USER_ERROR',
			E_USER_WARNING => 'E_USER_WARNING',
			E_USER_NOTICE => 'E_USER_NOTICE',
			E_STRICT => 'E_STRICT',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED => 'E_DEPRECATED',
			E_USER_DEPRECATED => 'E_USER_DEPRECATED',
		};

		$error_plain = $typestr . ': ' . $errstr . ' in ' . $errfile . ' on line ' . $errline;
		$error_html = "<p>A fatal error occurred at " . date(DATE_ATOM) . ".</p><p><b>" . $typestr . ": </b>" . $errstr . " in <b>" . $errfile . "</b> on line <b>" . $errline . "</b></p>";
		error_log($error_plain);

		if (self::$app::initConfig()) {
			if (self::$config->get('errors', 'mail')) {
				self::$app::sendMail(self::$config->get('errors', 'mail_address'), 'Internal server error on ' . self::$config->get('site_name'), $error_html, true);
			}

			if (self::$app::initRouter()) {
				Response::current()?->setCode(Response::HTTP_INTERNAL_SERVER_ERROR);
				if (self::$config->get('errors', 'dump')) {
					Router::sendResponse($error_html);
				} else {
					Router::sendResponse('Internal server error.', Router::CONTENT_TYPE_TEXT);
				}
			}
		}
		exit;
	}

	public static function shutdownHandler(): void {
		// Handle fatal errors
		$error = error_get_last();
		if ($error && ($error['type'] & (E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))){
			self::$app::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}

	public static function sendMail(string|array $recipients, string $subject, string $body, bool $is_html = false): bool {
		if (!self::$app::initConfig()) return false;

		if (is_string($recipients)) {
			$recipients = [$recipients];
		}
		if (!self::$config->get('mail', 'enable')) {
			throw new \Exception('Mail is not enabled in the server configuration');
		}

		$transport = new \Swift_SmtpTransport(self::$config->get('mail', 'host'), self::$config->get('mail', 'port'), self::$config->get('mail', 'security'));
		$transport->setUsername(self::$config->get('mail', 'username'));
		$transport->setPassword(self::$config->get('mail', 'password'));

		$mailer = new \Swift_Mailer($transport);

		$message = new \Swift_Message($subject);
		$message->setFrom([self::$config->get('mail', 'from_email') => self::$config->get('mail', 'from_name')]);
		$message->setTo($recipients);
		$message->setBody($body, $is_html ? 'text/html' : 'text/plain');

		try {
			return $mailer->send($message);
		} catch (\Exception $e) {
			return false;
		}
	}

	protected static ?I18n $i18n = null;
	public static function getI18n(): I18n {
		if (static::$i18n === null) {
			static::$i18n = I18n::new();
		}
		return static::$i18n;
	}

	protected static ?FidoMds $fido_mds = null;
	public static function getFidoMds(): FidoMds {
		if (static::$fido_mds === null) {
			static::$fido_mds = new FidoMds();
		}
		return static::$fido_mds;
	}
}