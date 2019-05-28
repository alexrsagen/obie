<?php namespace ZeroX;
if (version_compare(phpversion(), '7.0.0', '<')) {
	echo 'PHP version out of date, please update to >=7.0.0.';
	return;
}

// Set globals
define('IN_ZEROX', true);
define('ZEROX_BASE_DIR', __DIR__ . DIRECTORY_SEPARATOR . '..');

// Set timezone
date_default_timezone_set('UTC');

// Set default language
putenv('LC_ALL=en_US.UTF-8');
setlocale(LC_ALL, 'en_US.UTF-8');

// Set temp folder
ini_set('sys_temp_dir', ZEROX_BASE_DIR . '/tmp');

// Set up class autoloading
spl_autoload_register(function(string $name) {
	$ns_parts = explode('\\', $name);

	// Only apply this autoloader to classes beginning with the namespace of this file
	if (count($ns_parts) < 2 || $ns_parts[0] !== __NAMESPACE__) {
		return;
	}

	for ($i = 1; $i < count($ns_parts); $i++) {
		// Convert item to snake case
		$ns_parts[$i] = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $ns_parts[$i]));

		// Get current path
		$cur_path = ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .
			implode(DIRECTORY_SEPARATOR, array_slice($ns_parts, 1, $i));

		// Ensure that current path exists
		if ($i === count($ns_parts)-1) {
			$cur_path .= '.php';
			if (!file_exists($cur_path) || !is_file($cur_path)) {
				return;
			}
			require $cur_path;
		} elseif (!file_exists($cur_path) || !is_dir($cur_path)) {
			return;
		}
	}
});

// Set up router
$nonce = base64_encode(random_bytes(16));
Router::getInstance()->vars->set('nonce', $nonce);
Router::setResponseHeader('Content-Security-Policy', "img-src data: 'self'; media-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'");
Router::setResponseHeader('X-XSS-Protection', '1; mode=block');
Router::setResponseHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
Router::setResponseHeader('X-Frame-Options', 'SAMEORIGIN');
Router::setResponseHeader('Date', gmdate('D, d M Y H:i:s T', time()));

// Load config
if (!file_exists(ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'inc/app/config.json')) {
	Router::sendResponse('Server configuration is missing. If this is a new installation, copy "inc/app/config.sample.json" to "inc/app/config.json" and change the values to match your installation.', Router::CONTENT_TYPE_TEXT);
	return;
}
$config = Config::fromJSON(file_get_contents(ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'inc/app/config.json'));
Router::getInstance()->vars->set('config', $config);

// Set language from config
putenv('LC_ALL=' . $config->get('lang') . '.UTF-8');
setlocale(LC_ALL, $config->get('lang') . '.UTF-8');

// Update CSP with values from config
Router::setResponseHeader('Content-Security-Policy', "img-src data: 'self' " .
	implode(' ', $config->get('server', 'csp_sources')) . "; media-src 'self' " .
	implode(' ', $config->get('server', 'csp_sources')) . "; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'");

// Set up sessions
if ($config->get('sessions', 'enable')) {
	Session::start();
	Router::defer(function() {
		Session::end();
	});
}

// Redirect if wrong host header
if ($config->get('host_redirect')) {
	$desired_host = substr($config->get('url'), strpos($config->get('url'), '://') + 3);
	if ($_SERVER['HTTP_HOST'] !== $desired_host) {
		Router::redirect('/');
		return;
	}
}

// Load third-party modules
if ($config->get('mail', 'enable')) {
	if (!file_exists(ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor/autoload.php')) {
		Router::sendResponse('Vendor libraries are missing. Run "composer update".', Router::CONTENT_TYPE_TEXT);
		return;
	}
	require ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

	// Set SwiftMailer temp folder
	\Swift_Preferences::getInstance()->setTempDir(ZEROX_BASE_DIR . '/tmp')->setCacheType('disk');
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
		exit;
	}

	Models\BaseModel::setDefaultDatabase($db);
}

// Error handler
$error_handler = function($errno, $errstr, $errfile, $errline) use($config) {
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

	if ($config->get('errors', 'mail')) {
		Util::sendMail('info+error@0x.ms', 'Internal server error on 0x.ms', $error_html, true);
	}

	if ($config->get('errors', 'dump')) {
		Router::sendResponse($error_html);
	} else {
		Router::sendResponse('Something went wrong while processing the request.', Router::CONTENT_TYPE_TEXT);
	}
	exit;
};

// Set up error handler
if ($config->get('errors', 'handle')) {
	set_error_handler($error_handler);
}

// Set up router deferred functions and fatal error handler
register_shutdown_function(function() use($error_handler, $config) {
	Router::runDeferred();

	if ($config->get('errors', 'handle')) {
		$error = error_get_last();
		if ($error && ($error['type'] & (E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))){
			$error_handler($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}
});

// Let app take over from here
if (!file_exists(ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'inc/app/index.php')) {
	Router::sendResponse('No app found. If this is a new installation, create "inc/app/index.php".', Router::CONTENT_TYPE_TEXT);
	return;
}
require ZEROX_BASE_DIR . DIRECTORY_SEPARATOR . 'inc/app/index.php';
