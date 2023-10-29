<?php namespace Obie\Formatters;

class Casing {
	public static function camelToSnake(string $name): string {
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
	}

	public static function snakeToCamel(string $name): string {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
	}
}