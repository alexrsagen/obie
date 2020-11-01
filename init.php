<?php namespace ZeroX;

if (version_compare(phpversion(), '7.0.0', '<')) {
	echo 'PHP version out of date, please update to >=7.0.0.';
	return false;
}

// Set up globals and class autoloading
require __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

// Initialize app
if (!App::init()) {
	return false;
}

// Redirect if wrong host header
if (php_sapi_name() !== 'cli') {
	if (App::getConfig()->get('host_redirect')) {
		$desired_host = substr(App::getConfig()->get('url'), strpos(App::getConfig()->get('url'), '://') + 3);
		if (Router::getHost() !== $desired_host) {
			Router::redirect($_SERVER['REQUEST_URI']);
			return true;
		}
	}
	if (App::getConfig()->get('scheme_redirect')) {
		$desired_scheme = substr(App::getConfig()->get('url'), 0, strpos(App::getConfig()->get('url'), '://'));
		if (Router::getScheme() !== $desired_scheme) {
			Router::redirect($_SERVER['REQUEST_URI']);
			return true;
		}
	}
}

return App::$app;