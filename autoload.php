<?php namespace Obie;

// Set globals
if (defined('IN_OBIE')) {
	return;
}
define('IN_OBIE', true);
if (!defined('OBIE_BASE_DIR')) {
	define('OBIE_BASE_DIR', __DIR__);
}

// Register class autoloader
spl_autoload_register(function(string $name) {
	static $base_path = OBIE_BASE_DIR . DIRECTORY_SEPARATOR;

	$ns_parts = explode('\\', $name);

	// Only apply this autoloader to classes beginning with the namespace of this file
	if (count($ns_parts) < 2 || $ns_parts[0] !== __NAMESPACE__) {
		return;
	}

	for ($i = 1; $i < count($ns_parts); $i++) {
		// Convert item to snake case
		$ns_parts[$i] = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $ns_parts[$i]));

		// Get current path
		$cur_path = $base_path . implode(DIRECTORY_SEPARATOR, array_slice($ns_parts, 1, $i));

		if ($i === count($ns_parts) - 1) {
			// Attempt to load 'class_name.php'
			$cur_file = realpath($cur_path . '.php');
			if (
				$cur_file !== false &&
				strncmp($cur_file, $base_path, strlen($base_path)) === 0 &&
				file_exists($cur_file) &&
				is_file($cur_file)
			) {
				require $cur_file;
			}

			// Attempt to load 'class_name/index.php'
			$cur_file = realpath($cur_path . DIRECTORY_SEPARATOR . 'index.php');
			if (
				$cur_file !== false &&
				strncmp($cur_file, $base_path, strlen($base_path)) === 0 &&
				file_exists($cur_file) &&
				is_file($cur_file)
			) {
				require $cur_file;
			}
		} else {
			if (!file_exists($cur_path) || !is_dir($cur_path)) return;
		}
	}
});
