<?php namespace Obie\Encoding;

class Jsonc extends Json {
	public static function decode(string $input, bool $assoc = true, int $depth = 512, int $options = 0): mixed {
		// remove comments
		$input = preg_replace('/(" (?:\\\\. | [^"])*+ ") | \# [^\v]*+ | \/\/ [^\v]*+ | \/\* .*? \*\//xs', '$1', $input);

		// remove trailing comma on last property of objects/arrays
		$input = preg_replace('/,(\s+[}\]])/', '$1', $input);

		return parent::decode($input, $assoc, $depth, $options);
	}
}