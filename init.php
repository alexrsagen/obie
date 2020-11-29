<?php namespace Obie;

if (version_compare(phpversion(), '8.0.0', '<')) {
	echo 'PHP version out of date, please update to >= 8.0.0.';
	exit;
}

// Set up globals and class autoloading
require __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

// Initialize app
if (!App::init()) {
	return false;
}

return App::$app;