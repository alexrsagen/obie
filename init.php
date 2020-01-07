<?php namespace ZeroX;

if (version_compare(phpversion(), '7.0.0', '<')) {
	echo 'PHP version out of date, please update to >=7.0.0.';
	return;
}

// Set timezone
date_default_timezone_set('UTC');

// Set default language
putenv('LC_ALL=en_US' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
setlocale(LC_ALL, 'en_US' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));

// Set up globals and class autoloading
require __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

// Load vendor autoloader if ZeroX is not loaded as a vendor module
if (!defined('ZEROX_IS_VENDORED')) {
	if (array_key_exists('ZEROX_IS_VENDORED', $_ENV)) {
		define('ZEROX_IS_VENDORED', $_ENV['ZEROX_IS_VENDORED']);
	} else {
		define('ZEROX_IS_VENDORED', basename(dirname(ZEROX_BASE_DIR)) === 'alexrsagen');
	}
}
if (!ZEROX_IS_VENDORED) {
	if (!file_exists(ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
		Router::sendResponse('Vendor autoloader is missing. Run "composer install".', Router::CONTENT_TYPE_TEXT);
		return;
	}
	require ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

// Get app directory path
if (!defined('ZEROX_APP_DIR')) {
	if (array_key_exists('ZEROX_APP_DIR', $_ENV)) {
		define('ZEROX_APP_DIR', $_ENV['ZEROX_APP_DIR']);
	} elseif (ZEROX_IS_VENDORED) {
		define('ZEROX_APP_DIR', dirname(dirname(dirname(ZEROX_BASE_DIR))));
	} else {
		define('ZEROX_APP_DIR', ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'app');
	}
}

if (!file_exists(ZEROX_APP_DIR) || !is_dir(ZEROX_APP_DIR)) {
	Router::sendResponse('App directory is missing or not a directory.', Router::CONTENT_TYPE_TEXT);
	return;
}

// Set up router
$nonce = base64_encode(random_bytes(16));
Router::getInstance()->vars->set('nonce', $nonce);
Router::setResponseHeader('Content-Security-Policy', "img-src data: 'self'; media-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'");
Router::setResponseHeader('X-XSS-Protection', '1; mode=block');
Router::setResponseHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
Router::setResponseHeader('X-Frame-Options', 'SAMEORIGIN');
Router::setResponseHeader('Date', gmdate('D, d M Y H:i:s T', time()));

// Load config
if (!defined('ZEROX_CONFIG_PATH')) {
	if (array_key_exists('ZEROX_CONFIG_PATH', $_ENV)) {
		define('ZEROX_CONFIG_PATH', $_ENV['ZEROX_CONFIG_PATH']);
	} else {
		define('ZEROX_CONFIG_PATH', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'config.json');
	}
}
if (!file_exists(ZEROX_CONFIG_PATH)) {
	Router::sendResponse('Server configuration is missing. If this is a new installation, create a configuration from the sample file and change the values to match your installation.', Router::CONTENT_TYPE_TEXT);
	return;
}
$config = Config::fromJSON(file_get_contents(ZEROX_CONFIG_PATH));
Router::getInstance()->vars->set('config', $config);

// Redirect if wrong host header
if (php_sapi_name() !== 'cli' && $config->get('host_redirect')) {
	$desired_host = substr($config->get('url'), strpos($config->get('url'), '://') + 3);
	if (Router::getHost() !== $desired_host) {
		Router::redirect('/');
		return;
	}
}

// Set language from config
putenv('LC_ALL=' . $config->get('lang') . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));
setlocale(LC_ALL, $config->get('lang') . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '' : '.utf8'));

// Update CSP with values from config
Router::setResponseHeader('Content-Security-Policy', "img-src data: 'self' " .
	implode(' ', $config->get('server', 'csp_sources', 'img')) . "; media-src 'self' " .
	implode(' ', $config->get('server', 'csp_sources', 'media')) . "; script-src 'self' 'nonce-$nonce' " .
	implode(' ', $config->get('server', 'csp_sources', 'script')) . "; style-src 'self' 'unsafe-inline' " .
	implode(' ', $config->get('server', 'csp_sources', 'style')) . "");

// Set up sessions
if ($config->get('sessions', 'enable')) {
	Session::start();
	Router::defer(function() {
		Session::end();
	});
}

// Set up views
if (!defined('ZEROX_VIEWS_DIR')) {
	if (array_key_exists('ZEROX_VIEWS_DIR', $_ENV)) {
		define('ZEROX_VIEWS_DIR', $_ENV['ZEROX_VIEWS_DIR']);
	} elseif ($config->isset('paths', 'views_dir')) {
		define('ZEROX_VIEWS_DIR', $config->get('paths', 'views_dir'));
	} else {
		define('ZEROX_VIEWS_DIR', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'views');
	}
}
View::$views_dir = ZEROX_VIEWS_DIR;
View::$default_vars = [
	'base_url' => rtrim($config->get('url'), '/'),
	'site_name' => $config->get('site_name')
];

// Set temp folder
if (!defined('ZEROX_TMP_DIR')) {
	if (array_key_exists('ZEROX_TMP_DIR', $_ENV)) {
		define('ZEROX_TMP_DIR', $_ENV['ZEROX_TMP_DIR']);
	} elseif ($config->isset('paths', 'tmp_dir')) {
		define('ZEROX_TMP_DIR', $config->get('paths', 'tmp_dir'));
	} else {
		define('ZEROX_TMP_DIR', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'tmp');
	}
}
ini_set('sys_temp_dir', ZEROX_TMP_DIR);

// Set up SwiftMailer if mail is enabled in config
if ($config->get('mail', 'enable')) {
	if (!class_exists('Swift_Preferences')) {
		Router::sendResponse('Unable to load SwiftMailer.', Router::CONTENT_TYPE_TEXT);
		return;
	}

	// Set SwiftMailer temp folder
	\Swift_Preferences::getInstance()->setTempDir(ZEROX_TMP_DIR)->setCacheType('disk');
}

// Load database
if ($config->get('db', 'enable')) {
	if (!extension_loaded('pdo')) {
		Router::sendResponse('PDO module is not loaded.', Router::CONTENT_TYPE_TEXT);
		return;
	}

	try {
		$db = new \PDO(
			$config->get('db', 'dsn'),
			$config->get('db', 'username'),
			$config->get('db', 'password'),
			$config->get('db', 'options')
		);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	} catch (\PDOException $e) {
		Router::sendResponse('Unable to connect to database.', Router::CONTENT_TYPE_TEXT);
		return;
	}

	Models\BaseModel::setDefaultDatabase($db);
}

// Set up error handler
if ($config->get('errors', 'handle')) {
	set_error_handler('\ZeroX\Util::errorHandler');
}

// Set up shutdown handler
register_shutdown_function('\ZeroX\Util::shutdownHandler');
