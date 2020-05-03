<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

class ModelHelpers {
	public static function getSingular(string $name) {
		if (substr($name, -3) === 'ies') {
			return substr($name, 0, -3) . 'y';
		} elseif (substr($name, -2) === 'es') {
			return substr($name, -2);
		} elseif (substr($name, -1) === 's') {
			return substr($name, 0, -1);
		}
		return $name;
	}

	public static function getPlural(string $name) {
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

	public static function getSnakeCase(string $name) {
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
	}

	public static function getSingularFromClassNS(string $class_name) {
		$class_name_ns_pos = strrpos($class_name, '\\');
		if ($class_name_ns_pos !== false) {
			$class_name = substr($class_name, $class_name_ns_pos + 1);
		}
		return static::getSingular(static::getSnakeCase($class_name));
	}

	public static function getEscapedList(array $column_names = [], string $table_prefix = null) {
		$list_parts = [];
		foreach ($column_names as $i => $key) {
			$list_parts[$i] = '';
			if ($table_prefix !== null) {
				$list_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
			}
			$list_parts[$i] .= static::getEscapedSource($key);
		}
		return implode(',', $list_parts);
	}

	public static function getEscapedSet(array $column_names = [], string $table_prefix = null) {
		$set_parts = [];
		foreach ($column_names as $i => $key) {
			$set_parts[$i] = '';
			if ($table_prefix !== null) {
				$set_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
			}
			$set_parts[$i] .= static::getEscapedSource($key) . ' = ?';
		}
		return implode(',', $set_parts);
	}

	public static function getEscapedWhere(array $column_names = [], string $table_prefix = null) {
		$where_parts = [];
		foreach ($column_names as $i => $key) {
			$where_parts[$i] = '';
			if ($table_prefix !== null) {
				$where_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
			}
			$where_parts[$i] .= static::getEscapedSource($key) . ' = ?';
		}
		return implode(' AND ', $where_parts);
	}

	public static function getEscapedOn(array $source_column_names = [], string $source_table_prefix = null,
		array $target_column_names = [], string $target_table_prefix = null) {
		if (count($source_column_names) !== count($target_column_names)) {
			throw new \InvalidArgumentException('Source and target column count must be equal');
		}
		$on_parts = [];
		for ($i = 0; $i < count($source_column_names); $i++) {
			$on_parts[$i] = '';
			if ($source_table_prefix !== null) {
				$on_parts[$i] .= static::getEscapedSource($source_table_prefix) . '.';
			}
			$on_parts[$i] .= static::getEscapedSource($source_column_names[$i]) . ' = ';
			if ($target_table_prefix !== null) {
				$on_parts[$i] .= static::getEscapedSource($target_table_prefix) . '.';
			}
			$on_parts[$i] .= static::getEscapedSource($target_column_names[$i]);
		}
		return implode(' AND ', $on_parts);
	}

	public static function getEscapedSource(string $source) {
		return '`' . str_replace('`', '``', $source) . '`';
	}
}
