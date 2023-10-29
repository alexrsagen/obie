<?php namespace Obie\Models;

class ModelHelpers {
	public static function getEscapedList(array $column_names = [], string $table_prefix = null, bool $column_names_are_escaped = false, bool $table_prefix_is_escaped = false) {
		$list_parts = [];
		foreach ($column_names as $i => $key) {
			$list_parts[$i] = '';
			if ($table_prefix !== null) {
				if ($table_prefix_is_escaped) {
					$list_parts[$i] .= $table_prefix . '.';
				} else {
					$list_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
				}
			}
			if ($column_names_are_escaped) {
				$list_parts[$i] .= $key;
			} else {
				$list_parts[$i] .= static::getEscapedSource($key);
			}
		}
		return implode(',', $list_parts);
	}

	public static function getEscapedSet(array $column_names = [], string $table_prefix = null, bool $column_names_are_escaped = false, bool $table_prefix_is_escaped = false) {
		$set_parts = [];
		foreach ($column_names as $i => $key) {
			$set_parts[$i] = '';
			if ($table_prefix !== null) {
				if ($table_prefix_is_escaped) {
					$set_parts[$i] .= $table_prefix . '.';
				} else {
					$set_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
				}
			}
			if ($column_names_are_escaped) {
				$set_parts[$i] .= $key . ' = ?';
			} else {
				$set_parts[$i] .= static::getEscapedSource($key) . ' = ?';
			}
		}
		return implode(',', $set_parts);
	}

	public static function getEscapedWhere(array $column_names = [], string $table_prefix = null, string $kind = 'AND', string $op = '=', int $value_count = 1, string $unsafe_value = '?', bool $column_names_are_escaped = false, $table_prefix_is_escaped = false) {
		$where_parts = [];
		foreach ($column_names as $i => $key) {
			$where_parts[$i] = '';
			if ($table_prefix !== null) {
				if ($table_prefix_is_escaped) {
					$where_parts[$i] .= $table_prefix . '.';
				} else {
					$where_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
				}
			}
			if ($column_names_are_escaped) {
				$where_parts[$i] .= $key . ' ';
			} else {
				$where_parts[$i] .= static::getEscapedSource($key) . ' ';
			}
			$op = strtoupper($op);
			if (in_array($op, ['IN', 'NOT IN'], true)) {
				$where_parts[$i] .= $op . '(' . implode(',', array_fill(0, $value_count, '?')) . ')';
			} elseif (in_array($op, ['IS NULL', 'IS NOT NULL'], true)) {
				$where_parts[$i] .= $op;
			} else {
				$where_parts[$i] .= $op . ' ' . $unsafe_value;
			}
		}
		return implode(' ' . trim($kind) . ' ', $where_parts);
	}

	public static function getEscapedOn(array $source_column_names = [], string $source_table_prefix = null, array $target_column_names = [], string $target_table_prefix = null, bool $column_names_are_escaped = false, bool $table_prefix_is_escaped = false) {
		if (count($source_column_names) !== count($target_column_names)) {
			throw new \InvalidArgumentException('Source and target column count must be equal');
		}
		$on_parts = [];
		for ($i = 0; $i < count($source_column_names); $i++) {
			$on_parts[$i] = '';
			if ($source_table_prefix !== null) {
				if ($table_prefix_is_escaped) {
					$on_parts[$i] .= $source_table_prefix . '.';
				} else {
					$on_parts[$i] .= static::getEscapedSource($source_table_prefix) . '.';
				}
			}
			if ($column_names_are_escaped) {
				$on_parts[$i] .= $source_column_names[$i] . ' = ';
			} else {
				$on_parts[$i] .= static::getEscapedSource($source_column_names[$i]) . ' = ';
			}
			if ($target_table_prefix !== null) {
				if ($table_prefix_is_escaped) {
					$on_parts[$i] .= $target_table_prefix . '.';
				} else {
					$on_parts[$i] .= static::getEscapedSource($target_table_prefix) . '.';
				}
			}
			if ($column_names_are_escaped) {
				$on_parts[$i] .= $target_column_names[$i];
			} else {
				$on_parts[$i] .= static::getEscapedSource($target_column_names[$i]);
			}
		}
		return implode(' AND ', $on_parts);
	}

	public static function getEscapedSource(string $source) {
		return '`' . str_replace('`', '``', $source) . '`';
	}
}
