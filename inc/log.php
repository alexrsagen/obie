<?php namespace ZeroX;
use ZeroX\Config;

class Log {
	const DEFAULT_LOG_NAME = 'app';

	private static $loggers = [];

	public static function logger($name) {
		if (!array_key_exists($name, static::$loggers)) {
			static::$loggers[$name] = new Logger($name);
		}
		return static::$loggers[$name];
	}

	public static function __callStatic(string $method_name, array $args) {
		if (is_callable([static::logger(self::DEFAULT_LOG_NAME), $method_name])) {
			return call_user_func_array([static::logger(self::DEFAULT_LOG_NAME), $method_name], $args);
		}
	}
}
