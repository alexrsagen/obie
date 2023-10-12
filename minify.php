<?php namespace Obie;
use MatthiasMullie\Minify as MMM;

class Minify {
	public static function HTML(string $input, array $options = []): string {
		$m = new TinyHtmlMinifier($options);
		return $m->minify($input);
	}

	public static function CSS(string $input): string {
		if (!class_exists('MMM\CSS')) {
			return $input;
		}
		$m = new MMM\CSS($input);
		return $m->minify();
	}

	public static function JS(string $input): string {
		if (!class_exists('MMM\JS')) {
			return $input;
		}
		$m = new MMM\JS($input);
		return $m->minify();
	}
}
