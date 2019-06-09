<?php namespace MyZeroXApp;
use ZeroX\Router,
	ZeroX\RouterInstance;

// Set up app class autoloading
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
		$cur_path = ZEROX_APP_DIR . DIRECTORY_SEPARATOR .
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

// Autoload route
$route_parts = explode('/', ltrim(str_replace('/./', '', preg_replace('/(\.)\.+|(\/)\/+/g', '$1$2', '/' . trim(Router::getPath(), '/'))), '/'));
$route_cur_dir = ZEROX_APP_DIR . DIRECTORY_SEPARATOR . 'routes';
$route_handler = $cur_route_dir . DIRECTORY_SEPARATOR . 'index.php';
foreach ($route_parts as $part) {
	// Convert part to snake case
	$part = strtolower(str_replace('-', '_', preg_replace('/(?<!^)[A-Z]/', '_$0', $part)));

	// Attempt file <$route_cur_dir>/<$part>.php
	$part_handler = realpath($route_cur_dir . DIRECTORY_SEPARATOR . $part . '.php');
	if (strncmp($part_handler, $route_cur_dir, strlen($route_cur_dir)) === 0 && file_exists($part_handler) && is_file($part_handler)) {
		$route_handler = $part_handler;
	}

	// Attempt directory <$route_cur_dir>/<$part>
	$part_dir = realpath($route_cur_dir . DIRECTORY_SEPARATOR . $part);
	if (strncmp($part_dir, $route_cur_dir, strlen($route_cur_dir)) === 0 && file_exists($part_dir) && is_dir($part_dir)) {
		// Attempt file <$route_cur_dir>/<$part>/index.php
		if ($route_handler !== $part_handler) {
			$part_handler = $part_dir . DIRECTORY_SEPARATOR . 'index.php';
			if (file_exists($part_handler) && is_file($part_handler)) {
				$route_handler = $part_handler;
			}
		}

		$route_cur_dir = $part_dir;
	} else {
		break;
	}
}
require $route_handler;

// Execute routes and handle 404/405
switch (Router::execute()) {
	case RouterInstance::ENOT_FOUND:
		Router::setResponseCode(Router::HTTP_NOT_FOUND);
		Router::sendResponse(Router::HTTP_STATUSTEXT[Router::HTTP_NOT_FOUND], Router::CONTENT_TYPE_TEXT);
		break;

	case RouterInstance::EINVALID_METHOD:
		Router::setResponseCode(Router::HTTP_METHOD_NOT_ALLOWED);
		Router::sendResponse(Router::HTTP_STATUSTEXT[Router::HTTP_METHOD_NOT_ALLOWED], Router::CONTENT_TYPE_TEXT);
		break;
}
