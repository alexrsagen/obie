<?php namespace Obie\Vars;

trait StaticVarTrait {
	public static $vars = null;

	protected static function _init_vars(&$storage = null, bool $assoc = false) {
		if (self::$vars === null) {
			if (is_array($storage)) {
				self::$vars = new VarCollection($storage, $assoc);
			} elseif (is_a($storage, '\Obie\Vars\VarCollection')) {
				self::$vars = $storage;
			} else {
				self::$vars = new VarCollection();
			}
		}
	}

	public static function get(...$v): mixed {
		self::_init_vars();
		return self::$vars->get(...$v);
	}

	public static function getHTMLEscaped(...$v): string {
		self::_init_vars();
		$res = self::$vars->get(...$v);
		if ($res === null) return null;
		return htmlentities((string)$res);
	}

	public static function getURLEscaped(...$v): string {
		self::_init_vars();
		$res = self::$vars->get(...$v);
		if ($res === null) return null;
		return urlencode((string)$res);
	}

	public static function set(...$v): void {
		self::_init_vars();
		self::$vars->set(...$v);
	}

	public static function unset(...$v): void {
		self::_init_vars();
		self::$vars->unset(...$v);
	}

	public static function isset(...$v): bool {
		self::_init_vars();
		return self::$vars->isset(...$v);
	}
}
