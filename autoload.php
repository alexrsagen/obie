<?php namespace ZeroX;

// Set globals
if (defined('IN_ZEROX')) {
	return;
}
define('IN_ZEROX', true);
if (!defined('ZEROX_BASE_DIR')) {
	define('ZEROX_BASE_DIR', __DIR__);
}

// Register class autoloader
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
