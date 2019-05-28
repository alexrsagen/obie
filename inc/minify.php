<?php namespace ZeroX;
use MatthiasMullie\Minify as MMM;
if (!defined('IN_ZEROX')) {
	exit;
}

class Minify {
	public static function HTML(string $input, array $options = []) {
		$m = new TinyHtmlMinifier($options);
		return $m->minify($input);
	}

	public static function CSS(string $input) {
		if (!class_exists('MMM\CSS')) {
			return $input;
		}
		$m = new MMM\CSS($input);
		return $m->minify();
	}

	public static function JS(string $input) {
		if (!class_exists('MMM\JS')) {
			return $input;
		}
		$m = new MMM\JS($input);
		return $m->minify();
	}
}
