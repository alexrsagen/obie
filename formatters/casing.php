<?php namespace Obie\Formatters;

class Casing {
	public static function camelToSnake(string $name) {
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
	}

	public static function snakeToCamel(string $name) {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
	}
}