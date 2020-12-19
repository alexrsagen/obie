<?php namespace Obie\Formatters;

class EnglishNoun {
	public static function toSingular(string $name) {
		if (substr($name, -3) === 'ies') {
			return substr($name, 0, -3) . 'y';
		} elseif (substr($name, -2) === 'es') {
			return substr($name, -2);
		} elseif (substr($name, -1) === 's') {
			return substr($name, 0, -1);
		}
		return $name;
	}

	public static function toPlural(string $name) {
		$last_two    = substr($name, -2);
		$last        = substr($last_two, -1);
		$second_last = substr($last_two, 0, 1);
		if ($last === 'y') {
			if (
				$second_last === 'a' ||
				$second_last === 'e' ||
				$second_last === 'i' ||
				$second_last === 'o' ||
				$second_last === 'u'
			) {
				return $name . 's';
			}
			return substr($name, 0, -1) . 'ies';
		} elseif ($last === 's') {
			if ($second_last === 'u') {
				return substr($name, 0, -2) . 'i';
			}
			if ($second_last === 'i') {
				return substr($name, 0, -2) . 'es';
			}
			return $name . 'es';
		} elseif (
			$last === 'o' ||
			$last === 'x' ||
			$last === 'z' ||
			$last_two === 'sh' ||
			$last_two === 'ch'
		) {
			return $name . 'es';
		} elseif ($last_two === 'on') {
			return substr($name, 0, -2) . 'a';
		}
		return $name . 's';
	}

	protected static function getClassName(string $class_name): string {
		$class_name_ns_pos = strrpos($class_name, '\\');
		if ($class_name_ns_pos !== false) {
			$class_name = substr($class_name, $class_name_ns_pos + 1);
		}
		return $class_name;
	}

	public static function classNameToSingular(string $class_name) {
		return static::toSingular(Casing::camelToSnake(static::getClassName($class_name)));
	}

	public static function classNameToPlural(string $class_name) {
		return static::toPlural(Casing::camelToSnake(static::getClassName($class_name)));
	}
}