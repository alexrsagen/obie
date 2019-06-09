<?php

// Initialize ZeroX
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'init.php';

// Get app path
if (!defined('ZEROX_APP_PATH')) {
	if (array_key_exists('ZEROX_APP_PATH', $_ENV)) {
		define('ZEROX_APP_PATH', $_ENV['ZEROX_APP_PATH']);
	} else {
		define('ZEROX_APP_PATH', ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'index.php');
	}
}

// Let app take over from here
if (!file_exists(ZEROX_APP_PATH)) {
	ZeroX\Router::sendResponse('No app found. If this is a new installation, create "app/index.php".', Router::CONTENT_TYPE_TEXT);
	return;
}
require ZEROX_APP_PATH;
